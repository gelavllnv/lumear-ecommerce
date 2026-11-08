import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

abstract final class LogisticsApi {
  static const baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://lumearofficial.com/api',
  );
  static String? _token;

  static Future<Map<String, dynamic>> request(String method, String path, [Map<String, dynamic>? body]) async {
    final base = Uri.tryParse(baseUrl);
    if (base == null || !base.hasAuthority || !['http', 'https'].contains(base.scheme)) throw const FormatException('Set API_BASE_URL to your Laravel API address.');
    final client = HttpClient()..connectionTimeout = const Duration(seconds: 20);
    try {
      final request = await client.openUrl(method, Uri.parse('${baseUrl.replaceFirst(RegExp(r'/$'), '')}$path')).timeout(const Duration(seconds: 20));
      request.headers.set('Accept', 'application/json');
      request.headers.contentType = ContentType.json;
      if (_token != null) request.headers.set('Authorization', 'Bearer $_token');
      if (body != null) request.write(jsonEncode(body));
      final response = await request.close().timeout(const Duration(seconds: 20));
      final decoded = jsonDecode(await response.transform(utf8.decoder).join().timeout(const Duration(seconds: 20))) as Map<String, dynamic>;
      if (response.statusCode < 200 || response.statusCode >= 300) throw HttpException(decoded['message']?.toString() ?? 'Request failed.');
      return decoded;
    } finally { client.close(force: true); }
  }

  static Future<Uint8List> downloadRiderDocument(String path) async {
    if (!path.startsWith('/logistics/riders/') &&
        !path.startsWith('/logistics/applications/')) {
      throw const FormatException('Invalid rider document address.');
    }

    final client = HttpClient()..connectionTimeout = const Duration(seconds: 20);
    try {
      final request = await client
          .getUrl(Uri.parse('${baseUrl.replaceFirst(RegExp(r'/$'), '')}$path'))
          .timeout(const Duration(seconds: 20));
      request.headers.set('Accept', '*/*');
      if (_token != null) request.headers.set('Authorization', 'Bearer $_token');

      final response = await request.close().timeout(const Duration(seconds: 20));
      final bytes = await response.fold<BytesBuilder>(
        BytesBuilder(),
        (builder, chunk) => builder..add(chunk),
      );
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw HttpException('Could not download rider document (${response.statusCode}).');
      }
      return bytes.takeBytes();
    } finally {
      client.close(force: true);
    }
  }

  static Future<void> login(String email, String password) async {
    _token = null;
    final result = await request('POST', '/auth/login', {'email': email, 'password': password, 'role': 'logistics'});
    _token = result['token'] as String;
  }

  static Future<void> loginWithGoogle(String idToken) async {
    _token = null;
    final result = await request('POST', '/auth/google', {'id_token': idToken});
    final token = result['token']?.toString();
    if (token == null || token.isEmpty || result['role'] != 'logistics') {
      throw const HttpException('The server returned an invalid Logistics session.');
    }
    _token = token;
  }

  static Future<void> sendPasswordResetCode(String email) async {
    await request('POST', '/auth/forgot-password/send-code', {
      'email': email.trim().toLowerCase(),
    });
  }

  static Future<String> verifyPasswordResetCode({
    required String email,
    required String code,
  }) async {
    final result = await request('POST', '/auth/forgot-password/verify-code', {
      'email': email.trim().toLowerCase(),
      'code': code,
    });
    final resetToken = result['reset_token']?.toString();
    if (resetToken == null || resetToken.isEmpty) {
      throw const HttpException(
        'The server did not return a password reset session.',
      );
    }
    return resetToken;
  }

  static Future<void> resetPassword({
    required String email,
    required String resetToken,
    required String password,
  }) async {
    await request('POST', '/auth/forgot-password/reset', {
      'email': email.trim().toLowerCase(),
      'reset_token': resetToken,
      'password': password,
      'password_confirmation': password,
    });
  }

  static Future<void> logout() async {
    try { await request('POST', '/auth/logout', {}); }
    finally { _token = null; }
  }
}

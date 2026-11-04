import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../models/rider_profile.dart';

class RiderApiException implements Exception {
  const RiderApiException(this.message);

  final String message;

  @override
  String toString() => message;
}

/// The Rider app's bridge to the same Laravel database used by the web portal.
abstract final class RiderApiService {
  static const String _tokenKey = 'lumear_rider_api_token';
  static const String _baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://lumearofficial.com/api',
  );

  static String? _token;

  static Future<void> restoreSession() async {
    _token ??= (await SharedPreferences.getInstance()).getString(_tokenKey);
  }

  static Future<void> login({
    required String email,
    required String password,
  }) async {
    final response = await http.post(
      _uri('/rider/auth/login'),
      headers: _jsonHeaders,
      body: jsonEncode({'email': email, 'password': password}),
    );
    final body = _decode(response);
    if (response.statusCode != 200) throw RiderApiException(_message(body));

    final token = body['token']?.toString();
    final rider = body['rider'];
    if (token == null || token.isEmpty || rider is! Map<String, dynamic>) {
      throw const RiderApiException('The server returned an invalid rider session.');
    }

    _token = token;
    await (await SharedPreferences.getInstance()).setString(_tokenKey, token);
    RiderProfile.setFromJson(rider);
  }

  static Future<void> register({
    required Map<String, String> values,
    required File driverLicense,
    required File orDocument,
    required File crDocument,
  }) async {
    final request = http.MultipartRequest('POST', _uri('/rider/auth/register'))
      ..fields.addAll(values)
      ..files.add(await http.MultipartFile.fromPath('driver_license', driverLicense.path))
      ..files.add(await http.MultipartFile.fromPath('or_document', orDocument.path))
      ..files.add(await http.MultipartFile.fromPath('cr_document', crDocument.path));
    final response = await http.Response.fromStream(await request.send());
    final body = _decode(response);
    if (response.statusCode != 201) throw RiderApiException(_message(body));
  }

  static Future<void> refreshProfile() async {
    final body = await _authenticated('GET', '/rider/me');
    final rider = body['rider'];
    if (rider is Map<String, dynamic>) RiderProfile.setFromJson(rider);
  }

  static Future<void> updateAvailability(bool available) async {
    await _authenticated('PATCH', '/rider/availability', body: {'available': available});
    RiderProfile.isAvailable = available;
    RiderProfile.status = available ? 'Available' : 'Offline';
  }

  static Future<List<Map<String, dynamic>>> shipments() async {
    final body = await _authenticated('GET', '/rider/shipments');
    final rows = body['shipments'];
    if (rows is! List) return [];
    return rows.whereType<Map<String, dynamic>>().toList();
  }

  static Future<void> updateShipmentStatus(
    int shipmentId,
    String status, {
    String? note,
  }) async {
    await _authenticated(
      'PATCH',
      '/rider/shipments/$shipmentId/status',
      body: {'status': status, if (note != null) 'note': note},
    );
  }

  static Future<void> logout() async {
    final token = _token;
    _token = null;
    await (await SharedPreferences.getInstance()).remove(_tokenKey);
    try {
      if (token != null) {
        await http.post(
          _uri('/rider/auth/logout'),
          headers: {..._jsonHeaders, 'Authorization': 'Bearer $token'},
        );
      }
    } catch (_) {
      // Local session removal must still work when the device is offline.
    }
    RiderProfile.clear();
  }

  static Future<Map<String, dynamic>> _authenticated(
    String method,
    String path, {
    Map<String, dynamic>? body,
  }) async {
    await restoreSession();
    if (_token == null) throw const RiderApiException('Please sign in again.');
    final request = http.Request(method, _uri(path))
      ..headers.addAll({..._jsonHeaders, 'Authorization': 'Bearer $_token'});
    if (body != null) request.body = jsonEncode(body);
    final response = await http.Response.fromStream(await request.send());
    final decoded = _decode(response);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      if (response.statusCode == 401) await logout();
      throw RiderApiException(_message(decoded));
    }
    return decoded;
  }

  static Uri _uri(String path) => Uri.parse(
    '${_baseUrl.replaceFirst(RegExp(r'/$'), '')}$path',
  );

  static const Map<String, String> _jsonHeaders = {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  };

  static Map<String, dynamic> _decode(http.Response response) {
    try {
      final decoded = jsonDecode(response.body);
      return decoded is Map<String, dynamic> ? decoded : <String, dynamic>{};
    } catch (_) {
      return <String, dynamic>{};
    }
  }

  static String _message(Map<String, dynamic> body) {
    final errors = body['errors'];
    if (errors is Map && errors.isNotEmpty) {
      final first = errors.values.first;
      if (first is List && first.isNotEmpty) return first.first.toString();
    }
    return body['message']?.toString() ?? 'The request could not be completed.';
  }
}

import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:http/http.dart' as http;
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
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
      throw const RiderApiException(
        'The server returned an invalid rider session.',
      );
    }

    _token = token;
    await (await SharedPreferences.getInstance()).setString(_tokenKey, token);
    RiderProfile.setFromJson(rider);
  }

  static Future<void> loginAdmin({required String email, required String password}) async {
    final response = await http.post(
      _uri('/rider/admin/login'),
      headers: _jsonHeaders,
      body: jsonEncode({'email': email, 'password': password}),
    );
    final body = _decode(response);
    if (response.statusCode != 200) throw RiderApiException(_message(body));
    _token = body['token']?.toString();
    if (_token == null || _token!.isEmpty) throw const RiderApiException('The server returned an invalid admin session.');
    await (await SharedPreferences.getInstance()).setString(_tokenKey, _token!);
  }

  static Future<void> logoutAdmin() async {
    await restoreSession();
    if (_token != null) {
      try {
        await http.post(_uri('/auth/logout'), headers: {..._jsonHeaders, 'Authorization': 'Bearer $_token'});
      } catch (_) {}
    }
    _token = null;
    await (await SharedPreferences.getInstance()).remove(_tokenKey);
  }

  static Future<List<Map<String, dynamic>>> adminRiders() async {
    final body = await _authenticated('GET', '/rider/admin/riders');
    return (body['riders'] as List? ?? []).whereType<Map<String, dynamic>>().toList();
  }

  static Future<List<Map<String, dynamic>>> adminBugReports() async {
    final body = await _authenticated('GET', '/rider/admin/bug-reports');
    return (body['reports'] as List? ?? []).whereType<Map<String, dynamic>>().map((report) {
      final copy = Map<String, dynamic>.from(report);
      copy['evidencePaths'] = (copy['evidencePaths'] as List? ?? []).map((path) => Uri.parse('${_baseUrl.replaceFirst(RegExp(r'/$'), '')}${path.toString()}').toString()).toList();
      return copy;
    }).toList();
  }

  static Future<void> updateAdminBugReport({required String reportId, required String status, required String response}) async {
    final numericId = reportId.replaceFirst('BUG-', '');
    await _authenticated('PATCH', '/rider/admin/bug-reports/$numericId', body: {'status': status, 'admin_response': response});
  }

  static Future<Map<String, dynamic>> submitBugReport({required Map<String, String> fields, required List<String> evidencePaths}) async {
    await restoreSession();
    if (_token == null) throw const RiderApiException('Please sign in again.');
    final request = http.MultipartRequest('POST', _uri('/rider/bug-reports'))
      ..headers.addAll({'Accept': 'application/json', 'Authorization': 'Bearer $_token'})
      ..fields.addAll(fields);
    for (int index = 0; index < evidencePaths.length; index++) {
      request.files.add(await http.MultipartFile.fromPath('evidence[$index]', evidencePaths[index]));
    }
    final response = await http.Response.fromStream(await request.send());
    final body = _decode(response);
    if (response.statusCode != 201) throw RiderApiException(_message(body));
    return body;
  }

  static Map<String, String> get authenticatedHeaders {
    final token = _token;
    return token == null ? const {} : {'Authorization': 'Bearer $token'};
  }

  static Future<Map<String, dynamic>> loginWithGoogle({
    required String idToken,
  }) async {
    final response = await http.post(
      _uri('/rider/auth/google'),
      headers: _jsonHeaders,
      body: jsonEncode({'id_token': idToken}),
    );
    final body = _decode(response);
    if (response.statusCode != 200) {
      throw RiderApiException(_message(body));
    }

    if (body['action'] == 'signup' || body['action'] == 'pending') {
      return body;
    }

    final token = body['token']?.toString();
    final rider = body['rider'];
    if (token == null || token.isEmpty || rider is! Map<String, dynamic>) {
      throw const RiderApiException(
        'The server returned an invalid rider session.',
      );
    }

    _token = token;
    await (await SharedPreferences.getInstance()).setString(_tokenKey, token);
    RiderProfile.setFromJson(rider);
    return body;
  }

  static Future<void> register({
    required Map<String, String> values,
    required File driverLicense,
    required File orDocument,
    required File crDocument,
  }) async {
    final request = http.MultipartRequest('POST', _uri('/rider/auth/register'))
      ..fields.addAll(values)
      ..files.add(
        await http.MultipartFile.fromPath('driver_license', driverLicense.path),
      )
      ..files.add(
        await http.MultipartFile.fromPath('or_document', orDocument.path),
      )
      ..files.add(
        await http.MultipartFile.fromPath('cr_document', crDocument.path),
      );
    final response = await http.Response.fromStream(await request.send());
    final body = _decode(response);
    if (response.statusCode != 201) throw RiderApiException(_message(body));
  }

  static Future<List<Map<String, dynamic>>> logisticsHubs() async {
    final response = await http.get(
      _uri('/rider/logistics-hubs'),
      headers: const {'Accept': 'application/json'},
    );
    final body = _decode(response);
    if (response.statusCode != 200) {
      throw RiderApiException(_message(body));
    }

    final hubs = body['logistics_hubs'];
    if (hubs is! List) return [];
    return hubs.whereType<Map<String, dynamic>>().toList();
  }

  static Future<void> refreshProfile() async {
    final body = await _authenticated('GET', '/rider/me');
    final rider = body['rider'];
    if (rider is Map<String, dynamic>) RiderProfile.setFromJson(rider);
  }

  static Future<void> updateProfile(Map<String, dynamic> values) async {
    final body = await _authenticated('PATCH', '/rider/me', body: values);
    final rider = body['rider'];
    if (rider is Map<String, dynamic>) RiderProfile.setFromJson(rider);
  }

  static Future<File?> downloadDocument(String type) async {
    const documentTypes = {'driver_license', 'or_document', 'cr_document'};
    if (!documentTypes.contains(type)) {
      throw const RiderApiException('Unknown rider document type.');
    }

    await restoreSession();
    if (_token == null) throw const RiderApiException('Please sign in again.');

    final response = await http.get(
      _uri('/rider/documents/$type'),
      headers: {'Authorization': 'Bearer $_token', 'Accept': '*/*'},
    );
    if (response.statusCode == 404) return null;
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw RiderApiException(_message(_decode(response)));
    }

    final contentType = response.headers['content-type']?.toLowerCase() ?? '';
    final disposition = response.headers['content-disposition'] ?? '';
    final filename = RegExp(
      r'filename="?([^";]+)',
      caseSensitive: false,
    ).firstMatch(disposition)?.group(1);
    final filenameExtension = filename == null
        ? ''
        : p.extension(Uri.decodeComponent(filename.trim())).toLowerCase();
    final extension =
        const {
          '.jpg',
          '.jpeg',
          '.png',
          '.pdf',
          '.doc',
          '.docx',
        }.contains(filenameExtension)
        ? filenameExtension
        : contentType.contains('pdf')
        ? '.pdf'
        : contentType.contains('wordprocessingml')
        ? '.docx'
        : contentType.contains('msword')
        ? '.doc'
        : contentType.contains('png')
        ? '.png'
        : '.jpg';
    final directory = await getApplicationDocumentsDirectory();
    final folder = Directory(p.join(directory.path, 'rider_documents'));
    await folder.create(recursive: true);
    final safeEmail = RiderProfile.email.toLowerCase().replaceAll(
      RegExp(r'[^a-z0-9]'),
      '_',
    );
    final file = File(p.join(folder.path, '${safeEmail}_$type$extension'));
    await file.writeAsBytes(response.bodyBytes, flush: true);
    return file;
  }

  static Future<void> updateDocuments({
    File? driverLicense,
    File? orDocument,
    File? crDocument,
    DateTime? licenseExpiry,
  }) async {
    await restoreSession();
    if (_token == null) throw const RiderApiException('Please sign in again.');

    final request = http.MultipartRequest('POST', _uri('/rider/documents'))
      ..headers.addAll({
        'Authorization': 'Bearer $_token',
        'Accept': 'application/json',
      });
    if (driverLicense != null) {
      request.files.add(
        await http.MultipartFile.fromPath('driver_license', driverLicense.path),
      );
    }
    if (orDocument != null) {
      request.files.add(
        await http.MultipartFile.fromPath('or_document', orDocument.path),
      );
    }
    if (crDocument != null) {
      request.files.add(
        await http.MultipartFile.fromPath('cr_document', crDocument.path),
      );
    }
    if (licenseExpiry != null) {
      request.fields['license_expiry'] =
          '${licenseExpiry.year.toString().padLeft(4, '0')}-'
          '${licenseExpiry.month.toString().padLeft(2, '0')}-'
          '${licenseExpiry.day.toString().padLeft(2, '0')}';
    }

    final response = await http.Response.fromStream(await request.send());
    final body = _decode(response);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw RiderApiException(_message(body));
    }
    final rider = body['rider'];
    if (rider is Map<String, dynamic>) RiderProfile.setFromJson(rider);
  }

  static Future<void> updateAvailability(bool available) async {
    await _authenticated(
      'PATCH',
      '/rider/availability',
      body: {'available': available},
    );
    RiderProfile.isAvailable = available;
    RiderProfile.status = available ? 'Available' : 'Offline';
  }

  static Future<void> updatePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    await _authenticated(
      'PATCH',
      '/rider/password',
      body: {
        'current_password': currentPassword,
        'password': newPassword,
        'password_confirmation': newPassword,
      },
    );
  }

  static Future<void> sendRiderPasswordResetCode(String email) async {
    await _publicPost('/rider/auth/forgot-password/send-code', {
      'email': email.trim().toLowerCase(),
    });
  }

  static Future<String> verifyRiderPasswordResetCode({
    required String email,
    required String code,
  }) async {
    final body = await _publicPost('/rider/auth/forgot-password/verify-code', {
      'email': email.trim().toLowerCase(),
      'code': code,
    });
    final token = body['reset_token']?.toString();
    if (token == null || token.isEmpty) {
      throw const RiderApiException(
        'The server did not return a reset session.',
      );
    }
    return token;
  }

  static Future<void> resetRiderPassword({
    required String email,
    required String resetToken,
    required String password,
  }) async {
    await _publicPost('/rider/auth/forgot-password/reset', {
      'email': email.trim().toLowerCase(),
      'reset_token': resetToken,
      'password': password,
      'password_confirmation': password,
    });
  }

  static Future<Map<String, dynamic>> _publicPost(
    String path,
    Map<String, dynamic> values,
  ) async {
    final response = await http.post(
      _uri(path),
      headers: _jsonHeaders,
      body: jsonEncode(values),
    );
    final body = _decode(response);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw RiderApiException(_message(body));
    }
    return body;
  }

  static Future<List<Map<String, dynamic>>> shipments() async {
    final body = await _authenticated('GET', '/rider/shipments');
    final rows = body['shipments'];
    if (rows is! List) return [];
    return rows.whereType<Map<String, dynamic>>().toList();
  }

  static String apiUrl(String path) => _uri(path).toString();

  static Future<void> uploadShipmentProof({required int shipmentId, required String imagePath, required String receivedBy}) async {
    await restoreSession();
    if (_token == null) throw const RiderApiException('Please sign in again.');
    final request = http.MultipartRequest('POST', _uri('/rider/shipments/$shipmentId/proof'))
      ..headers.addAll({'Accept': 'application/json', 'Authorization': 'Bearer $_token'})
      ..fields['received_by'] = receivedBy
      ..files.add(await http.MultipartFile.fromPath('proof', imagePath));
    final response = await http.Response.fromStream(await request.send());
    final body = _decode(response);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw RiderApiException(_message(body));
    }
  }

  static Future<Map<String, dynamic>> payroll() async {
    return _authenticated('GET', '/rider/payroll');
  }

  static Future<void> syncPayrollDeliveries() async {
    await _authenticated('POST', '/rider/payroll/sync-deliveries');
  }

  static Future<void> confirmShipmentPickupScan(
    int shipmentId,
    String waybill,
  ) async {
    await _authenticated(
      'POST',
      '/rider/shipments/$shipmentId/pickup-scan',
      body: {'waybill': waybill},
    );
  }

  static Future<List<Map<String, dynamic>>> riderChats() async {
    final body = await _authenticated('GET', '/rider/chats');
    final rows = body['chats'];
    if (rows is! List) return [];
    return rows.whereType<Map<String, dynamic>>().toList();
  }

  static Future<Map<String, dynamic>> logisticsChat() async {
    final body = await _authenticated('GET', '/rider/logistics-chat');
    final chat = body['chat'];
    return chat is Map<String, dynamic> ? chat : <String, dynamic>{};
  }

  static Future<List<Map<String, dynamic>>> logisticsChatMessages() async {
    final body = await _authenticated('GET', '/rider/logistics-chat/messages');
    final rows = body['messages'];
    if (rows is! List) return [];
    return rows.whereType<Map<String, dynamic>>().toList();
  }

  static Future<void> sendLogisticsChatMessage(String text) async {
    await _authenticated(
      'POST',
      '/rider/logistics-chat/messages',
      body: {'text': text},
    );
  }

  static Future<List<Map<String, dynamic>>> riderChatMessages(
    int conversationId,
  ) async {
    final body = await _authenticated(
      'GET',
      '/rider/chats/$conversationId/messages',
    );
    final rows = body['messages'];
    if (rows is! List) return [];
    return rows.whereType<Map<String, dynamic>>().toList();
  }

  static Future<void> sendRiderChatMessage(
    int conversationId,
    String text,
  ) async {
    await _authenticated(
      'POST',
      '/rider/chats/$conversationId/messages',
      body: {'text': text},
    );
  }

  static Future<String> lumearAiChat({
    required String question,
    required List<Map<String, String>> history,
    String? attachmentPath,
  }) async {
    await restoreSession();
    if (_token == null) throw const RiderApiException('Please sign in again.');

    final request = http.MultipartRequest('POST', _uri('/rider/ai/chat'))
      ..headers.addAll({
        'Accept': 'application/json',
        'Authorization': 'Bearer $_token',
      })
      ..fields['question'] = question
      ..fields['history'] = jsonEncode(history);

    if (attachmentPath != null) {
      request.files.add(
        await http.MultipartFile.fromPath('attachment', attachmentPath),
      );
    }

    final streamedResponse = await request.send().timeout(
      const Duration(seconds: 55),
    );
    final response = await http.Response.fromStream(streamedResponse);
    final body = _decode(response);
    if (response.statusCode < 200 || response.statusCode >= 300) {
      if (response.statusCode == 401) await logout();
      throw RiderApiException(_message(body));
    }

    final answer = body['answer']?.toString().trim() ?? '';
    if (answer.isEmpty) {
      throw const RiderApiException('Lumear AI returned an empty response.');
    }

    return answer;
  }

  static Future<void> deleteRiderChat(int conversationId) async {
    await _authenticated('DELETE', '/rider/chats/$conversationId');
  }

  static Future<Map<String, dynamic>> codRemittances() async {
    return _authenticated('GET', '/rider/remittances');
  }

  static Future<void> syncCodRemittances() async {
    await _authenticated('POST', '/rider/remittances/sync');
  }

  static Future<List<Map<String, dynamic>>> riderNotifications() async {
    final body = await _authenticated('GET', '/rider/notifications');
    final rows = body['notifications'];
    if (rows is! List) return [];
    return rows.whereType<Map<String, dynamic>>().toList();
  }

  static Future<Map<String, dynamic>> submitCodRemittance({
    required int amountMinor,
  }) async {
    return _authenticated(
      'POST',
      '/rider/remittances',
      body: {'amount_minor': amountMinor},
    );
  }

  static Future<Uint8List> downloadRemittanceReceipt(int remittanceId) async {
    await restoreSession();
    if (_token == null) throw const RiderApiException('Please sign in again.');
    final response = await http.get(
      _uri('/rider/remittances/$remittanceId/receipt'),
      headers: {'Authorization': 'Bearer $_token', 'Accept': '*/*'},
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw RiderApiException(_message(_decode(response)));
    }
    return response.bodyBytes;
  }

  static Future<List<Map<String, dynamic>>> tasks() async {
    final body = await _authenticated('GET', '/rider/tasks');
    return (body['tasks'] as List).whereType<Map<String, dynamic>>().toList();
  }

  static Future<void> updateTask(int id, String status, {String? note}) async {
    await _authenticated(
      'PATCH',
      '/rider/tasks/$id',
      body: {'status': status, 'note': ?note},
    );
  }

  static Future<Map<String, dynamic>> updateShipmentStatus(
    int shipmentId,
    String status, {
    String? note,
  }) async {
    return _authenticated(
      'PATCH',
      '/rider/shipments/$shipmentId/status',
      body: {'status': status, 'note': ?note},
    );
  }

  static Future<void> reportShipmentDelay(
    int shipmentId, {
    required String reason,
    required DateTime proposedFor,
  }) async {
    await _authenticated(
      'POST',
      '/rider/shipments/$shipmentId/delay-report',
      body: {'reason': reason, 'proposed_for': proposedFor.toIso8601String()},
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

  static Uri _uri(String path) =>
      Uri.parse('${_baseUrl.replaceFirst(RegExp(r'/$'), '')}$path');

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

import 'package:flutter/foundation.dart';

import '../models/bug_report.dart';
import '../models/rider_profile.dart';
import 'rider_api_service.dart';

class BugReportService extends ChangeNotifier {
  BugReportService._();

  static final BugReportService instance = BugReportService._();
  List<BugReport> _reports = [];
  List<BugReport> get reports => List.unmodifiable(_reports);

  Future<void> refresh() async {
    final values = await RiderApiService.adminBugReports();
    _reports = values.map(BugReport.fromJson).toList();
    notifyListeners();
  }

  Future<BugReport> submitReport({
    required String affectedScreen,
    required String appVersion,
    required String deviceInfo,
    required String description,
    required String expectedResult,
    required List<String> evidencePaths,
  }) async {
    final response = await RiderApiService.submitBugReport(
      fields: {
        'affected_screen': affectedScreen,
        'app_version': appVersion,
        'device_info': deviceInfo,
        'description': description,
        'expected_result': expectedResult,
      },
      evidencePaths: evidencePaths,
    );
    return BugReport(
      id: response['id']?.toString() ?? '',
      riderId: RiderProfile.riderId,
      riderName: RiderProfile.name,
      riderEmail: RiderProfile.email,
      affectedScreen: affectedScreen,
      appVersion: appVersion,
      deviceInfo: deviceInfo,
      description: description,
      expectedResult: expectedResult,
      evidencePaths: List<String>.from(evidencePaths),
      reportedAt: DateTime.now(),
    );
  }

  Future<void> updateStatus({required String reportId, required String status, required String response}) async {
    await RiderApiService.updateAdminBugReport(reportId: reportId, status: status, response: response);
    await refresh();
  }
}

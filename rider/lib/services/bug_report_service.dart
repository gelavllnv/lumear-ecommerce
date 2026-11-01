import 'package:flutter/material.dart';

import '../models/bug_report.dart';

class BugReportService extends ChangeNotifier {
  BugReportService._();

  static final BugReportService instance = BugReportService._();

  final List<BugReport> _reports = [];

  List<BugReport> get reports => List.unmodifiable(_reports.reversed);

  BugReport submitReport({
    required String riderId,
    required String riderName,
    required String riderEmail,
    required String affectedScreen,
    required String appVersion,
    required String deviceInfo,
    required String description,
    required String expectedResult,
    required List<String> evidencePaths,
  }) {
    final DateTime now = DateTime.now();

    final String bugId = _generateBugId(now);

    final BugReport report = BugReport(
      id: bugId,
      riderId: riderId,
      riderName: riderName,
      riderEmail: riderEmail,
      affectedScreen: affectedScreen,
      appVersion: appVersion,
      deviceInfo: deviceInfo,
      description: description,
      expectedResult: expectedResult,
      evidencePaths: List<String>.from(evidencePaths),
      reportedAt: now,
      status: 'Pending',
    );

    _reports.add(report);

    notifyListeners();

    return report;
  }

  void updateStatus({required String reportId, required String status}) {
    final BugReport? report = _findReport(reportId);

    if (report == null) {
      return;
    }

    report.status = status;

    notifyListeners();
  }

  void updateAdminResponse({
    required String reportId,
    required String response,
  }) {
    final BugReport? report = _findReport(reportId);

    if (report == null) {
      return;
    }

    report.adminResponse = response.trim();

    notifyListeners();
  }

  BugReport? _findReport(String id) {
    for (final BugReport report in _reports) {
      if (report.id == id) {
        return report;
      }
    }

    return null;
  }

  String _generateBugId(DateTime dateTime) {
    final String year = dateTime.year.toString();

    final String number = (_reports.length + 1).toString().padLeft(4, '0');

    return 'BUG-$year-$number';
  }
}

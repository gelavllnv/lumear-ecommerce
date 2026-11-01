class BugReport {
  final String id;
  final String riderId;
  final String riderName;
  final String riderEmail;
  final String affectedScreen;
  final String appVersion;
  final String deviceInfo;
  final String description;
  final String expectedResult;
  final List<String> evidencePaths;
  final DateTime reportedAt;

  String status;
  String adminResponse;

  BugReport({
    required this.id,
    required this.riderId,
    required this.riderName,
    required this.riderEmail,
    required this.affectedScreen,
    required this.appVersion,
    required this.deviceInfo,
    required this.description,
    required this.expectedResult,
    required this.evidencePaths,
    required this.reportedAt,
    this.status = 'Pending',
    this.adminResponse = '',
  });
}

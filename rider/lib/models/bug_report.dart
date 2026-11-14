class BugReport {
  final String id;
  final String riderId;
  final String riderName;
  final String riderEmail;
  final String riderPhone;
  final String vehicle;
  final String jobType;
  final String municipality;
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
    this.riderPhone = '',
    this.vehicle = '',
    this.jobType = '',
    this.municipality = '',
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

  factory BugReport.fromJson(Map<String, dynamic> json) => BugReport(
    id: json['id']?.toString() ?? '',
    riderId: json['riderId']?.toString() ?? '',
    riderName: json['riderName']?.toString() ?? '',
    riderEmail: json['riderEmail']?.toString() ?? '',
    riderPhone: json['riderPhone']?.toString() ?? '',
    vehicle: json['vehicle']?.toString() ?? '',
    jobType: json['jobType']?.toString() ?? '',
    municipality: json['municipality']?.toString() ?? '',
    affectedScreen: json['affectedScreen']?.toString() ?? '',
    appVersion: json['appVersion']?.toString() ?? '',
    deviceInfo: json['deviceInfo']?.toString() ?? '',
    description: json['description']?.toString() ?? '',
    expectedResult: json['expectedResult']?.toString() ?? '',
    evidencePaths: (json['evidencePaths'] as List? ?? []).map((e) => e.toString()).toList(),
    reportedAt: DateTime.tryParse(json['reportedAt']?.toString() ?? '')?.toLocal() ?? DateTime.now(),
    status: json['status']?.toString() ?? 'Pending',
    adminResponse: json['adminResponse']?.toString() ?? '',
  );
}

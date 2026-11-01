
class TruckRiderProfile {
  // ------------------------------------------------------------
  // RIDER IDENTIFICATION
  // ------------------------------------------------------------

  final String riderId;

  // ------------------------------------------------------------
  // PERSONAL INFORMATION
  // ------------------------------------------------------------

  final String fullName;
  final String email;
  final String phone;
  final DateTime? birthday;
  final int age;

  // ------------------------------------------------------------
  // ADDRESS
  // ------------------------------------------------------------

  final String region;
  final String province;
  final String municipality;
  final String barangay;
  final String street;
  final String houseUnit;

  // ------------------------------------------------------------
  // VEHICLE INFORMATION
  // ------------------------------------------------------------

  final String vehicleType;
  final String licenseNumber;
  final String plateNumber;
  final String accountStatus;

  // ------------------------------------------------------------
  // DOCUMENT FILE PATHS
  // ------------------------------------------------------------

  /// Driver's license image path (PNG/JPG/JPEG).
  final String? licenseImagePath;

  /// Official Receipt path (PDF/DOC/DOCX).
  final String? orImagePath;

  /// Certificate of Registration path (PDF/DOC/DOCX).
  final String? crImagePath;

  // ------------------------------------------------------------
  // CONSTRUCTOR
  // ------------------------------------------------------------

  const TruckRiderProfile({
    this.riderId = '',
    this.fullName = '',
    this.email = '',
    this.phone = '',
    this.birthday,
    this.age = 0,
    this.region = '',
    this.province = '',
    this.municipality = '',
    this.barangay = '',
    this.street = '',
    this.houseUnit = '',
    this.vehicleType = '',
    this.licenseNumber = '',
    this.plateNumber = '',
    this.accountStatus = 'Pending',
    this.licenseImagePath,
    this.orImagePath,
    this.crImagePath,
  });

  // ------------------------------------------------------------
  // FULL ADDRESS
  // ------------------------------------------------------------

  String get fullAddress {
    final parts = <String>[
      houseUnit,
      street,
      barangay,
      municipality,
      province,
      region,
    ].where((part) => part.trim().isNotEmpty).toList();

    return parts.isEmpty ? '' : parts.join(', ');
  }

  // ------------------------------------------------------------
  // COPY WITH
  // ------------------------------------------------------------

  /// Creates an updated profile while keeping unchanged fields.
  ///
  /// For nullable fields, use clearLicenseImagePath, clearOrImagePath,
  /// or clearCrImagePath to explicitly remove a stored document path.
  TruckRiderProfile copyWith({
    String? riderId,
    String? fullName,
    String? email,
    String? phone,
    DateTime? birthday,
    bool clearBirthday = false,
    int? age,
    String? region,
    String? province,
    String? municipality,
    String? barangay,
    String? street,
    String? houseUnit,
    String? vehicleType,
    String? licenseNumber,
    String? plateNumber,
    String? accountStatus,
    String? licenseImagePath,
    bool clearLicenseImagePath = false,
    String? orImagePath,
    bool clearOrImagePath = false,
    String? crImagePath,
    bool clearCrImagePath = false,
  }) {
    return TruckRiderProfile(
      riderId: riderId ?? this.riderId,
      fullName: fullName ?? this.fullName,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      birthday: clearBirthday ? null : (birthday ?? this.birthday),
      age: age ?? this.age,
      region: region ?? this.region,
      province: province ?? this.province,
      municipality: municipality ?? this.municipality,
      barangay: barangay ?? this.barangay,
      street: street ?? this.street,
      houseUnit: houseUnit ?? this.houseUnit,
      vehicleType: vehicleType ?? this.vehicleType,
      licenseNumber: licenseNumber ?? this.licenseNumber,
      plateNumber: plateNumber ?? this.plateNumber,
      accountStatus: accountStatus ?? this.accountStatus,
      licenseImagePath: clearLicenseImagePath
          ? null
          : (licenseImagePath ?? this.licenseImagePath),
      orImagePath: clearOrImagePath
          ? null
          : (orImagePath ?? this.orImagePath),
      crImagePath: clearCrImagePath
          ? null
          : (crImagePath ?? this.crImagePath),
    );
  }

  // ------------------------------------------------------------
  // MAP SERIALIZATION
  // ------------------------------------------------------------

  Map<String, dynamic> toMap() {
    return {
      'riderId': riderId,
      'fullName': fullName,
      'email': email,
      'phone': phone,
      'birthday': birthday?.toIso8601String(),
      'age': age,
      'region': region,
      'province': province,
      'municipality': municipality,
      'barangay': barangay,
      'street': street,
      'houseUnit': houseUnit,
      'vehicleType': vehicleType,
      'licenseNumber': licenseNumber,
      'plateNumber': plateNumber,
      'accountStatus': accountStatus,
      'licenseImagePath': licenseImagePath,
      'orImagePath': orImagePath,
      'crImagePath': crImagePath,
    };
  }

  factory TruckRiderProfile.fromMap(Map<String, dynamic> map) {
    return TruckRiderProfile(
      riderId: map['riderId']?.toString() ?? '',
      fullName: map['fullName']?.toString() ?? '',
      email: map['email']?.toString() ?? '',
      phone: map['phone']?.toString() ?? '',
      birthday: map['birthday'] == null ||
              map['birthday'].toString().isEmpty
          ? null
          : DateTime.tryParse(map['birthday'].toString()),
      age: int.tryParse(map['age']?.toString() ?? '') ?? 0,
      region: map['region']?.toString() ?? '',
      province: map['province']?.toString() ?? '',
      municipality: map['municipality']?.toString() ?? '',
      barangay: map['barangay']?.toString() ?? '',
      street: map['street']?.toString() ?? '',
      houseUnit: map['houseUnit']?.toString() ?? '',
      vehicleType: map['vehicleType']?.toString() ?? '',
      licenseNumber: map['licenseNumber']?.toString() ?? '',
      plateNumber: map['plateNumber']?.toString() ?? '',
      accountStatus: map['accountStatus']?.toString() ?? 'Pending',
      licenseImagePath: map['licenseImagePath']?.toString(),
      orImagePath: map['orImagePath']?.toString(),
      crImagePath: map['crImagePath']?.toString(),
    );
  }
}
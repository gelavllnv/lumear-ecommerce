class HardcodedRider {
  // ============================================================
  // ACCOUNT / SYSTEM INFORMATION
  // ============================================================

  static const String riderId = 'RIDER-001';

  static const String role = 'Rider';

  static const String accountStatus = 'Approved';

  // ============================================================
  // PERSONAL INFORMATION
  // Same information requested in Sign Up
  // ============================================================

  static const String fullName = 'Earl Chumacera';

  static const String firstName = 'Earl';

  static const String email = 'rider@lumear.com';

  // Sign Up already displays +63 separately,
  // so this is the local mobile number.
  static const String phone = '9123456789';

  static const String birthday = '07/18/05';

  static const int age = 21;

  // ============================================================
  // RESIDENTIAL ADDRESS
  // ============================================================

  static const String region = 'Region XI (Davao Region)';

  static const String province = 'Davao de Oro';

  static const String municipality = 'Mabini';

  static const String barangay = 'Poblacion';

  static const String street = 'Sample Street';

  static const String houseUnit = 'House 123';

  // ============================================================
  // ACCOUNT SECURITY
  // ============================================================

  static const String password = 'rider123';

  // ============================================================
  // RIDER & VEHICLE INFORMATION
  // ============================================================

  static const String vehicleType = 'Motorcycle';

  static const String licenseNumber = 'N01-23-456789';

  static const String plateNumber = 'ABC 1234';

  // ============================================================
  // DOCUMENT VERIFICATION
  // Demo assets muna habang hardcoded
  // ============================================================

  static const String licenseImage = 'assets/images/sample_driver_license.png';

  static const String orCrImage = 'assets/images/sample_orcr.png';

  // ============================================================
  // LOGIN VALIDATION
  // ============================================================

  static bool validateLogin({required String email, required String password}) {
    return email.trim().toLowerCase() == HardcodedRider.email.toLowerCase() &&
        password == HardcodedRider.password;
  }

  // ============================================================
  // COMPLETE ADDRESS
  // ============================================================

  static String get completeAddress {
    return '$houseUnit, $street, $barangay, '
        '$municipality, $province, $region';
  }

  // ============================================================
  // COMPLETE PHONE NUMBER
  // ============================================================

  static String get fullPhoneNumber {
    return '+63$phone';
  }

  // ============================================================
  // RIDER DATA
  // Useful later for Profile / Dashboard
  // ============================================================

  static Map<String, dynamic> get riderData {
    return {
      // Account
      'riderId': riderId,
      'role': role,
      'accountStatus': accountStatus,

      // Personal
      'fullName': fullName,
      'firstName': firstName,
      'email': email,
      'phone': fullPhoneNumber,
      'birthday': birthday,
      'age': age,

      // Address
      'region': region,
      'province': province,
      'municipality': municipality,
      'barangay': barangay,
      'street': street,
      'houseUnit': houseUnit,
      'completeAddress': completeAddress,

      // Rider / Vehicle
      'vehicleType': vehicleType,
      'licenseNumber': licenseNumber,
      'plateNumber': plateNumber,

      // Documents
      'licenseImage': licenseImage,
      'orCrImage': orCrImage,
    };
  }
}

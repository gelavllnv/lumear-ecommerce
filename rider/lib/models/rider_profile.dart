import 'dart:io';

class RiderProfile {
  // Local files selected on this device.
  static File? profileImage;
  static File? localDriverLicenseFile;
  static File? localOrFile;
  static File? localCrFile;

  // Rider identity.
  static int? id;
  static String name = '';
  static String riderId = '';
  static String email = '';
  static String phone = '';
  static String sex = '';
  static String birthday = '';
  static int? age;

  // Rider address.
  static String region = '';
  static String province = '';
  static String municipality = '';
  static String barangay = '';
  static String street = '';
  static String houseUnit = '';

  // Vehicle and work details.
  static String vehicleType = '';
  static String jobType = '';
  static String licenseNumber = '';
  static String plateNumber = '';

  // Documents and account status.
  static String licenseImage = '';
  static String orCrImage = '';
  static String status = '';
  static bool? isAvailable;
  static DateTime? licenseExpiry;

  // Normalize vehicle names from the API or registration form.
  static String get normalizedVehicleType {
    final type = vehicleType.trim().toLowerCase();

    if (type.contains('van')) return 'van';
    if (type.contains('truck')) return 'truck';

    if (type.contains('tricycle') || type.contains('trycicle')) {
      return 'tricycle';
    }

    if (type.contains('motorcycle') || type.contains('motor')) {
      return 'motorcycle';
    }

    return type;
  }

  static String get roleLabel {
    switch (normalizedVehicleType) {
      case 'van':
        return 'Van Driver';
      case 'truck':
        return 'Truck Driver';
      case 'motorcycle':
      case 'tricycle':
        return 'Rider';
      default:
        return 'Driver';
    }
  }

  static String get vehicleLabel {
    switch (normalizedVehicleType) {
      case 'van':
        return 'Van for Delivery';
      case 'truck':
        return 'Truck for Delivery';
      case 'motorcycle':
        return 'Motorcycle for Delivery';
      case 'tricycle':
        return 'Tricycle for Delivery';
      default:
        return vehicleType.trim().isEmpty
            ? 'Vehicle for Delivery'
            : '${vehicleType.trim()} for Delivery';
    }
  }

  static String get idPrefix {
    switch (normalizedVehicleType) {
      case 'van':
        return 'VD';
      case 'truck':
        return 'TD';
      case 'motorcycle':
      case 'tricycle':
      default:
        return 'R';
    }
  }

  static String get displayId {
    if (riderId.trim().isNotEmpty) {
      final existing = riderId.trim();

      if (RegExp(r'^(VD|TD|R)-\d+$', caseSensitive: false).hasMatch(existing)) {
        return existing.toUpperCase();
      }

      final match = RegExp(r'(\d+)$').firstMatch(existing);

      if (match != null) {
        final number = int.parse(match.group(1)!);
        return '$idPrefix-${number.toString().padLeft(3, '0')}';
      }
    }

    if (id != null) {
      return '$idPrefix-${id.toString().padLeft(3, '0')}';
    }

    return '';
  }

  static String get completeAddress {
    return [
      houseUnit,
      street,
      barangay,
      municipality,
      province,
      region,
    ].where((part) => part.trim().isNotEmpty).join(', ');
  }

  // Read the rider information returned by the Node.js API.
  // Supports common alternative field names.
  static void setFromJson(Map<String, dynamic> json) {
    id = _toInt(json['id'] ?? json['rider_id']);

    name = _toString(json['full_name'] ?? json['name']);

    email = _toString(json['email']);

    phone = _toString(
      json['phone'] ?? json['contact_number'] ?? json['contact'],
    );

    birthday = _toString(json['birthday'] ?? json['birthdate']);

    age = _toInt(json['age']);

    region = _toString(json['region']);
    province = _toString(json['province']);

    municipality = _toString(json['municipality'] ?? json['city']);

    barangay = _toString(json['barangay']);
    street = _toString(json['street']);

    houseUnit = _toString(json['house_unit'] ?? json['houseUnit']);

    vehicleType = _toString(json['vehicle_type'] ?? json['vehicleType']);

    jobType = _toString(json['job_type'] ?? json['jobType']);

    licenseNumber = _toString(json['license_number'] ?? json['licenseNumber']);

    plateNumber = _toString(json['plate_number'] ?? json['plateNumber']);

    licenseImage = _toString(json['license_image'] ?? json['licenseImage']);

    orCrImage = _toString(
      json['or_cr_image'] ??
          json['orCrImage'] ??
          json['or_image'] ??
          json['cr_image'],
    );

    // The login API may return approval_status rather than status.
    status = _toString(json['approval_status'] ?? json['status']);

    isAvailable = _toBool(json['is_available'] ?? json['isAvailable']);

    licenseExpiry = _toDateTime(
      json['license_expiry'] ?? json['licenseExpiry'],
    );

    final backendCode =
        json['driver_code'] ?? json['rider_code'] ?? json['rider_number'];

    if (backendCode != null && backendCode.toString().trim().isNotEmpty) {
      riderId = backendCode.toString().trim();
    } else {
      // Keep a supplied rider ID if no code was returned.
      riderId = _toString(json['riderId']);
    }
  }

  // Reset in-memory profile data when logging out.
  static void clear() {
    profileImage = null;
    localDriverLicenseFile = null;
    localOrFile = null;
    localCrFile = null;

    id = null;
    name = '';
    riderId = '';
    email = '';
    phone = '';
    birthday = '';
    age = null;

    region = '';
    province = '';
    municipality = '';
    barangay = '';
    street = '';
    houseUnit = '';

    vehicleType = '';
    jobType = '';
    licenseNumber = '';
    plateNumber = '';

    licenseImage = '';
    orCrImage = '';
    status = '';
    isAvailable = null;
    licenseExpiry = null;
  }

  static String _toString(dynamic value) {
    if (value == null) return '';
    return value.toString();
  }

  static int? _toInt(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    return int.tryParse(value.toString());
  }

  static bool? _toBool(dynamic value) {
    if (value == null) return null;
    if (value is bool) return value;

    if (value is num) {
      return value != 0;
    }

    switch (value.toString().toLowerCase().trim()) {
      case 'true':
      case '1':
      case 'yes':
        return true;
      case 'false':
      case '0':
      case 'no':
        return false;
      default:
        return null;
    }
  }

  static DateTime? _toDateTime(dynamic value) {
    if (value == null || value.toString().trim().isEmpty) {
      return null;
    }

    return DateTime.tryParse(value.toString());
  }
}

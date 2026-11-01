import 'package:flutter/foundation.dart';

class ParcelData {
  final String parcelNumber;
  final String trackingNumber;
  final String hub;
  final String recipient;
  final String phone;
  final String address;
  final String parcelType;
  final String paymentMethod;
  final double codAmount;
  final double riderFee;
  final String distance;
  final double customerLatitude;
  final double customerLongitude;
  final String qrValue;
  final String status;

  // Local demo timestamps used for daily dashboard/report calculations.
  // These become server timestamps when the app is connected to the real API.
  final DateTime? deliveredAt;
  final DateTime? statusUpdatedAt;

  const ParcelData({
    required this.parcelNumber,
    required this.trackingNumber,
    required this.hub,
    required this.recipient,
    required this.phone,
    required this.address,
    required this.parcelType,
    required this.paymentMethod,
    required this.codAmount,
    required this.riderFee,
    required this.distance,
    required this.customerLatitude,
    required this.customerLongitude,
    required this.qrValue,
    required this.status,
    this.deliveredAt,
    this.statusUpdatedAt,
  });

  ParcelData copyWith({
    String? status,
    DateTime? deliveredAt,
    DateTime? statusUpdatedAt,
  }) => ParcelData(
    parcelNumber: parcelNumber,
    trackingNumber: trackingNumber,
    hub: hub,
    recipient: recipient,
    phone: phone,
    address: address,
    parcelType: parcelType,
    paymentMethod: paymentMethod,
    codAmount: codAmount,
    riderFee: riderFee,
    distance: distance,
    customerLatitude: customerLatitude,
    customerLongitude: customerLongitude,
    qrValue: qrValue,
    status: status ?? this.status,
    deliveredAt: deliveredAt ?? this.deliveredAt,
    statusUpdatedAt: statusUpdatedAt ?? this.statusUpdatedAt,
  );
}

enum SellerPickupStatus {
  pendingAssignment,
  assigned,
  inProgress,
  awaitingSellerVerification,
  pickupVerified,
  pickupDisputed,
  pickupFailed,
  pickedUp, // Legacy status kept for compatibility.
  headingToSortingCenter,
}

extension SellerPickupStatusLabel on SellerPickupStatus {
  String get label => switch (this) {
    SellerPickupStatus.pendingAssignment => 'Pending Assignment',
    SellerPickupStatus.assigned => 'Assigned',
    SellerPickupStatus.inProgress => 'Seller Pickup In Progress',
    SellerPickupStatus.awaitingSellerVerification =>
      'Awaiting Seller Verification',
    SellerPickupStatus.pickupVerified => 'Pickup Verified',
    SellerPickupStatus.pickupDisputed => 'Pickup Disputed',
    SellerPickupStatus.pickupFailed => 'Pickup Failed',
    SellerPickupStatus.pickedUp => 'Picked Up',
    SellerPickupStatus.headingToSortingCenter => 'Heading to Sorting Center',
  };
}

enum SellerPickupScanResult {
  accepted,
  invalidQr,
  pickupNotFound,
  pickupNotActive,
  parcelNotExpected,
  parcelAssignedElsewhere,
  duplicateScan,
  noUnscannedParcels,
  alreadyCollected,
}

enum SellerPickupConfirmationResult {
  confirmed,
  pickupNotFound,
  pickupNotActive,
  parcelAssignedElsewhere,
  parcelsNotAllScanned,
  parcelAlreadyCollected,
  alreadyConfirmed,
}

enum SellerPickupSubmissionResult {
  submitted,
  pickupNotFound,
  pickupNotActive,
  noParcelsSelected,
  parcelNotExpected,
  parcelAlreadyCollected,
  alreadySubmitted,
}

enum SellerPickupVerificationResult {
  verified,
  disputed,
  pickupNotFound,
  awaitingVerification,
  alreadyProcessed,
}

enum SellerPickupStartResult { started, pickupNotFound, pickupNotAssignable }

class SellerPickupRequest {
  final String requestId;
  final String sellerName;
  final String pickupAddress;
  final String? sellerPhone;
  final double? pickupLatitude;
  final double? pickupLongitude;
  final List<ParcelData> expectedParcels;
  final SellerPickupStatus status;

  // Legacy QR-scanning fields retained so existing screens still compile.
  final Set<String> scannedTrackingNumbers;
  final Map<String, String> scannedWaybillValues;
  final bool acceptAnyWaybillQr;

  // New handover confirmation fields.
  final Set<String> receivedTrackingNumbers;
  final Set<String> verifiedTrackingNumbers;
  final String? discrepancyNote;

  SellerPickupRequest({
    required this.requestId,
    required this.sellerName,
    required this.pickupAddress,
    this.sellerPhone,
    this.pickupLatitude,
    this.pickupLongitude,
    required List<ParcelData> expectedParcels,
    this.status = SellerPickupStatus.assigned,
    Set<String> scannedTrackingNumbers = const {},
    Map<String, String> scannedWaybillValues = const {},
    this.acceptAnyWaybillQr = true,
    Set<String> receivedTrackingNumbers = const {},
    Set<String> verifiedTrackingNumbers = const {},
    this.discrepancyNote,
  }) : expectedParcels = List.unmodifiable(expectedParcels),
       scannedTrackingNumbers = Set.unmodifiable(scannedTrackingNumbers),
       scannedWaybillValues = Map.unmodifiable(scannedWaybillValues),
       receivedTrackingNumbers = Set.unmodifiable(receivedTrackingNumbers),
       verifiedTrackingNumbers = Set.unmodifiable(verifiedTrackingNumbers);

  SellerPickupRequest copyWith({
    SellerPickupStatus? status,
    Set<String>? scannedTrackingNumbers,
    Map<String, String>? scannedWaybillValues,
    List<ParcelData>? expectedParcels,
    Set<String>? receivedTrackingNumbers,
    Set<String>? verifiedTrackingNumbers,
    String? discrepancyNote,
    bool clearDiscrepancyNote = false,
  }) => SellerPickupRequest(
    requestId: requestId,
    sellerName: sellerName,
    pickupAddress: pickupAddress,
    sellerPhone: sellerPhone,
    pickupLatitude: pickupLatitude,
    pickupLongitude: pickupLongitude,
    expectedParcels: expectedParcels ?? this.expectedParcels,
    status: status ?? this.status,
    scannedTrackingNumbers:
        scannedTrackingNumbers ?? this.scannedTrackingNumbers,
    scannedWaybillValues: scannedWaybillValues ?? this.scannedWaybillValues,
    acceptAnyWaybillQr: acceptAnyWaybillQr,
    receivedTrackingNumbers:
        receivedTrackingNumbers ?? this.receivedTrackingNumbers,
    verifiedTrackingNumbers:
        verifiedTrackingNumbers ?? this.verifiedTrackingNumbers,
    discrepancyNote: clearDiscrepancyNote
        ? null
        : discrepancyNote ?? this.discrepancyNote,
  );
}

class ParcelStore {
  static final ValueNotifier<List<ParcelData>> parcels = ValueNotifier([]);
  static final SellerPickupRequest demoSellerPickup = SellerPickupRequest(
    requestId: 'PU-0001',
    sellerName: 'Victoria Home Supplies',
    pickupAddress: 'Lumear Seller Hub, Victoria, Laguna',
    sellerPhone: '0912 345 6789',
    pickupLatitude: 14.2277,
    pickupLongitude: 121.3284,
    expectedParcels: const [
      ParcelData(
        parcelNumber: '#SPX1234',
        trackingNumber: 'SPX1234',
        hub: 'Victoria Hub',
        recipient: 'Awaiting sorting',
        phone: '',
        address: 'Lumear Seller Hub, Victoria, Laguna',
        parcelType: 'Seller pickup parcel',
        paymentMethod: 'Paid',
        codAmount: 0,
        riderFee: 0,
        distance: '',
        customerLatitude: 0,
        customerLongitude: 0,
        qrValue: 'SPX1234',
        status: 'To Pick Up',
      ),
      ParcelData(
        parcelNumber: '#SPX1235',
        trackingNumber: 'SPX1235',
        hub: 'Victoria Hub',
        recipient: 'Awaiting sorting',
        phone: '',
        address: 'Lumear Seller Hub, Victoria, Laguna',
        parcelType: 'Seller pickup parcel',
        paymentMethod: 'Paid',
        codAmount: 0,
        riderFee: 0,
        distance: '',
        customerLatitude: 0,
        customerLongitude: 0,
        qrValue: 'SPX1235',
        status: 'To Pick Up',
      ),
    ],
  );

  static final ValueNotifier<List<SellerPickupRequest>> sellerPickups =
      ValueNotifier([demoSellerPickup]);
  static final ValueNotifier<ParcelData?> currentParcel = ValueNotifier(null);
  // Each successful delivery is split: ₱40 to the rider, ₱10 to Logistics.
  static const double earningPerDelivery = 40;
  static const double logisticsSharePerDelivery = 10;
  static final ValueNotifier<double> totalEarnings = ValueNotifier(0);
  static final ValueNotifier<List<ParcelData>> earnedParcels = ValueNotifier(
    [],
  );

  static SellerPickupRequest? findSellerPickup(String requestId) {
    for (final pickup in sellerPickups.value) {
      if (pickup.requestId == requestId) return pickup;
    }
    return null;
  }

  static void addSellerPickup(SellerPickupRequest pickup) {
    if (sellerPickups.value.any((item) => item.requestId == pickup.requestId)) {
      throw ArgumentError.value(
        pickup.requestId,
        'requestId',
        'Already exists',
      );
    }
    sellerPickups.value = [...sellerPickups.value, pickup];
  }

  static SellerPickupStartResult startSellerPickup(String requestId) {
    final pickup = findSellerPickup(requestId);
    if (pickup == null) return SellerPickupStartResult.pickupNotFound;
    if (pickup.status == SellerPickupStatus.inProgress) {
      return SellerPickupStartResult.started;
    }
    if (pickup.status != SellerPickupStatus.assigned) {
      return SellerPickupStartResult.pickupNotAssignable;
    }
    _replaceSellerPickup(
      pickup.copyWith(
        status: SellerPickupStatus.inProgress,
        clearDiscrepancyNote: true,
      ),
    );
    return SellerPickupStartResult.started;
  }

  // Legacy waybill scan method retained for existing screens.
  static SellerPickupScanResult scanSellerPickupWaybill(
    String requestId,
    String rawQr,
  ) {
    final qrValue = rawQr.trim();
    if (qrValue.isEmpty) return SellerPickupScanResult.invalidQr;
    final pickup = findSellerPickup(requestId);
    if (pickup == null) return SellerPickupScanResult.pickupNotFound;
    if (pickup.status != SellerPickupStatus.inProgress) {
      return SellerPickupScanResult.pickupNotActive;
    }
    if (pickup.scannedWaybillValues.values.contains(qrValue)) {
      return SellerPickupScanResult.duplicateScan;
    }

    ParcelData? expectedParcel;
    for (final parcel in pickup.expectedParcels) {
      if (parcel.trackingNumber == qrValue || parcel.qrValue == qrValue) {
        expectedParcel = parcel;
        break;
      }
    }
    if (expectedParcel == null) {
      if (!pickup.acceptAnyWaybillQr)
        return SellerPickupScanResult.parcelNotExpected;
      for (final parcel in pickup.expectedParcels) {
        if (!pickup.scannedTrackingNumbers.contains(parcel.trackingNumber)) {
          expectedParcel = parcel;
          break;
        }
      }
      if (expectedParcel == null)
        return SellerPickupScanResult.noUnscannedParcels;
    }

    final targetTracking = expectedParcel.trackingNumber;
    final assignedElsewhere = sellerPickups.value.any(
      (other) =>
          other.requestId != requestId &&
          other.expectedParcels.any(
            (parcel) => parcel.trackingNumber == targetTracking,
          ),
    );
    if (assignedElsewhere)
      return SellerPickupScanResult.parcelAssignedElsewhere;
    if (pickup.scannedTrackingNumbers.contains(targetTracking)) {
      return SellerPickupScanResult.duplicateScan;
    }
    final stored = _findParcel(targetTracking);
    if (stored != null && !_isCollectable(stored)) {
      return SellerPickupScanResult.alreadyCollected;
    }

    _replaceSellerPickup(
      pickup.copyWith(
        scannedTrackingNumbers: {
          ...pickup.scannedTrackingNumbers,
          targetTracking,
        },
        scannedWaybillValues: {
          ...pickup.scannedWaybillValues,
          targetTracking: qrValue,
        },
      ),
    );
    return SellerPickupScanResult.accepted;
  }

  /// Rider submits only the tracking numbers of parcels physically received.
  /// This is local demo state until a Laravel API/database is connected.
  static SellerPickupSubmissionResult submitSellerPickupConfirmation(
    String requestId,
    Set<String> receivedTrackingNumbers,
  ) {
    final pickup = findSellerPickup(requestId);
    if (pickup == null) return SellerPickupSubmissionResult.pickupNotFound;
    if ({
      SellerPickupStatus.awaitingSellerVerification,
      SellerPickupStatus.pickupVerified,
      SellerPickupStatus.pickupDisputed,
      SellerPickupStatus.pickedUp,
      SellerPickupStatus.headingToSortingCenter,
    }.contains(pickup.status)) {
      return SellerPickupSubmissionResult.alreadySubmitted;
    }
    if (pickup.status != SellerPickupStatus.inProgress) {
      return SellerPickupSubmissionResult.pickupNotActive;
    }
    if (receivedTrackingNumbers.isEmpty) {
      return SellerPickupSubmissionResult.noParcelsSelected;
    }

    final expectedNumbers = pickup.expectedParcels
        .map((p) => p.trackingNumber)
        .toSet();
    if (!expectedNumbers.containsAll(receivedTrackingNumbers)) {
      return SellerPickupSubmissionResult.parcelNotExpected;
    }
    for (final tracking in receivedTrackingNumbers) {
      final stored = _findParcel(tracking);
      if (stored != null && !_isCollectable(stored)) {
        return SellerPickupSubmissionResult.parcelAlreadyCollected;
      }
    }

    _replaceSellerPickup(
      pickup.copyWith(
        status: SellerPickupStatus.awaitingSellerVerification,
        receivedTrackingNumbers: receivedTrackingNumbers,
        verifiedTrackingNumbers: const {},
        clearDiscrepancyNote: true,
      ),
    );
    // Do not mark any parcel as picked up until seller verification.
    return SellerPickupSubmissionResult.submitted;
  }

  static SellerPickupVerificationResult verifySellerPickup(String requestId) {
    final pickup = findSellerPickup(requestId);
    if (pickup == null) return SellerPickupVerificationResult.pickupNotFound;
    if (pickup.status != SellerPickupStatus.awaitingSellerVerification) {
      if ({
        SellerPickupStatus.pickupVerified,
        SellerPickupStatus.pickupDisputed,
        SellerPickupStatus.headingToSortingCenter,
      }.contains(pickup.status)) {
        return SellerPickupVerificationResult.alreadyProcessed;
      }
      return SellerPickupVerificationResult.awaitingVerification;
    }

    for (final tracking in pickup.receivedTrackingNumbers) {
      final expected = _expectedParcel(pickup, tracking);
      if (expected == null) continue;
      final stored = _findParcel(tracking);
      if (stored != null && !_isCollectable(stored)) {
        return SellerPickupVerificationResult.alreadyProcessed;
      }
    }
    for (final tracking in pickup.receivedTrackingNumbers) {
      final expected = _expectedParcel(pickup, tracking);
      if (expected != null)
        _upsertParcel(expected.copyWith(status: 'Picked Up'));
    }
    _replaceSellerPickup(
      pickup.copyWith(
        status: SellerPickupStatus.pickupVerified,
        verifiedTrackingNumbers: pickup.receivedTrackingNumbers,
      ),
    );
    return SellerPickupVerificationResult.verified;
  }

  static SellerPickupVerificationResult disputeSellerPickup(
    String requestId, {
    required String note,
  }) {
    final pickup = findSellerPickup(requestId);
    if (pickup == null) return SellerPickupVerificationResult.pickupNotFound;
    if (pickup.status != SellerPickupStatus.awaitingSellerVerification) {
      if ({
        SellerPickupStatus.pickupVerified,
        SellerPickupStatus.pickupDisputed,
        SellerPickupStatus.headingToSortingCenter,
      }.contains(pickup.status)) {
        return SellerPickupVerificationResult.alreadyProcessed;
      }
      return SellerPickupVerificationResult.awaitingVerification;
    }
    _replaceSellerPickup(
      pickup.copyWith(
        status: SellerPickupStatus.pickupDisputed,
        discrepancyNote: note.trim().isEmpty
            ? 'Seller reported a pickup discrepancy.'
            : note.trim(),
      ),
    );
    return SellerPickupVerificationResult.disputed;
  }

  // Legacy QR-gated confirmation retained so older screens/tests compile.
  static SellerPickupConfirmationResult confirmSellerPickup(String requestId) {
    final pickup = findSellerPickup(requestId);
    if (pickup == null) return SellerPickupConfirmationResult.pickupNotFound;
    if ({
      SellerPickupStatus.pickedUp,
      SellerPickupStatus.headingToSortingCenter,
      SellerPickupStatus.pickupVerified,
      SellerPickupStatus.awaitingSellerVerification,
    }.contains(pickup.status)) {
      return SellerPickupConfirmationResult.alreadyConfirmed;
    }
    if (pickup.status != SellerPickupStatus.inProgress) {
      return SellerPickupConfirmationResult.pickupNotActive;
    }
    if (pickup.expectedParcels.any(
      (p) => !pickup.scannedTrackingNumbers.contains(p.trackingNumber),
    )) {
      return SellerPickupConfirmationResult.parcelsNotAllScanned;
    }
    if (pickup.expectedParcels.any((p) {
      final stored = _findParcel(p.trackingNumber);
      return stored != null && !_isCollectable(stored);
    })) {
      return SellerPickupConfirmationResult.parcelAlreadyCollected;
    }
    for (final parcel in pickup.expectedParcels) {
      _upsertParcel(parcel.copyWith(status: 'Picked Up'));
    }
    _replaceSellerPickup(pickup.copyWith(status: SellerPickupStatus.pickedUp));
    return SellerPickupConfirmationResult.confirmed;
  }

  static bool beginSellerPickupHeadingToSortingCenter(String requestId) {
    final pickup = findSellerPickup(requestId);
    if (pickup == null) return false;
    if (pickup.status != SellerPickupStatus.pickupVerified &&
        pickup.status != SellerPickupStatus.pickedUp) {
      return false;
    }

    final trackingNumbers = pickup.verifiedTrackingNumbers.isNotEmpty
        ? pickup.verifiedTrackingNumbers
        : pickup.expectedParcels.map((p) => p.trackingNumber).toSet();
    for (final tracking in trackingNumbers) {
      final expected = _expectedParcel(pickup, tracking);
      if (expected != null) {
        _upsertParcel(expected.copyWith(status: 'Heading to Sorting Center'));
      }
    }
    _replaceSellerPickup(
      pickup.copyWith(status: SellerPickupStatus.headingToSortingCenter),
    );
    return true;
  }

  static ParcelData? _expectedParcel(
    SellerPickupRequest pickup,
    String tracking,
  ) {
    for (final parcel in pickup.expectedParcels) {
      if (parcel.trackingNumber == tracking) return parcel;
    }
    return null;
  }

  static void _replaceSellerPickup(SellerPickupRequest updated) {
    sellerPickups.value = sellerPickups.value
        .map(
          (pickup) => pickup.requestId == updated.requestId ? updated : pickup,
        )
        .toList(growable: false);
  }

  static ParcelData? _findParcel(String trackingNumber) {
    for (final parcel in parcels.value) {
      if (parcel.trackingNumber == trackingNumber) return parcel;
    }
    return null;
  }

  static bool _isCollectable(ParcelData parcel) =>
      parcel.status == 'To Pick Up' ||
      parcel.status == 'Assigned' ||
      parcel.status == 'Seller Pickup In Progress';

  static void _upsertParcel(ParcelData updated) {
    final exists = parcels.value.any(
      (p) => p.trackingNumber == updated.trackingNumber,
    );
    parcels.value = exists
        ? parcels.value
              .map(
                (p) => p.trackingNumber == updated.trackingNumber ? updated : p,
              )
              .toList(growable: false)
        : [updated, ...parcels.value];

    if (currentParcel.value?.trackingNumber == updated.trackingNumber) {
      currentParcel.value = updated;
    }
  }

  static ParcelData createParcelFromQr(String qrValue) {
    final cleanQr = qrValue.trim();
    if (cleanQr.isEmpty) throw Exception('QR code is empty.');

    for (final parcel in parcels.value) {
      if (parcel.qrValue == cleanQr) {
        currentParcel.value = parcel;
        debugPrint('PARCEL ALREADY EXISTS: ${parcel.trackingNumber}');
        return parcel;
      }
    }

    final numberString = DateTime.now().microsecondsSinceEpoch.toString();
    final shortId = numberString.substring(numberString.length - 7);
    final newParcel = ParcelData(
      parcelNumber: '#LUM$shortId',
      trackingNumber: 'LUMEAR-$shortId',
      hub: 'Lumear Seller Hub - Victoria',
      recipient: 'Maricko Sheiy Villacorta',
      phone: '0912 345 6789',
      address: 'Masapang, Victoria, Laguna',
      parcelType: 'Medium Parcel',
      paymentMethod: 'Cash on Delivery',
      codAmount: 150.00,
      riderFee: 45.00,
      distance: '3.2 km',
      customerLatitude: 14.2277,
      customerLongitude: 121.3284,
      qrValue: cleanQr,
      status: 'To Pick Up',
    );
    parcels.value = [newParcel, ...parcels.value];
    currentParcel.value = newParcel;
    debugPrint('PARCEL CREATED: ${newParcel.parcelNumber}');
    debugPrint('Tracking: ${newParcel.trackingNumber}');
    debugPrint('QR: ${newParcel.qrValue}');
    return newParcel;
  }

  static void updateStatus(String status) {
    final current = currentParcel.value;
    if (current == null) return;

    final now = DateTime.now();
    final updated = current.copyWith(
      status: status,
      statusUpdatedAt: now,
      deliveredAt: status == 'Delivered' ? (current.deliveredAt ?? now) : null,
    );

    currentParcel.value = updated;
    parcels.value = parcels.value
        .map(
          (p) => p.trackingNumber == updated.trackingNumber
              ? p.copyWith(
                  status: status,
                  statusUpdatedAt: now,
                  deliveredAt: status == 'Delivered'
                      ? (p.deliveredAt ?? now)
                      : null,
                )
              : p,
        )
        .toList(growable: false);
  }

  static bool awardSuccessfulDelivery(ParcelData completedParcel) {
    if (completedParcel.status != 'Delivered') return false;
    ParcelData? stored;
    for (final parcel in parcels.value) {
      if (parcel.trackingNumber == completedParcel.trackingNumber) {
        stored = parcel;
        break;
      }
    }
    if (stored != null && stored.status != 'Delivered') return false;
    if (earnedParcels.value.any(
      (p) => p.trackingNumber == completedParcel.trackingNumber,
    ))
      return false;

    final earned = (stored ?? completedParcel).copyWith(
      deliveredAt: (stored ?? completedParcel).deliveredAt ?? DateTime.now(),
      statusUpdatedAt:
          (stored ?? completedParcel).statusUpdatedAt ?? DateTime.now(),
    );
    if (stored == null) parcels.value = [earned, ...parcels.value];
    earnedParcels.value = [earned, ...earnedParcels.value];
    totalEarnings.value += earningPerDelivery;
    return true;
  }

  static void removeParcel() {
    currentParcel.value = null;
  }

  static void clearAll() {
    parcels.value = [];
    sellerPickups.value = [];
    currentParcel.value = null;
    earnedParcels.value = [];
    totalEarnings.value = 0;
  }
}

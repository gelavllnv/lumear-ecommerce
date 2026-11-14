import 'dart:convert';

import 'package:flutter/foundation.dart';

import '../services/rider_api_service.dart';

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
  final double shippingFee;
  final String distance;
  final double customerLatitude;
  final double customerLongitude;
  final String qrValue;
  final String status;
  final int? remoteShipmentId;

  // Timestamps returned by Laravel for shipment status updates.
  final DateTime? deliveredAt;
  final DateTime? statusUpdatedAt;
  final DateTime? pickedUpAt;
  final int failedAttempts;
  final String? proofImagePath;
  final String? receivedBy;

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
    this.shippingFee = 0,
    required this.distance,
    required this.customerLatitude,
    required this.customerLongitude,
    required this.qrValue,
    required this.status,
    this.remoteShipmentId,
    this.deliveredAt,
    this.statusUpdatedAt,
    this.pickedUpAt,
    this.failedAttempts = 0,
    this.proofImagePath,
    this.receivedBy,
  });

  ParcelData copyWith({
    String? status,
    int? remoteShipmentId,
    DateTime? deliveredAt,
    DateTime? statusUpdatedAt,
    DateTime? pickedUpAt,
    int? failedAttempts,
    String? proofImagePath,
    String? receivedBy,
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
    shippingFee: shippingFee,
    distance: distance,
    customerLatitude: customerLatitude,
    customerLongitude: customerLongitude,
    qrValue: qrValue,
    status: status ?? this.status,
    remoteShipmentId: remoteShipmentId ?? this.remoteShipmentId,
    deliveredAt: deliveredAt ?? this.deliveredAt,
    statusUpdatedAt: statusUpdatedAt ?? this.statusUpdatedAt,
    pickedUpAt: pickedUpAt ?? this.pickedUpAt,
    failedAttempts: failedAttempts ?? this.failedAttempts,
    proofImagePath: proofImagePath ?? this.proofImagePath,
    receivedBy: receivedBy ?? this.receivedBy,
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
  static final ValueNotifier<List<SellerPickupRequest>> sellerPickups =
      ValueNotifier([]);
  static final ValueNotifier<ParcelData?> currentParcel = ValueNotifier(null);
  static final ValueNotifier<double> totalEarnings = ValueNotifier(0);
  static final ValueNotifier<List<ParcelData>> earnedParcels = ValueNotifier(
    [],
  );

  /// Replaces local shipment state with the rider's Laravel assignments.
  static void replaceRemoteParcels(List<ParcelData> assignedParcels) {
    connected = true;
    final previousByTracking = {
      for (final parcel in parcels.value) parcel.trackingNumber: parcel,
    };
    final refreshedParcels = assignedParcels
        .map((parcel) {
          final previous = previousByTracking[parcel.trackingNumber];
          if (previous == null) return parcel;
          return parcel.copyWith(
            proofImagePath: parcel.proofImagePath ?? previous.proofImagePath,
            receivedBy: parcel.receivedBy ?? previous.receivedBy,
          );
        })
        .toList(growable: false);
    parcels.value = refreshedParcels;

    ParcelData? active;
    for (final parcel in refreshedParcels) {
      if (parcel.status != 'Delivered' &&
          parcel.status != 'Returned' &&
          parcel.status != 'Delivery Failed') {
        active = parcel;
        break;
      }
    }
    currentParcel.value = active;
  }

  static bool connected = false;

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
      if (!pickup.acceptAnyWaybillQr) {
        return SellerPickupScanResult.parcelNotExpected;
      }
      for (final parcel in pickup.expectedParcels) {
        if (!pickup.scannedTrackingNumbers.contains(parcel.trackingNumber)) {
          expectedParcel = parcel;
          break;
        }
      }
      if (expectedParcel == null) {
        return SellerPickupScanResult.noUnscannedParcels;
      }
    }

    final targetTracking = expectedParcel.trackingNumber;
    final assignedElsewhere = sellerPickups.value.any(
      (other) =>
          other.requestId != requestId &&
          other.expectedParcels.any(
            (parcel) => parcel.trackingNumber == targetTracking,
          ),
    );
    if (assignedElsewhere) {
      return SellerPickupScanResult.parcelAssignedElsewhere;
    }
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
  /// Updates the server-synced rider task view after a status change.
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
      if (expected != null) {
        _upsertParcel(expected.copyWith(status: 'Picked Up'));
      }
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
    final cleanQr = _decodeWaybillPayload(qrValue);
    if (cleanQr.isEmpty) throw Exception('QR code is empty.');
    final trackingCode = cleanQr
        .replaceFirst(
          RegExp(r'^LUMEAR-WAYBILL\s*:\s*', caseSensitive: false),
          '',
        )
        .trim()
        .toUpperCase();

    for (final parcel in parcels.value) {
      if (parcel.qrValue.toUpperCase() == trackingCode ||
          parcel.trackingNumber.toUpperCase() == trackingCode) {
        currentParcel.value = parcel;
        debugPrint('PARCEL ALREADY EXISTS: ${parcel.trackingNumber}');
        return parcel;
      }
    }

    throw StateError(
      'This QR code is not assigned to your account. Refresh your assignments and try again.',
    );
  }

  static Future<ParcelData> confirmPickupFromWaybill(String rawQr) async {
    final parcel = createParcelFromQr(rawQr);
    if (parcel.status != 'Pending') {
      throw StateError('This parcel is no longer waiting for pickup.');
    }
    final shipmentId = parcel.remoteShipmentId;
    if (shipmentId == null) {
      throw StateError('This parcel is not connected to a server assignment.');
    }

    await RiderApiService.confirmShipmentPickupScan(
      shipmentId,
      _decodeWaybillPayload(rawQr),
    );

    final updated = parcel.copyWith(
      status: 'Parcel Picked Up',
      statusUpdatedAt: DateTime.now(),
    );
    currentParcel.value = updated;
    parcels.value = parcels.value
        .map(
          (item) =>
              item.trackingNumber == updated.trackingNumber ? updated : item,
        )
        .toList(growable: false);

    return updated;
  }

  static String _decodeWaybillPayload(String qrValue) {
    final trimmedQr = qrValue.trim();
    try {
      final decodedQr = jsonDecode(trimmedQr);
      if (decodedQr is String) {
        return decodedQr.trim();
      }
    } on FormatException {
      // Older waybills may contain a plain-text QR instead of a JSON string.
    }

    return trimmedQr;
  }

  static Future<void> updateStatus(
    String status, {
    String? trackingNumber,
    String? proofImagePath,
    String? receivedBy,
    String? note,
  }) async {
    final current = trackingNumber == null
        ? currentParcel.value
        : parcels.value
              .where((p) => p.trackingNumber == trackingNumber)
              .firstOrNull;
    if (current == null) return;

    final apiStatus = switch (status) {
      'Heading to Pickup' => 'out_for_delivery',
      'In Transit' => 'in_transit',
      'Out for Delivery' => 'out_for_delivery',
      'Awaiting Buyer Confirmation' => 'delivered',
      'Delivered' => 'delivered',
      'Delivery Failed' || 'Returned' => 'failed',
      _ => null,
    };
    if (current.remoteShipmentId != null && apiStatus != null) {
      if (proofImagePath != null && receivedBy?.trim().isNotEmpty == true) {
        await RiderApiService.uploadShipmentProof(
          shipmentId: current.remoteShipmentId!,
          imagePath: proofImagePath,
          receivedBy: receivedBy!.trim(),
        );
      }
      await RiderApiService.updateShipmentStatus(
        current.remoteShipmentId!,
        apiStatus,
        note: note,
      );
    }

    final now = DateTime.now();
    final updated = current.copyWith(
      status: status,
      statusUpdatedAt: now,
      deliveredAt:
          status == 'Delivered' || status == 'Awaiting Buyer Confirmation'
          ? (current.deliveredAt ?? now)
          : null,
      proofImagePath: proofImagePath,
      receivedBy: receivedBy,
    );

    currentParcel.value = updated;
    parcels.value = parcels.value
        .map(
          (p) => p.trackingNumber == updated.trackingNumber
              ? p.copyWith(
                  status: status,
                  statusUpdatedAt: now,
                  deliveredAt:
                      status == 'Delivered' ||
                          status == 'Awaiting Buyer Confirmation'
                      ? (p.deliveredAt ?? now)
                      : null,
                  proofImagePath: proofImagePath,
                  receivedBy: receivedBy,
                )
              : p,
        )
        .toList(growable: false);
  }

  static Future<Map<String, dynamic>> reportFailedAttempt(
    String reason,
    String note, {
    required String trackingNumber,
  }) async {
    final parcel = parcels.value
        .where((item) => item.trackingNumber == trackingNumber)
        .firstOrNull;
    final shipmentId = parcel?.remoteShipmentId;
    if (shipmentId == null) {
      throw StateError('This parcel is not connected to a server assignment.');
    }
    final result = await RiderApiService.updateShipmentStatus(
      shipmentId,
      'failed',
      note: [reason, if (note.trim().isNotEmpty) note.trim()].join(' — '),
    );
    final attempts = int.tryParse(result['attempt']?.toString() ?? '') ?? 0;
    if (parcel != null) {
      final updated = parcel.copyWith(failedAttempts: attempts);
      parcels.value = parcels.value
          .map((item) => item.trackingNumber == trackingNumber ? updated : item)
          .toList(growable: false);
      if (currentParcel.value?.trackingNumber == trackingNumber) {
        currentParcel.value = updated;
      }
    }
    return result;
  }

  static Future<void> reportDeliveryDelay(
    String reason,
    DateTime proposedFor, {
    required String trackingNumber,
  }) async {
    final parcel = parcels.value
        .where((item) => item.trackingNumber == trackingNumber)
        .firstOrNull;
    final shipmentId = parcel?.remoteShipmentId;

    if (shipmentId == null) {
      throw StateError('This parcel is not connected to a server assignment.');
    }

    await RiderApiService.reportShipmentDelay(
      shipmentId,
      reason: reason,
      proposedFor: proposedFor,
    );
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
    )) {
      return false;
    }

    final earned = (stored ?? completedParcel).copyWith(
      deliveredAt: (stored ?? completedParcel).deliveredAt ?? DateTime.now(),
      statusUpdatedAt:
          (stored ?? completedParcel).statusUpdatedAt ?? DateTime.now(),
    );
    if (stored == null) parcels.value = [earned, ...parcels.value];
    earnedParcels.value = [earned, ...earnedParcels.value];
    totalEarnings.value += earned.riderFee;
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

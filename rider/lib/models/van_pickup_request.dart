enum VanPickupStatus {
  assigned,
  inProgress,
  arrived,
  collected,
  sellerConfirmed,
  handedOver,
  failed, // new
}

class VanPickupRequest {
  final String id;
  final String sellerName;
  final String sellerPhone;
  final String sellerEmail;
  final String address;
  final String barangay;
  final String municipality;
  final String province;
  final int parcelCount;
  final String notes;
  final double totalAmount;

  VanPickupStatus status;
  DateTime? arrivedAt;
  DateTime? collectedAt;
  DateTime? sellerConfirmedAt;
  DateTime? handedOverAt;
  DateTime? failedAt;
  String? failureReason;

  VanPickupRequest({
    required this.id,
    required this.sellerName,
    required this.sellerPhone,
    required this.sellerEmail,
    required this.address,
    required this.barangay,
    required this.municipality,
    required this.province,
    required this.parcelCount,
    this.notes = '',
    required this.totalAmount,
    this.status = VanPickupStatus.assigned,
    this.arrivedAt,
    this.collectedAt,
    this.sellerConfirmedAt,
    this.handedOverAt,
    this.failedAt,
    this.failureReason,
  });

  String get fullAddress => '$address, $barangay, $municipality, $province';

  String get statusLabel {
    switch (status) {
      case VanPickupStatus.assigned:
        return 'Assigned';
      case VanPickupStatus.inProgress:
        return 'Heading to Seller';
      case VanPickupStatus.arrived:
        return 'Arrived at Seller';
      case VanPickupStatus.collected:
        return 'Collected';
      case VanPickupStatus.sellerConfirmed:
        return 'Seller Confirmation';
      case VanPickupStatus.handedOver:
        return 'Handed Over';
      case VanPickupStatus.failed:
        return 'Failed';
    }
  }

  void startPickup() {
    if (status == VanPickupStatus.assigned) {
      status = VanPickupStatus.inProgress;
    }
  }

  void confirmArrival() {
    if (status != VanPickupStatus.inProgress) return;
    status = VanPickupStatus.arrived;
    arrivedAt = DateTime.now(); // kung may ganitong field ka
  }

  // Driver reports that the parcels were collected from the seller.
  void reportCollection() {
    if (status != VanPickupStatus.arrived) return;
    status = VanPickupStatus.collected;
  }

  // Demo-only acknowledgement by the van driver that the seller confirmed.
  // This is not actual validation by the seller.
  void acknowledgeSellerConfirmation() {
    if (status == VanPickupStatus.collected) {
      status = VanPickupStatus.sellerConfirmed;
      sellerConfirmedAt = DateTime.now();
    }
  }

  // Older one-step flow: collection + seller confirmation together.
  // Kept so other screens that call it still compile.
  void confirmCollection() {
    if (status == VanPickupStatus.inProgress && arrivedAt != null) {
      status = VanPickupStatus.sellerConfirmed;
      collectedAt = DateTime.now();
      sellerConfirmedAt = DateTime.now();
    }
  }

  void confirmHandover() {
    if (status == VanPickupStatus.sellerConfirmed) {
      status = VanPickupStatus.handedOver;
      handedOverAt = DateTime.now();
    }
  }

  void reportFailedPickup(String reason) {
    status = VanPickupStatus.failed;
    failureReason = reason;
    failedAt = DateTime.now();
  }
}

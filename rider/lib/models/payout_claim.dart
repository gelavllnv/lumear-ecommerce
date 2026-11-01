enum PayoutSchedule { daily, weekly, monthly }

extension PayoutScheduleLabel on PayoutSchedule {
  String get label {
    switch (this) {
      case PayoutSchedule.daily:
        return 'Daily';
      case PayoutSchedule.weekly:
        return 'Weekly';
      case PayoutSchedule.monthly:
        return 'Monthly';
    }
  }
}

enum PayoutStatus { pending, paid, rejected }

extension PayoutStatusLabel on PayoutStatus {
  String get label {
    switch (this) {
      case PayoutStatus.pending:
        return 'Pending';
      case PayoutStatus.paid:
        return 'Paid';
      case PayoutStatus.rejected:
        return 'Rejected';
    }
  }
}

class EarningTransaction {
  final String transactionId;
  final String parcelNumber;
  final String trackingNumber;
  final String recipient;
  final double amount;
  final DateTime completedAt;

  const EarningTransaction({
    required this.transactionId,
    required this.parcelNumber,
    required this.trackingNumber,
    required this.recipient,
    required this.amount,
    required this.completedAt,
  });
}

class PayoutClaim {
  final String referenceId;
  final String riderId;
  final String riderName;
  final String riderEmail;
  final double amount;
  final PayoutSchedule schedule;
  final DateTime periodStart;
  final DateTime periodEnd;
  final List<EarningTransaction> deliveries;
  final DateTime requestedAt;
  final PayoutStatus status;
  final DateTime? paidAt;
  final String? staffIdentifier;
  final String? paymentMethod;
  final String? paymentReference;

  PayoutClaim({
    required this.referenceId,
    required this.riderId,
    required this.riderName,
    required this.riderEmail,
    required this.amount,
    required this.schedule,
    required this.periodStart,
    required this.periodEnd,
    required List<EarningTransaction> deliveries,
    required this.requestedAt,
    this.status = PayoutStatus.pending,
    this.paidAt,
    this.staffIdentifier,
    this.paymentMethod,
    this.paymentReference,
  }) : deliveries = List.unmodifiable(deliveries);

  PayoutClaim copyWith({
    PayoutStatus? status,
    DateTime? paidAt,
    String? staffIdentifier,
    String? paymentMethod,
    String? paymentReference,
  }) {
    return PayoutClaim(
      referenceId: referenceId,
      riderId: riderId,
      riderName: riderName,
      riderEmail: riderEmail,
      amount: amount,
      schedule: schedule,
      periodStart: periodStart,
      periodEnd: periodEnd,
      deliveries: deliveries,
      requestedAt: requestedAt,
      status: status ?? this.status,
      paidAt: paidAt ?? this.paidAt,
      staffIdentifier: staffIdentifier ?? this.staffIdentifier,
      paymentMethod: paymentMethod ?? this.paymentMethod,
      paymentReference: paymentReference ?? this.paymentReference,
    );
  }
}

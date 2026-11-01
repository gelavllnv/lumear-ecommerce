import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter_application_1/data/hardcoded_rider.dart';
import 'package:flutter_application_1/models/parcel_store.dart';
import 'package:flutter_application_1/models/payout_claim.dart';

class RiderEarningsService {
  RiderEarningsService({bool seedSampleData = false})
    : transactions = ValueNotifier<List<EarningTransaction>>(
        seedSampleData ? _sampleTransactions() : [],
      ),
      payouts = ValueNotifier<List<PayoutClaim>>([]);

  static final RiderEarningsService instance = RiderEarningsService(
    seedSampleData: true,
  );

  static const double earningPerDelivery = 50;

  final ValueNotifier<List<EarningTransaction>> transactions;
  final ValueNotifier<List<PayoutClaim>> payouts;
  final ValueNotifier<PayoutSchedule> selectedSchedule =
      ValueNotifier<PayoutSchedule>(PayoutSchedule.daily);

  final Set<String> _processingPayments = {};
  bool _creatingClaim = false;

  static List<EarningTransaction> _sampleTransactions() {
    final DateTime now = DateTime.now();
    final DateTime today = DateTime(now.year, now.month, now.day);
    final DateTime morningDelivery = now.hour >= 8
        ? today.add(const Duration(hours: 8))
        : DateTime(now.year, now.month, now.day, now.hour);
    final DateTime lateMorningDelivery = now.hour >= 10
        ? today.add(const Duration(hours: 10))
        : DateTime(now.year, now.month, now.day, now.hour);
    return [
      EarningTransaction(
        transactionId: 'SAMPLE-DELIVERY-001',
        parcelNumber: '#DEMO201',
        trackingNumber: 'DEMO-TRK-201',
        recipient: 'Ana Reyes',
        amount: earningPerDelivery,
        completedAt: morningDelivery,
      ),
      EarningTransaction(
        transactionId: 'SAMPLE-DELIVERY-002',
        parcelNumber: '#DEMO202',
        trackingNumber: 'DEMO-TRK-202',
        recipient: 'Mark Santos',
        amount: earningPerDelivery,
        completedAt: lateMorningDelivery,
      ),
      EarningTransaction(
        transactionId: 'SAMPLE-DELIVERY-003',
        parcelNumber: '#DEMO203',
        trackingNumber: 'DEMO-TRK-203',
        recipient: 'Juan Santos',
        amount: earningPerDelivery,
        completedAt: today
            .subtract(const Duration(days: 1))
            .add(const Duration(hours: 15)),
      ),
    ];
  }

  double get totalEarnings => _sum(transactions.value);

  double get todaysEarnings {
    final DateTime now = DateTime.now();
    final DateTime start = DateTime(now.year, now.month, now.day);
    final DateTime end = start.add(const Duration(days: 1));
    return _sum(
      transactions.value.where(
        (transaction) =>
            !transaction.completedAt.isBefore(start) &&
            transaction.completedAt.isBefore(end),
      ),
    );
  }

  double get pendingPayout => _sumClaims(PayoutStatus.pending);

  double get paidPayout => _sumClaims(PayoutStatus.paid);

  double get availableEarnings => totalEarnings - pendingPayout - paidPayout;

  int get successfulDeliveryCount => transactions.value.length;

  double eligibleAmount(PayoutSchedule schedule, {DateTime? at}) =>
      _sum(eligibleTransactions(schedule, at: at));

  List<EarningTransaction> eligibleTransactions(
    PayoutSchedule schedule, {
    DateTime? at,
  }) {
    final DateTime now = at ?? DateTime.now();
    final DateTime start = _periodStart(schedule, now);
    final DateTime end = _periodEnd(schedule, start);
    final Set<String> reservedIds = payouts.value
        .where((claim) => claim.status != PayoutStatus.rejected)
        .expand((claim) => claim.deliveries)
        .map((transaction) => transaction.transactionId)
        .toSet();

    return transactions.value
        .where(
          (transaction) =>
              transaction.amount > 0 &&
              !transaction.completedAt.isBefore(start) &&
              !transaction.completedAt.isAfter(now) &&
              transaction.completedAt.isBefore(end) &&
              !reservedIds.contains(transaction.transactionId),
        )
        .toList(growable: false);
  }

  bool recordSuccessfulDelivery(ParcelData parcel, {DateTime? completedAt}) {
    if (parcel.status != 'Delivered' ||
        transactions.value.any(
          (transaction) => transaction.trackingNumber == parcel.trackingNumber,
        )) {
      return false;
    }

    final EarningTransaction transaction = EarningTransaction(
      transactionId: parcel.trackingNumber,
      parcelNumber: parcel.parcelNumber,
      trackingNumber: parcel.trackingNumber,
      recipient: parcel.recipient,
      amount: earningPerDelivery,
      completedAt: completedAt ?? DateTime.now(),
    );
    transactions.value = [transaction, ...transactions.value];
    return true;
  }

  Future<PayoutClaim?> requestPayout(PayoutSchedule schedule) async {
    if (_creatingClaim) return null;
    _creatingClaim = true;

    try {
      await Future<void>.delayed(const Duration(milliseconds: 350));
      final DateTime now = DateTime.now();
      final List<EarningTransaction> included = eligibleTransactions(
        schedule,
        at: now,
      );
      if (included.isEmpty) return null;

      final DateTime start = _periodStart(schedule, now);
      final DateTime end = _periodEnd(schedule, start);
      final String nonce = Random.secure()
          .nextInt(0x7fffffff)
          .toRadixString(16);
      final PayoutClaim claim = PayoutClaim(
        referenceId: 'LUM-PAY-${now.microsecondsSinceEpoch}-$nonce',
        riderId: HardcodedRider.riderId,
        riderName: HardcodedRider.fullName,
        riderEmail: HardcodedRider.email,
        amount: _sum(included),
        schedule: schedule,
        periodStart: start,
        periodEnd: end.subtract(const Duration(microseconds: 1)),
        deliveries: included,
        requestedAt: now,
      );
      payouts.value = [claim, ...payouts.value];
      return claim;
    } finally {
      _creatingClaim = false;
    }
  }

  PayoutClaim? findClaim(String referenceId) {
    for (final claim in payouts.value) {
      if (claim.referenceId == referenceId) return claim;
    }
    return null;
  }

  Future<bool> confirmPayoutPaid(
    String referenceId, {
    required String staffIdentifier,
    String paymentMethod = 'Cash',
    String? paymentReference,
  }) async {
    if (_processingPayments.contains(referenceId)) return false;
    _processingPayments.add(referenceId);

    try {
      final PayoutClaim? current = findClaim(referenceId);
      if (current == null || current.status != PayoutStatus.pending) {
        return false;
      }

      await Future<void>.delayed(const Duration(milliseconds: 350));
      final PayoutClaim? latest = findClaim(referenceId);
      if (latest == null || latest.status != PayoutStatus.pending) {
        return false;
      }

      final List<PayoutClaim> updated = payouts.value.map((claim) {
        if (claim.referenceId != referenceId) return claim;
        return claim.copyWith(
          status: PayoutStatus.paid,
          paidAt: DateTime.now(),
          staffIdentifier: staffIdentifier,
          paymentMethod: paymentMethod,
          paymentReference: paymentReference,
        );
      }).toList();
      payouts.value = updated;
      return true;
    } finally {
      _processingPayments.remove(referenceId);
    }
  }

  bool rejectPayout(String referenceId) {
    final PayoutClaim? current = findClaim(referenceId);
    if (current == null || current.status != PayoutStatus.pending) return false;

    payouts.value = payouts.value.map((claim) {
      return claim.referenceId == referenceId
          ? claim.copyWith(status: PayoutStatus.rejected)
          : claim;
    }).toList();
    return true;
  }

  double _sum(Iterable<EarningTransaction> items) =>
      items.fold<double>(0, (total, item) => total + item.amount);

  double _sumClaims(PayoutStatus status) => payouts.value
      .where((claim) => claim.status == status)
      .fold<double>(0, (total, claim) => total + claim.amount);

  DateTime _periodStart(PayoutSchedule schedule, DateTime date) {
    switch (schedule) {
      case PayoutSchedule.daily:
        return DateTime(date.year, date.month, date.day);
      case PayoutSchedule.weekly:
        final DateTime day = DateTime(date.year, date.month, date.day);
        return day.subtract(Duration(days: day.weekday - DateTime.monday));
      case PayoutSchedule.monthly:
        return DateTime(date.year, date.month);
    }
  }

  DateTime _periodEnd(PayoutSchedule schedule, DateTime start) {
    switch (schedule) {
      case PayoutSchedule.daily:
        return start.add(const Duration(days: 1));
      case PayoutSchedule.weekly:
        return start.add(const Duration(days: 7));
      case PayoutSchedule.monthly:
        return DateTime(start.year, start.month + 1);
    }
  }
}

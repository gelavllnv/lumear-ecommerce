import 'package:flutter/foundation.dart';

import '../models/parcel_store.dart';
import '../models/payout_claim.dart';

/// Rider earnings shown in the app are built only from delivered shipments
/// returned by Laravel. Laravel currently has no rider payout history API.
class RiderEarningsService {
  RiderEarningsService._();

  static final RiderEarningsService instance = RiderEarningsService._();

  final ValueNotifier<List<EarningTransaction>> transactions =
      ValueNotifier<List<EarningTransaction>>([]);
  final ValueNotifier<List<PayoutClaim>> payouts =
      ValueNotifier<List<PayoutClaim>>([]);
  final ValueNotifier<PayoutSchedule> selectedSchedule =
      ValueNotifier<PayoutSchedule>(PayoutSchedule.daily);

  double get totalEarnings => _sum(transactions.value);

  double get todaysEarnings {
    final now = DateTime.now();
    final start = DateTime(now.year, now.month, now.day);
    final end = start.add(const Duration(days: 1));
    return _sum(
      transactions.value.where(
        (transaction) =>
            !transaction.completedAt.isBefore(start) &&
            transaction.completedAt.isBefore(end),
      ),
    );
  }

  int get successfulDeliveryCount => transactions.value.length;

  void syncFromRemoteParcels(List<ParcelData> parcels) {
    final delivered = parcels.where((parcel) => parcel.status == 'Delivered');
    transactions.value = delivered
        .map((parcel) {
          return EarningTransaction(
            transactionId:
                parcel.remoteShipmentId?.toString() ?? parcel.trackingNumber,
            parcelNumber: parcel.parcelNumber,
            trackingNumber: parcel.trackingNumber,
            recipient: parcel.recipient,
            amount: parcel.riderFee,
            completedAt:
                parcel.deliveredAt ??
                parcel.statusUpdatedAt ??
                DateTime.fromMillisecondsSinceEpoch(0),
          );
        })
        .toList(growable: false);
  }

  bool recordSuccessfulDelivery(ParcelData parcel, {DateTime? completedAt}) {
    if (parcel.status != 'Delivered' ||
        transactions.value.any(
          (transaction) => transaction.trackingNumber == parcel.trackingNumber,
        )) {
      return false;
    }

    transactions.value = [
      EarningTransaction(
        transactionId:
            parcel.remoteShipmentId?.toString() ?? parcel.trackingNumber,
        parcelNumber: parcel.parcelNumber,
        trackingNumber: parcel.trackingNumber,
        recipient: parcel.recipient,
        amount: parcel.riderFee,
        completedAt:
            completedAt ??
            parcel.deliveredAt ??
            parcel.statusUpdatedAt ??
            DateTime.now(),
      ),
      ...transactions.value,
    ];
    return true;
  }

  double _sum(Iterable<EarningTransaction> items) =>
      items.fold<double>(0, (total, item) => total + item.amount);
}

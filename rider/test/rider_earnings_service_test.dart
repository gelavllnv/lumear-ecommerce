import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_application_1/models/parcel_store.dart';
import 'package:flutter_application_1/models/payout_claim.dart';
import 'package:flutter_application_1/services/rider_earnings_service.dart';

void main() {
  late RiderEarningsService service;

  setUp(() {
    service = RiderEarningsService();
  });

  test(
    'credits a delivered parcel once and ignores failed or returned parcels',
    () {
      final DateTime completedAt = DateTime(2026, 10, 9, 10);
      final ParcelData delivered = _parcel('TRACK-1', 'Delivered');

      expect(
        service.recordSuccessfulDelivery(delivered, completedAt: completedAt),
        isTrue,
      );
      expect(
        service.recordSuccessfulDelivery(delivered, completedAt: completedAt),
        isFalse,
      );
      expect(
        service.recordSuccessfulDelivery(_parcel('TRACK-2', 'Delivery Failed')),
        isFalse,
      );
      expect(
        service.recordSuccessfulDelivery(_parcel('TRACK-3', 'Returned')),
        isFalse,
      );
      expect(
        service.recordSuccessfulDelivery(_parcel('TRACK-4', 'Cancelled')),
        isFalse,
      );
      expect(service.totalEarnings, 50);
    },
  );

  test(
    'payout reserves deliveries and a payment can only be confirmed once',
    () async {
      final DateTime now = DateTime.now();
      service.recordSuccessfulDelivery(
        _parcel('TRACK-4', 'Delivered'),
        completedAt: now,
      );

      final PayoutClaim? claim = await service.requestPayout(
        PayoutSchedule.daily,
      );
      expect(claim, isNotNull);
      expect(service.findClaim('invalid-reference'), isNull);
      expect(service.availableEarnings, 0);
      expect(service.pendingPayout, 50);

      expect(
        await service.confirmPayoutPaid(
          claim!.referenceId,
          staffIdentifier: 'LOGISTICS-DEMO',
        ),
        isTrue,
      );
      expect(
        await service.confirmPayoutPaid(
          claim.referenceId,
          staffIdentifier: 'LOGISTICS-DEMO',
        ),
        isFalse,
      );
      expect(service.pendingPayout, 0);
      expect(service.paidPayout, 50);
    },
  );

  test('rejected payout releases its deliveries for another request', () async {
    service.recordSuccessfulDelivery(
      _parcel('TRACK-5', 'Delivered'),
      completedAt: DateTime.now(),
    );

    final PayoutClaim claim = (await service.requestPayout(
      PayoutSchedule.daily,
    ))!;
    expect(service.rejectPayout(claim.referenceId), isTrue);
    expect(service.availableEarnings, 50);
    final PayoutClaim? replacement = await service.requestPayout(
      PayoutSchedule.daily,
    );
    expect(replacement, isNotNull);
    expect(replacement!.referenceId, isNot(claim.referenceId));
  });

  test('concurrent payment confirmations can only pay a claim once', () async {
    service.recordSuccessfulDelivery(
      _parcel('TRACK-10', 'Delivered'),
      completedAt: DateTime.now(),
    );
    final PayoutClaim claim = (await service.requestPayout(
      PayoutSchedule.daily,
    ))!;

    final results = await Future.wait([
      service.confirmPayoutPaid(
        claim.referenceId,
        staffIdentifier: 'LOGISTICS-DEMO-1',
      ),
      service.confirmPayoutPaid(
        claim.referenceId,
        staffIdentifier: 'LOGISTICS-DEMO-2',
      ),
    ]);

    expect(results.where((paid) => paid), hasLength(1));
    expect(service.paidPayout, 50);
  });

  test(
    'rejects simultaneous requests for the same eligible earnings',
    () async {
      service.recordSuccessfulDelivery(
        _parcel('TRACK-6', 'Delivered'),
        completedAt: DateTime.now(),
      );

      final results = await Future.wait([
        service.requestPayout(PayoutSchedule.daily),
        service.requestPayout(PayoutSchedule.daily),
      ]);

      expect(results.whereType<PayoutClaim>(), hasLength(1));
      expect(service.payouts.value, hasLength(1));
    },
  );

  test(
    'daily, weekly, and monthly schedules select their matching periods',
    () {
      final DateTime now = DateTime(2026, 10, 9, 12);
      service.recordSuccessfulDelivery(
        _parcel('TRACK-7', 'Delivered'),
        completedAt: DateTime(2026, 10, 9, 9),
      );
      service.recordSuccessfulDelivery(
        _parcel('TRACK-8', 'Delivered'),
        completedAt: DateTime(2026, 10, 8, 9),
      );
      service.recordSuccessfulDelivery(
        _parcel('TRACK-9', 'Delivered'),
        completedAt: DateTime(2026, 10, 1, 9),
      );

      expect(service.eligibleAmount(PayoutSchedule.daily, at: now), 50);
      expect(service.eligibleAmount(PayoutSchedule.weekly, at: now), 100);
      expect(service.eligibleAmount(PayoutSchedule.monthly, at: now), 150);
    },
  );
}

ParcelData _parcel(String trackingNumber, String status) {
  return ParcelData(
    parcelNumber: '#$trackingNumber',
    trackingNumber: trackingNumber,
    hub: 'Victoria Hub',
    recipient: 'Sample Recipient',
    phone: '09123456789',
    address: 'Victoria, Laguna',
    parcelType: 'Small Parcel',
    paymentMethod: 'Paid',
    codAmount: 0,
    riderFee: 50,
    distance: '1 km',
    customerLatitude: 14.2,
    customerLongitude: 121.3,
    qrValue: trackingNumber,
    status: status,
  );
}

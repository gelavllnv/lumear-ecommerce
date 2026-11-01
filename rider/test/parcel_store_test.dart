import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_application_1/models/parcel_store.dart';

void main() {
  setUp(() {
    ParcelStore.clearAll();
    ParcelStore.addSellerPickup(ParcelStore.demoSellerPickup);
  });

  test('awards one credit only once for a delivered parcel', () {
    ParcelStore.createParcelFromQr('successful-delivery');
    ParcelStore.updateStatus('Delivered');
    final completedParcel = ParcelStore.currentParcel.value!;

    expect(ParcelStore.awardSuccessfulDelivery(completedParcel), isTrue);
    expect(ParcelStore.awardSuccessfulDelivery(completedParcel), isFalse);
    expect(ParcelStore.totalEarnings.value, 50);
    expect(ParcelStore.earnedParcels.value, hasLength(1));
  });

  test('records a successful delivery that was not scanned', () {
    const completedParcel = ParcelData(
      parcelNumber: '#DEMO100',
      trackingNumber: 'DEMO100',
      hub: 'Victoria Hub',
      recipient: 'Rider Test',
      phone: '09123456789',
      address: 'Victoria, Laguna',
      parcelType: 'Small Parcel',
      paymentMethod: 'Paid',
      codAmount: 0,
      riderFee: 50,
      distance: '1 km',
      customerLatitude: 14.2,
      customerLongitude: 121.3,
      qrValue: 'DEMO100',
      status: 'Delivered',
    );

    expect(ParcelStore.awardSuccessfulDelivery(completedParcel), isTrue);
    expect(ParcelStore.parcels.value.single.trackingNumber, 'DEMO100');
    expect(ParcelStore.totalEarnings.value, 50);
  });

  test('does not award failed or returned parcels', () {
    ParcelStore.createParcelFromQr('failed-delivery');
    ParcelStore.updateStatus('Delivery Failed');
    expect(
      ParcelStore.awardSuccessfulDelivery(ParcelStore.currentParcel.value!),
      isFalse,
    );

    ParcelStore.createParcelFromQr('returned-delivery');
    ParcelStore.updateStatus('Returned');
    expect(
      ParcelStore.awardSuccessfulDelivery(ParcelStore.currentParcel.value!),
      isFalse,
    );

    expect(ParcelStore.totalEarnings.value, 0);
    expect(ParcelStore.earnedParcels.value, isEmpty);
  });

  test(
    'one seller pickup confirmation marks all expected parcels picked up',
    () {
      const String requestId = 'PU-0001';
      expect(
        ParcelStore.startSellerPickup(requestId),
        SellerPickupStartResult.started,
      );
      expect(
        ParcelStore.confirmSellerPickup(requestId),
        SellerPickupConfirmationResult.confirmed,
      );
      expect(ParcelStore.parcels.value, hasLength(2));
      expect(
        ParcelStore.parcels.value.map((parcel) => parcel.status),
        everyElement('Picked Up'),
      );
      expect(
        ParcelStore.confirmSellerPickup(requestId),
        SellerPickupConfirmationResult.alreadyConfirmed,
      );
      expect(
        ParcelStore.beginSellerPickupHeadingToSortingCenter(requestId),
        isTrue,
      );
      expect(
        ParcelStore.findSellerPickup(requestId)?.status,
        SellerPickupStatus.headingToSortingCenter,
      );
      expect(
        ParcelStore.parcels.value.map((parcel) => parcel.status),
        everyElement('Heading to Sorting Center'),
      );
    },
  );

  test('seller pickup rejects a batch with a parcel assigned elsewhere', () {
    ParcelStore.addSellerPickup(
      SellerPickupRequest(
        requestId: 'PU-0002',
        sellerName: 'Another Seller',
        pickupAddress: 'Another address',
        expectedParcels: [ParcelStore.demoSellerPickup.expectedParcels.first],
      ),
    );
    ParcelStore.startSellerPickup('PU-0001');

    expect(
      ParcelStore.confirmSellerPickup('PU-0001'),
      SellerPickupConfirmationResult.parcelAssignedElsewhere,
    );
    expect(ParcelStore.parcels.value, isEmpty);
    expect(
      ParcelStore.findSellerPickup('PU-0001')?.status,
      SellerPickupStatus.inProgress,
    );
  });

  test('seller pickup contact and location survive status changes', () {
    expect(ParcelStore.demoSellerPickup.sellerPhone, '0912 345 6789');
    expect(ParcelStore.demoSellerPickup.pickupLatitude, 14.2277);
    expect(ParcelStore.demoSellerPickup.pickupLongitude, 121.3284);

    ParcelStore.startSellerPickup('PU-0001');

    final SellerPickupRequest? updated = ParcelStore.findSellerPickup(
      'PU-0001',
    );
    expect(updated?.sellerPhone, '0912 345 6789');
    expect(updated?.pickupLatitude, 14.2277);
    expect(updated?.pickupLongitude, 121.3284);
  });

  test('seller pickup confirmation rechecks parcels collected elsewhere', () {
    const String requestId = 'PU-0001';
    ParcelStore.startSellerPickup(requestId);
    ParcelStore.parcels.value = [
      ParcelStore.demoSellerPickup.expectedParcels.first.copyWith(
        status: 'Delivered',
      ),
    ];

    expect(
      ParcelStore.confirmSellerPickup(requestId),
      SellerPickupConfirmationResult.parcelAlreadyCollected,
    );
    expect(ParcelStore.parcels.value.single.status, 'Delivered');
  });
}

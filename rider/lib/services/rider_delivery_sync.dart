import '../models/parcel_store.dart';
import 'rider_api_service.dart';
import 'rider_earnings_service.dart';

/// Maps Laravel shipment records into the existing Rider dashboard model.
abstract final class RiderDeliverySync {
  static Future<void> refresh() async {
    final shipments = await RiderApiService.shipments();
    final parcels = shipments.map(_toParcel).toList(growable: false);
    ParcelStore.replaceRemoteParcels(parcels);
    RiderEarningsService.instance.syncFromRemoteParcels(parcels);
  }

  static ParcelData _toParcel(Map<String, dynamic> shipment) {
    final updatedAt = DateTime.tryParse(
      shipment['updated_at']?.toString() ?? '',
    );
    final pickedUpAt = DateTime.tryParse(
      shipment['picked_up_at']?.toString() ?? '',
    );
    final status = _status(shipment['status']?.toString());
    final addressValue = shipment['shipping_address'];
    final address = addressValue is Map
        ? [
                addressValue['house_unit'],
                addressValue['line1'],
                addressValue['street'],
                addressValue['barangay'],
                addressValue['city'] ?? addressValue['municipality'],
                addressValue['province'],
              ]
              .whereType<String>()
              .where((part) => part.trim().isNotEmpty)
              .join(', ')
        : addressValue?.toString() ?? '';
    final codMinor = shipment['cod_amount_minor'];
    final codAmount = codMinor is num
        ? codMinor.toDouble() / 100
        : double.tryParse(codMinor?.toString() ?? '') != null
        ? double.parse(codMinor.toString()) / 100
        : 0.0;
    final feeMinor = shipment['fee_minor'];
    final shippingFee = feeMinor is num
        ? feeMinor.toDouble() / 100
        : double.tryParse(feeMinor?.toString() ?? '') != null
        ? double.parse(feeMinor.toString()) / 100
        : 0.0;
    final latitude = addressValue is Map
        ? _toDouble(addressValue['latitude'] ?? addressValue['lat'])
        : 0.0;
    final longitude = addressValue is Map
        ? _toDouble(addressValue['longitude'] ?? addressValue['lng'])
        : 0.0;

    return ParcelData(
      parcelNumber:
          '#${shipment['order_reference'] ?? shipment['tracking_code']}',
      trackingNumber: shipment['tracking_code']?.toString() ?? '',
      hub: shipment['seller_name']?.toString() ?? 'Lumear Logistics',
      recipient: shipment['buyer_name']?.toString() ?? 'Customer',
      phone: shipment['buyer_phone']?.toString() ?? '',
      address: address,
      parcelType: 'Marketplace order',
      paymentMethod: shipment['payment_method']?.toString() == 'cod'
          ? 'Cash on Delivery'
          : 'Paid Online',
      codAmount: codAmount,
      riderFee: shippingFee * 0.8,
      shippingFee: shippingFee,
      distance: '',
      customerLatitude: latitude,
      customerLongitude: longitude,
      qrValue: shipment['tracking_code']?.toString() ?? '',
      status: status,
      remoteShipmentId: shipment['id'] is num
          ? (shipment['id'] as num).toInt()
          : int.tryParse(shipment['id']?.toString() ?? ''),
      deliveredAt:
          status == 'Delivered' || status == 'Awaiting Buyer Confirmation'
          ? updatedAt
          : null,
      statusUpdatedAt: updatedAt,
      pickedUpAt: pickedUpAt,
      failedAttempts: int.tryParse(shipment['attempts']?.toString() ?? '') ?? 0,
      proofImagePath: shipment['proof_image_url'] == null
          ? null
          : RiderApiService.apiUrl(shipment['proof_image_url'].toString()),
      receivedBy: shipment['received_by']?.toString(),
    );
  }

  static String _status(String? value) => switch (value) {
    'assigned' => 'Pending',
    'dispatched' => 'Pending',
    'picked_up' => 'Parcel Picked Up',
    'in_transit' => 'In Transit',
    'out_for_delivery' => 'Out for Delivery',
    'awaiting_buyer_confirmation' => 'Awaiting Buyer Confirmation',
    'delivered' => 'Delivered',
    'failed' => 'Delivery Failed',
    'returned' => 'Returned',
    _ => 'Pending',
  };

  static double _toDouble(dynamic value) => value is num
      ? value.toDouble()
      : double.tryParse(value?.toString() ?? '') ?? 0;
}

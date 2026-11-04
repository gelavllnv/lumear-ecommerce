import '../models/parcel_store.dart';
import 'rider_api_service.dart';

/// Maps Laravel shipment records into the existing Rider dashboard model.
abstract final class RiderDeliverySync {
  static Future<void> refresh() async {
    final shipments = await RiderApiService.shipments();
    ParcelStore.replaceRemoteParcels(
      shipments.map(_toParcel).toList(growable: false),
    );
  }

  static ParcelData _toParcel(Map<String, dynamic> shipment) {
    final addressValue = shipment['shipping_address'];
    final address = addressValue is Map
        ? [
            addressValue['house_unit'],
            addressValue['street'],
            addressValue['barangay'],
            addressValue['city'] ?? addressValue['municipality'],
            addressValue['province'],
          ].whereType<String>().where((part) => part.trim().isNotEmpty).join(', ')
        : addressValue?.toString() ?? '';
    final codMinor = shipment['cod_amount_minor'];
    final codAmount = codMinor is num
        ? codMinor.toDouble() / 100
        : double.tryParse(codMinor?.toString() ?? '') != null
        ? double.parse(codMinor.toString()) / 100
        : 0.0;

    return ParcelData(
      parcelNumber: '#${shipment['order_reference'] ?? shipment['tracking_code']}',
      trackingNumber: shipment['tracking_code']?.toString() ?? '',
      hub: shipment['seller_name']?.toString() ?? 'Lumear Logistics',
      recipient: shipment['buyer_name']?.toString() ?? 'Customer',
      phone: shipment['buyer_phone']?.toString() ?? '',
      address: address,
      parcelType: 'Marketplace order',
      paymentMethod: codAmount > 0 ? 'Cash on Delivery' : 'Paid',
      codAmount: codAmount,
      riderFee: 0,
      distance: '',
      customerLatitude: 0,
      customerLongitude: 0,
      qrValue: shipment['tracking_code']?.toString() ?? '',
      status: _status(shipment['status']?.toString()),
      remoteShipmentId: shipment['id'] is num
          ? (shipment['id'] as num).toInt()
          : int.tryParse(shipment['id']?.toString() ?? ''),
      statusUpdatedAt: DateTime.tryParse(shipment['updated_at']?.toString() ?? ''),
    );
  }

  static String _status(String? value) => switch (value) {
    'assigned' => 'Pending',
    'picked_up' => 'Parcel Picked Up',
    'in_transit' => 'Out for Delivery',
    'out_for_delivery' => 'Out for Delivery',
    'delivered' => 'Delivered',
    'failed' => 'Delivery Failed',
    'returned' => 'Returned',
    _ => 'Pending',
  };
}

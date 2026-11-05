import 'api_service.dart';

/// Money is stored as integer centavos to avoid floating point rounding errors.
abstract final class MarketplacePolicy {
  static const lowStockThreshold = 5;
  static const shippingCentavos = 5000;
  static const shippingPesos = shippingCentavos / 100;
  static const adminSharePercent = 10;
  static const ownerSharePercent = 10;
  static const commissionPercent = adminSharePercent + ownerSharePercent;
  static const sellerSharePercent = 100 - commissionPercent;

  static int commissionFor(int subtotal) =>
      (subtotal * commissionPercent + 50) ~/ 100;

  static int adminShareFor(int subtotal) =>
      (subtotal * adminSharePercent + 50) ~/ 100;
  // Assign the rounding remainder to the owner so every centavo reconciles.
  static int ownerShareFor(int subtotal) =>
      commissionFor(subtotal) - adminShareFor(subtotal);
  static int sellerShareFor(int subtotal) => subtotal - commissionFor(subtotal);

  /// Never replace an authoritative server total with a client-side estimate.
  static void validateShippingQuote(Map<String, dynamic> quote) {
    final stores = quote['stores'] as List? ?? [];
    if (stores.isEmpty ||
        stores.any((store) => store['shipping'] != shippingCentavos)) {
      throw const ApiException(
        'Shipping must be ₱50.00 per shop order. The shop needs to update its shipping rate before checkout.',
      );
    }
  }
}

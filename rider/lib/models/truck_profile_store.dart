import 'package:flutter/foundation.dart';
import 'package:flutter_application_1/models/truck_rider_profile.dart';

/// Shared, in-memory copy of the truck rider's latest profile.
///
/// Both Personal Information and Vehicle Information read from and write to
/// this store, so a change saved in one screen shows up in the other
/// without depending on how the parent screen passes the profile around.
class TruckProfileStore {
  TruckProfileStore._();

  static final ValueNotifier<TruckRiderProfile?> current =
      ValueNotifier<TruckRiderProfile?>(null);

  /// Returns the stored profile if it belongs to the same rider,
  /// otherwise returns [fallback].
  static TruckRiderProfile resolve(TruckRiderProfile fallback) {
    final stored = current.value;
    if (stored != null && stored.riderId == fallback.riderId) {
      return stored;
    }
    return fallback;
  }

  static void update(TruckRiderProfile profile) {
    current.value = profile;
  }

  /// Call on logout so the next rider never sees old data.
  static void clear() {
    current.value = null;
  }
}

import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import 'package:flutter_application_1/models/parcel_store.dart';
import 'package:flutter_application_1/screens/sorting_center_handoff_screen.dart';

class SellerPickupDetailsScreen extends StatefulWidget {
  final String requestId;

  const SellerPickupDetailsScreen({super.key, required this.requestId});

  @override
  State<SellerPickupDetailsScreen> createState() =>
      _SellerPickupDetailsScreenState();
}

class _SellerPickupDetailsScreenState extends State<SellerPickupDetailsScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);
  static const Color success = Color(0xFF2E7D32);
  static const Color danger = Color(0xFFB3261E);

  bool _isWorking = false;
  bool _arrivedAtSeller = false;
  int? _lastCenteredStage;

  final ScrollController _stageScrollController = ScrollController();

  // ============================================================
  // MAP / RIDER LOCATION / ROUTE
  // ============================================================

  // TODO: replace with the real seller coordinates once your
  // SellerPickupRequest model has them, e.g.
  //   LatLng(pickup.latitude, pickup.longitude)
  static const LatLng _sampleSellerLocation = LatLng(14.0648, 121.3310);

  LatLng get _sellerLocation => _sampleSellerLocation;

  final MapController _mapController = MapController();

  LatLng? _riderLocation;
  StreamSubscription<Position>? _positionSubscription;
  bool _didFitInitialView = false;

  List<LatLng> _routePoints = [];
  bool _isLoadingRoute = false;

  @override
  void initState() {
    super.initState();
    _startRiderLocationTracking();
  }

  @override
  void dispose() {
    _positionSubscription?.cancel();
    _stageScrollController.dispose();
    super.dispose();
  }

  Future<void> _startRiderLocationTracking() async {
    final bool serviceEnabled = await Geolocator.isLocationServiceEnabled();

    if (!mounted) return;

    if (!serviceEnabled) {
      _showMessage('Please turn on your phone location.', isError: true);
      return;
    }

    LocationPermission permission = await Geolocator.checkPermission();

    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (!mounted) return;

    if (permission == LocationPermission.denied) {
      _showMessage(
        'Location permission is required to show the rider location.',
        isError: true,
      );
      return;
    }

    if (permission == LocationPermission.deniedForever) {
      _showMessage(
        'Location permission is permanently denied. Enable it in app settings.',
        isError: true,
      );
      return;
    }

    const LocationSettings locationSettings = LocationSettings(
      accuracy: LocationAccuracy.high,
      distanceFilter: 5,
    );

    await _positionSubscription?.cancel();

    _positionSubscription =
        Geolocator.getPositionStream(locationSettings: locationSettings).listen(
          (Position position) {
            if (!mounted) return;

            final LatLng newRiderLocation = LatLng(
              position.latitude,
              position.longitude,
            );

            setState(() {
              _riderLocation = newRiderLocation;
            });

            // Fit rider + seller in view only on the first GPS fix.
            if (!_didFitInitialView) {
              _didFitInitialView = true;

              WidgetsBinding.instance.addPostFrameCallback((_) {
                if (!mounted || _riderLocation == null) return;
                try {
                  _mapController.fitCamera(
                    CameraFit.bounds(
                      bounds: LatLngBounds.fromPoints([
                        _riderLocation!,
                        _sellerLocation,
                      ]),
                      padding: const EdgeInsets.all(48),
                    ),
                  );
                } catch (_) {
                  // Map not attached yet. Ignore.
                }
              });
            }
          },
          onError: (Object error) {
            if (!mounted) return;
            _showMessage('Unable to read the rider location.', isError: true);
          },
        );
  }

  Future<List<LatLng>?> _fetchRoadRoute(LatLng from, LatLng to) async {
    // OSRM public demo server (free, no API key). Fine for a prototype;
    // for production, self-host OSRM or use a paid routing provider.
    final Uri url = Uri.parse(
      'https://router.project-osrm.org/route/v1/driving/'
      '${from.longitude},${from.latitude};${to.longitude},${to.latitude}'
      '?overview=full&geometries=geojson',
    );

    try {
      final http.Response response = await http
          .get(url)
          .timeout(const Duration(seconds: 10));

      if (response.statusCode != 200) return null;

      final Map<String, dynamic> data =
          jsonDecode(response.body) as Map<String, dynamic>;

      final List<dynamic>? routes = data['routes'] as List<dynamic>?;
      if (routes == null || routes.isEmpty) return null;

      final List<dynamic> coordinates =
          (routes.first as Map<String, dynamic>)['geometry']['coordinates']
              as List<dynamic>;

      // GeoJSON order is [lng, lat].
      return coordinates.map<LatLng>((dynamic c) {
        final List<dynamic> pair = c as List<dynamic>;
        return LatLng((pair[1] as num).toDouble(), (pair[0] as num).toDouble());
      }).toList();
    } catch (e) {
      debugPrint('ROUTE ERROR: $e');
      return null;
    }
  }

  Future<void> _navigateToSeller() async {
    // Toggle: tapping again while a route is showing clears it.
    if (_routePoints.isNotEmpty) {
      setState(() => _routePoints = []);
      return;
    }

    if (_isLoadingRoute) return;

    final LatLng? rider = _riderLocation;

    if (rider == null) {
      _showMessage('Waiting for the rider GPS location.');
      return;
    }

    final LatLng destination = _sellerLocation;

    setState(() => _isLoadingRoute = true);

    List<LatLng>? route = await _fetchRoadRoute(rider, destination);

    if (!mounted) return;

    bool usedFallback = false;

    // Fallback: straight line so the rider still has a visual guide.
    if (route == null || route.length < 2) {
      route = [rider, destination];
      usedFallback = true;
    }

    setState(() {
      _routePoints = route!;
      _isLoadingRoute = false;
    });

    try {
      _mapController.fitCamera(
        CameraFit.bounds(
          bounds: LatLngBounds.fromPoints([rider, ...route]),
          padding: const EdgeInsets.all(48),
        ),
      );
    } catch (_) {
      // Map not attached. Ignore.
    }

    _showMessage(
      usedFallback
          ? 'Couldn\'t load the road route. Showing a straight line to the seller.'
          : 'Route to the seller is ready.',
      isError: usedFallback,
    );
  }

  // ============================================================
  // HELPERS
  // ============================================================

  void _showMessage(String message, {bool isError = false}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          backgroundColor: isError ? danger : darkMaroon,
          content: Text(message),
        ),
      );
  }

  Future<void> _runAction(Future<void> Function() action) async {
    if (_isWorking) return;

    setState(() => _isWorking = true);

    try {
      await action();
    } catch (_) {
      if (mounted) {
        _showMessage('Something went wrong. Please try again.', isError: true);
      }
    } finally {
      if (mounted) {
        setState(() => _isWorking = false);
      }
    }
  }

  Future<void> _callSeller(String? phone) async {
    final dialable = (phone ?? '').trim().replaceAll(RegExp(r'[^0-9+]'), '');

    if (dialable.isEmpty) {
      _showMessage('Seller phone number is unavailable.', isError: true);
      return;
    }

    try {
      final launched = await launchUrl(
        Uri(scheme: 'tel', path: dialable),
        mode: LaunchMode.externalApplication,
      );

      if (!launched && mounted) {
        _showMessage('Unable to open the phone dialer.', isError: true);
      }
    } catch (_) {
      if (mounted) {
        _showMessage('Unable to call seller.', isError: true);
      }
    }
  }

  Future<void> _startPickup(SellerPickupRequest pickup) async {
    await _runAction(() async {
      final result = ParcelStore.startSellerPickup(pickup.requestId);

      if (result != SellerPickupStartResult.started) {
        _showMessage(
          'This pickup is not currently assigned to this rider.',
          isError: true,
        );
        return;
      }

      if (!mounted) return;

      setState(() => _arrivedAtSeller = false);

      _showMessage('Pickup started. Head to the seller.');
    });
  }

  void _confirmArrival() {
    setState(() {
      _arrivedAtSeller = true;
      _routePoints = [];
    });

    _showMessage('Arrival recorded. Verify the parcels with the seller.');
  }

  Future<void> _confirmPickup(SellerPickupRequest pickup) async {
    if (!_arrivedAtSeller) {
      _showMessage('Confirm your arrival at the seller first.', isError: true);
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return AlertDialog(
          backgroundColor: cream,
          title: const Text(
            'Confirm Parcel Collection',
            style: TextStyle(color: darkMaroon, fontWeight: FontWeight.bold),
          ),
          content: Text(
            'Have you physically collected all '
            '${pickup.expectedParcels.length} listed parcels '
            'from ${pickup.sellerName}?',
            style: const TextStyle(color: brownBeige),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel', style: TextStyle(color: brownBeige)),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              style: ElevatedButton.styleFrom(
                backgroundColor: darkMaroon,
                foregroundColor: Colors.white,
              ),
              child: const Text('Confirm Collection'),
            ),
          ],
        );
      },
    );

    if (confirmed != true || !mounted) return;

    await _runAction(() async {
      final result = ParcelStore.confirmSellerPickup(pickup.requestId);

      if (result != SellerPickupConfirmationResult.confirmed) {
        final message = switch (result) {
          SellerPickupConfirmationResult.pickupNotFound =>
            'Pickup request not found.',

          SellerPickupConfirmationResult.pickupNotActive =>
            'Start the pickup before confirming collection.',

          SellerPickupConfirmationResult.parcelAssignedElsewhere =>
            'A parcel belongs to another pickup request.',

          SellerPickupConfirmationResult.parcelsNotAllScanned =>
            'Scan every expected waybill before confirming this pickup.',

          SellerPickupConfirmationResult.parcelAlreadyCollected =>
            'A parcel has already been collected or processed.',

          SellerPickupConfirmationResult.alreadyConfirmed =>
            'This pickup has already been confirmed.',

          SellerPickupConfirmationResult.confirmed => '',
        };

        _showMessage(message, isError: true);
        return;
      }

      // Seller is no longer the destination, so clear the old route.
      if (mounted) {
        setState(() => _routePoints = []);
      }

      _showMessage('Collection confirmed. Continue to the handoff page.');
    });
  }

  Future<void> _openHandoffPage(SellerPickupRequest pickup) async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (_) => SortingCenterHandoffScreen(requestId: pickup.requestId),
      ),
    );
  }

  // ============================================================
  // UI PIECES
  // ============================================================

  Widget _statusBadge(SellerPickupStatus status) {
    final color = switch (status) {
      SellerPickupStatus.pendingAssignment => brownBeige,
      SellerPickupStatus.assigned => brownBeige,
      SellerPickupStatus.inProgress => maroon,
      SellerPickupStatus.awaitingSellerVerification => brownBeige,
      SellerPickupStatus.pickupVerified => success,
      SellerPickupStatus.pickupDisputed => Colors.red,
      SellerPickupStatus.pickupFailed => Colors.red,
      SellerPickupStatus.pickedUp => success,
      SellerPickupStatus.headingToSortingCenter => success,
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        status.label,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }

  Widget _infoRow({
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 15),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: brownBeige, size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(color: brownBeige, fontSize: 11),
                ),
                const SizedBox(height: 4),
                Text(
                  value.trim().isEmpty ? 'Not provided' : value,
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _timeline(SellerPickupRequest pickup) {
    final currentStage = switch (pickup.status) {
      SellerPickupStatus.pendingAssignment => 0,
      SellerPickupStatus.assigned => 1,
      SellerPickupStatus.inProgress => _arrivedAtSeller ? 3 : 2,
      SellerPickupStatus.awaitingSellerVerification => 4,
      SellerPickupStatus.pickupVerified => 5,
      SellerPickupStatus.pickedUp => 5,
      SellerPickupStatus.headingToSortingCenter => 6,
      SellerPickupStatus.pickupDisputed => 4,
      SellerPickupStatus.pickupFailed => 2,
    };

    const stages = [
      ('Pending Assignment', Icons.schedule_rounded),
      ('Pickup Assigned', Icons.assignment_turned_in_outlined),
      ('Heading to Seller', Icons.navigation_rounded),
      ('Arrived at Seller', Icons.storefront_rounded),
      ('Confirm Collection', Icons.inventory_2_outlined),
      ('Picked Up', Icons.inventory_2_rounded),
      ('Sorting Center', Icons.warehouse_outlined),
    ];

    if (_lastCenteredStage != currentStage) {
      _lastCenteredStage = currentStage;

      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted || !_stageScrollController.hasClients) return;

        final position = _stageScrollController.position;
        final target =
            (currentStage * 112.0 + 17 - position.viewportDimension / 2).clamp(
              0.0,
              position.maxScrollExtent,
            );

        _stageScrollController.animateTo(
          target,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      });
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: lightBeige),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'PICKUP PROGRESS',
                style: TextStyle(
                  color: darkMaroon,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                '${currentStage + 1}/${stages.length}',
                style: const TextStyle(
                  color: maroon,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          SingleChildScrollView(
            controller: _stageScrollController,
            scrollDirection: Axis.horizontal,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (int i = 0; i < stages.length; i++)
                  SizedBox(
                    width: 112,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Container(
                              width: 34,
                              height: 34,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: i < currentStage
                                    ? success
                                    : i == currentStage
                                    ? darkMaroon
                                    : cream,
                              ),
                              child: Icon(
                                i < currentStage
                                    ? Icons.check_rounded
                                    : stages[i].$2,
                                size: 17,
                                color: i <= currentStage
                                    ? Colors.white
                                    : brownBeige,
                              ),
                            ),
                            if (i < stages.length - 1)
                              Expanded(
                                child: Container(
                                  height: 2,
                                  margin: const EdgeInsets.only(left: 6),
                                  color: i < currentStage
                                      ? success
                                      : lightBeige,
                                ),
                              ),
                          ],
                        ),
                        const SizedBox(height: 7),
                        Text(
                          stages[i].$1,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: i <= currentStage ? darkMaroon : brownBeige,
                            fontSize: 11,
                            fontWeight: i == currentStage
                                ? FontWeight.w800
                                : FontWeight.w500,
                          ),
                        ),
                        if (i == currentStage)
                          const Text(
                            'CURRENT',
                            style: TextStyle(
                              color: maroon,
                              fontSize: 9,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _primaryButton({
    required String label,
    required IconData icon,
    required VoidCallback? onPressed,
  }) {
    return SizedBox(
      width: double.infinity,
      height: 52,
      child: ElevatedButton.icon(
        onPressed: _isWorking ? null : onPressed,
        icon: Icon(icon),
        label: Text(label, style: const TextStyle(fontWeight: FontWeight.w800)),
        style: ElevatedButton.styleFrom(
          backgroundColor: darkMaroon,
          foregroundColor: Colors.white,
          disabledBackgroundColor: lightBeige,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(13),
          ),
        ),
      ),
    );
  }

  Widget _actionSection(SellerPickupRequest pickup) {
    switch (pickup.status) {
      case SellerPickupStatus.pendingAssignment:
        return _primaryButton(
          label: 'Waiting for Assignment',
          icon: Icons.schedule_rounded,
          onPressed: null,
        );

      case SellerPickupStatus.assigned:
        return _primaryButton(
          label: 'Start Pickup — Head to Seller',
          icon: Icons.navigation_rounded,
          onPressed: () => _startPickup(pickup),
        );

      case SellerPickupStatus.inProgress:
        if (!_arrivedAtSeller) {
          return _primaryButton(
            label: 'Arrived at Seller',
            icon: Icons.storefront_rounded,
            onPressed: _confirmArrival,
          );
        }

        return _primaryButton(
          label: 'Confirm Parcel Collection',
          icon: Icons.inventory_rounded,
          onPressed: () => _confirmPickup(pickup),
        );

      case SellerPickupStatus.pickedUp:
        return _primaryButton(
          label: 'Continue to Sorting Center',
          icon: Icons.warehouse_outlined,
          onPressed: () => _openHandoffPage(pickup),
        );

      case SellerPickupStatus.headingToSortingCenter:
        return _primaryButton(
          label: 'Open Sorting Center Handoff',
          icon: Icons.arrow_forward_rounded,
          onPressed: () => _openHandoffPage(pickup),
        );
      case SellerPickupStatus.awaitingSellerVerification:
        return _primaryButton(
          label: 'Waiting for Seller Verification',
          icon: Icons.hourglass_top,
          onPressed: null,
        );

      case SellerPickupStatus.pickupVerified:
        return _primaryButton(
          label: 'Continue to Sorting Center',
          icon: Icons.local_shipping_outlined,
          onPressed: () => _openHandoffPage(pickup),
        );

      case SellerPickupStatus.pickupDisputed:
        return _primaryButton(
          label: 'Pickup Disputed',
          icon: Icons.warning_amber_rounded,
          onPressed: null,
        );

      case SellerPickupStatus.pickupFailed:
        return _primaryButton(
          label: 'Pickup Failed',
          icon: Icons.error_outline,
          onPressed: null,
        );
    }
  }

  // ============================================================
  // MAP + CALL / NAVIGATE BAR
  // ============================================================

  Widget _buildMap() {
    return SizedBox(
      height: 220,
      width: double.infinity,
      child: FlutterMap(
        mapController: _mapController,
        options: MapOptions(initialCenter: _sellerLocation, initialZoom: 15),
        children: [
          TileLayer(
            urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            userAgentPackageName: 'com.example.flutter_application_1',
          ),

          // Route line (drawn under the markers).
          if (_routePoints.length >= 2)
            PolylineLayer(
              polylines: [
                Polyline(
                  points: _routePoints,
                  strokeWidth: 5,
                  color: darkMaroon,
                  borderStrokeWidth: 2,
                  borderColor: Colors.white,
                ),
              ],
            ),

          MarkerLayer(
            markers: [
              Marker(
                point: _sellerLocation,
                width: 55,
                height: 55,
                child: Container(
                  decoration: BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.15),
                        blurRadius: 8,
                      ),
                    ],
                  ),
                  child: const Icon(
                    Icons.storefront_rounded,
                    color: darkMaroon,
                    size: 32,
                  ),
                ),
              ),

              if (_riderLocation != null)
                Marker(
                  point: _riderLocation!,
                  width: 62,
                  height: 62,
                  child: Container(
                    decoration: BoxDecoration(
                      color: darkMaroon,
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white, width: 3),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.25),
                          blurRadius: 10,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: const Icon(
                      Icons.delivery_dining,
                      color: Colors.white,
                      size: 34,
                    ),
                  ),
                ),
            ],
          ),

          RichAttributionWidget(
            attributions: [TextSourceAttribution('OpenStreetMap contributors')],
          ),
        ],
      ),
    );
  }

  Widget _buildCallNavigateBar(SellerPickupRequest pickup) {
    return Container(
      padding: const EdgeInsets.fromLTRB(18, 12, 18, 12),
      decoration: BoxDecoration(
        color: cream,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: OutlinedButton.icon(
              onPressed: () => _callSeller(pickup.sellerPhone),
              icon: const Icon(Icons.phone_outlined, size: 18),
              label: const Text('Call'),
              style: OutlinedButton.styleFrom(
                foregroundColor: darkMaroon,
                backgroundColor: Colors.white.withValues(alpha: 0.55),
                side: BorderSide.none,
                minimumSize: const Size(double.infinity, 46),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(24),
                ),
              ),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: OutlinedButton.icon(
              // Always enabled (only disabled while a route is loading).
              onPressed: _isLoadingRoute ? null : _navigateToSeller,
              icon: _isLoadingRoute
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: darkMaroon,
                      ),
                    )
                  : Icon(
                      _routePoints.isEmpty
                          ? Icons.directions_outlined
                          : Icons.close_rounded,
                      size: 18,
                    ),
              label: Text(
                _isLoadingRoute
                    ? 'Routing...'
                    : _routePoints.isEmpty
                    ? 'Navigate'
                    : 'Clear Route',
              ),
              style: OutlinedButton.styleFrom(
                foregroundColor: darkMaroon,
                backgroundColor: Colors.white.withValues(alpha: 0.55),
                side: BorderSide.none,
                minimumSize: const Size(double.infinity, 46),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(24),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPickup(SellerPickupRequest pickup) {
    return Column(
      children: [
        // Fixed map + Call/Navigate bar (always visible).
        _buildMap(),
        _buildCallNavigateBar(pickup),

        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(18, 16, 18, 28),
            children: [
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: lightBeige.withValues(alpha: 0.7)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'SELLER PICKUP',
                            style: TextStyle(
                              color: maroon,
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.5,
                            ),
                          ),
                        ),
                        _statusBadge(pickup.status),
                      ],
                    ),
                    const SizedBox(height: 14),
                    Text(
                      pickup.sellerName,
                      style: const TextStyle(
                        color: darkMaroon,
                        fontSize: 21,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 20),
                    _infoRow(
                      icon: Icons.confirmation_number_outlined,
                      label: 'Pickup Request ID',
                      value: pickup.requestId,
                    ),
                    _infoRow(
                      icon: Icons.storefront_outlined,
                      label: 'Seller',
                      value: pickup.sellerName,
                    ),
                    _infoRow(
                      icon: Icons.phone_outlined,
                      label: 'Seller Contact',
                      value: pickup.sellerPhone ?? '',
                    ),
                    _infoRow(
                      icon: Icons.location_on_outlined,
                      label: 'Pickup Address',
                      value: pickup.pickupAddress,
                    ),
                    _infoRow(
                      icon: Icons.inventory_2_outlined,
                      label: 'Expected Parcels',
                      value: '${pickup.expectedParcels.length} parcels',
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              _timeline(pickup),
              const SizedBox(height: 20),
              const Text(
                'EXPECTED PARCELS',
                style: TextStyle(
                  color: darkMaroon,
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 10),
              if (pickup.expectedParcels.isEmpty)
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Text(
                    'No expected parcels are listed for this request.',
                    style: TextStyle(color: brownBeige),
                  ),
                ),
              ...pickup.expectedParcels.map((parcel) {
                return Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: lightBeige),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.inventory_2_outlined, color: darkMaroon),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          parcel.trackingNumber,
                          style: const TextStyle(
                            color: darkMaroon,
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      const Text(
                        'Expected',
                        style: TextStyle(color: brownBeige, fontSize: 11),
                      ),
                    ],
                  ),
                );
              }),
              const SizedBox(height: 12),
              _actionSection(pickup),
            ],
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: cream,
        foregroundColor: darkMaroon,
        title: const Text('Seller Pickup Details'),
      ),
      body: ValueListenableBuilder<List<SellerPickupRequest>>(
        valueListenable: ParcelStore.sellerPickups,
        builder: (context, pickups, _) {
          SellerPickupRequest? pickup;

          for (final item in pickups) {
            if (item.requestId == widget.requestId) {
              pickup = item;
              break;
            }
          }

          if (pickup == null) {
            return const Center(child: Text('Pickup request not found.'));
          }

          return _buildPickup(pickup);
        },
      ),
    );
  }
}

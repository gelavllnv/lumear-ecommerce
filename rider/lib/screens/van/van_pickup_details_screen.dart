import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../models/van_pickup_request.dart';

class VanPickupDetailsScreen extends StatefulWidget {
  final VanPickupRequest pickup;
  final VoidCallback onChanged;

  /// Optional exact seller coordinates from your backend.
  /// When provided, the screen skips address lookup and pins this location.
  final LatLng? sellerLocation;

  const VanPickupDetailsScreen({
    super.key,
    required this.pickup,
    required this.onChanged,
    this.sellerLocation,
  });

  @override
  State<VanPickupDetailsScreen> createState() => _VanPickupDetailsScreenState();
}

class _VanPickupDetailsScreenState extends State<VanPickupDetailsScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color beige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color pageBg = Color(0xFFFFFAF6);
  static const Color border = Color(0xFFEDE1D8);
  static const Color amberText = Color(0xFF815C13);

  final MapController _mapController = MapController();
  final ScrollController _scrollController = ScrollController();

  // Rider location
  Position? _riderPosition;
  StreamSubscription<Position>? _positionSubscription;
  bool _locationLoading = true;
  bool _locationUnavailable = false;
  bool _mapReady = false;

  // Seller location + route
  bool _showSeller = false;
  bool _routeLoading = false;
  String? _routeError;
  LatLng? _sellerLocation;
  bool _sellerApproximate = false;
  bool _routeIsEstimate = false;
  List<LatLng> _routePoints = [];
  double? _routeDistanceMeters;
  double? _routeDurationSeconds;

  VanPickupRequest get pickup => widget.pickup;

  // ---------------------------------------------------------------------------
  // Lifecycle + rider location
  // ---------------------------------------------------------------------------

  @override
  void initState() {
    super.initState();
    _sellerLocation = widget.sellerLocation;
    _initializeLocation();
  }

  Future<void> _initializeLocation() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        _setLocationUnavailable();
        return;
      }

      var permission = await Geolocator.checkPermission();

      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }

      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        _setLocationUnavailable();
        return;
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
        ),
      );

      if (!mounted) return;

      setState(() {
        _riderPosition = position;
        _locationLoading = false;
        _locationUnavailable = false;
      });

      if (_mapReady && !_showSeller) _moveToRider(position);

      _positionSubscription?.cancel();
      _positionSubscription =
          Geolocator.getPositionStream(
            locationSettings: const LocationSettings(
              accuracy: LocationAccuracy.high,
              distanceFilter: 5,
            ),
          ).listen((position) {
            if (!mounted) return;
            setState(() => _riderPosition = position);
          });
    } catch (_) {
      _setLocationUnavailable();
    }
  }

  void _setLocationUnavailable() {
    if (!mounted) return;

    setState(() {
      _locationLoading = false;
      _locationUnavailable = true;
    });
  }

  void _moveToRider(Position position) {
    _mapController.move(LatLng(position.latitude, position.longitude), 16);
  }

  @override
  void dispose() {
    _positionSubscription?.cancel();
    _mapController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  // ---------------------------------------------------------------------------
  // Call + external navigation
  // ---------------------------------------------------------------------------

  void _showSnack(String message) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: darkMaroon,
          duration: const Duration(seconds: 3),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          content: Text(message),
        ),
      );
  }

  Future<bool> _launch(Uri uri) async {
    try {
      return await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (_) {
      return false;
    }
  }

  Future<void> _callSeller() async {
    final cleaned = pickup.sellerPhone.replaceAll(RegExp(r'[^0-9+]'), '');

    if (cleaned.isEmpty) {
      _showSnack('No phone number available for this seller.');
      return;
    }

    final opened = await _launch(Uri(scheme: 'tel', path: cleaned));

    if (!opened) _showSnack('Unable to open the phone dialer on this device.');
  }

  Future<void> _openInGoogleMaps() async {
    final seller = _sellerLocation;
    final rider = _riderPosition;

    final params = <String, String>{
      'api': '1',
      'destination': seller == null
          ? pickup.fullAddress
          : '${seller.latitude},${seller.longitude}',
      'travelmode': 'driving',
      if (rider != null) 'origin': '${rider.latitude},${rider.longitude}',
    };

    final opened = await _launch(
      Uri.https('www.google.com', '/maps/dir/', params),
    );

    if (!opened) _showSnack('Unable to open maps on this device.');
  }

  // ---------------------------------------------------------------------------
  // Seller lookup + route
  // ---------------------------------------------------------------------------

  Future<void> _showSellerOnMap() async {
    if (_routeLoading) return;

    setState(() {
      _showSeller = true;
      _routeLoading = true;
      _routeError = null;
    });

    _scrollToTop();

    if (_sellerLocation == null) {
      final found = await _locateSeller();
      if (!mounted) return;

      if (found == null) {
        setState(() {
          _routeLoading = false;
          _routeError =
              'We could not find this address on the map. You can still '
              'open it in Google Maps.';
        });
        return;
      }

      setState(() {
        _sellerLocation = found.point;
        _sellerApproximate = found.approximate;
      });
    }

    await _loadRoute();
    if (!mounted) return;

    setState(() => _routeLoading = false);

    WidgetsBinding.instance.addPostFrameCallback((_) => _fitMap());
  }

  void _hideSeller() {
    setState(() {
      _showSeller = false;
      _routeLoading = false;
      _routeError = null;
      _routePoints = [];
      _routeDistanceMeters = null;
      _routeDurationSeconds = null;
      _routeIsEstimate = false;
    });

    final position = _riderPosition;
    if (position != null) _moveToRider(position);
  }

  void _scrollToTop() {
    if (!_scrollController.hasClients) return;

    _scrollController.animateTo(
      0,
      duration: const Duration(milliseconds: 350),
      curve: Curves.easeOut,
    );
  }

  void _fitMap() {
    if (!_mapReady) return;

    final seller = _sellerLocation;
    if (seller == null) return;

    final rider = _riderPosition;

    if (rider == null) {
      _mapController.move(seller, 16);
      return;
    }

    final points = <LatLng>[
      seller,
      LatLng(rider.latitude, rider.longitude),
      ..._routePoints,
    ];

    _mapController.fitCamera(
      CameraFit.bounds(
        bounds: LatLngBounds.fromPoints(points),
        padding: const EdgeInsets.fromLTRB(56, 90, 56, 70),
        maxZoom: 17,
      ),
    );
  }

  // Looks the address up on OpenStreetMap (Nominatim).
  // Tries the most precise address first, then falls back to the barangay and
  // municipality. Those fallbacks are marked as "approximate".
  Future<_GeoResult?> _locateSeller() async {
    final queries = <String>[];

    void add(String query) {
      final text = query.trim();
      final hasContent = text.replaceAll(RegExp(r'[,\s]'), '').isNotEmpty;
      if (hasContent && !queries.contains(text)) queries.add(text);
    }

    add(pickup.fullAddress);
    add(
      '${pickup.address}, ${pickup.barangay}, '
      '${pickup.municipality}, ${pickup.province}',
    );
    final preciseCount = queries.length;
    add('${pickup.barangay}, ${pickup.municipality}, ${pickup.province}');
    add('${pickup.municipality}, ${pickup.province}');

    for (var i = 0; i < queries.length; i++) {
      // Nominatim asks for at most one request per second.
      if (i > 0) await Future<void>.delayed(const Duration(milliseconds: 1100));

      final point = await _geocode(queries[i]);

      if (point != null) {
        return _GeoResult(point: point, approximate: i >= preciseCount);
      }
    }

    return null;
  }

  Future<LatLng?> _geocode(String query) async {
    try {
      final response = await http
          .get(
            Uri.https('nominatim.openstreetmap.org', '/search', {
              'q': query,
              'format': 'jsonv2',
              'limit': '1',
              'countrycodes': 'ph',
            }),
            headers: const {
              'User-Agent': 'LumearLogistics/1.0 (van rider app)',
              'Accept-Language': 'en',
            },
          )
          .timeout(const Duration(seconds: 10));

      if (response.statusCode != 200) return null;

      final data = jsonDecode(response.body);

      if (data is List && data.isNotEmpty) {
        final first = data.first as Map<String, dynamic>;
        final lat = double.tryParse('${first['lat']}');
        final lon = double.tryParse('${first['lon']}');

        if (lat != null && lon != null) return LatLng(lat, lon);
      }
    } catch (_) {
      // Network error or timeout: treated as "not found".
    }

    return null;
  }

  Future<void> _loadRoute() async {
    final seller = _sellerLocation;
    if (seller == null) return;

    var rider = _riderPosition;

    if (rider == null) {
      try {
        rider = await Geolocator.getLastKnownPosition();
      } catch (_) {
        rider = null;
      }
    }

    if (rider == null) {
      _routePoints = [];
      _routeDistanceMeters = null;
      _routeDurationSeconds = null;
      _routeIsEstimate = false;
      return;
    }

    final from = LatLng(rider.latitude, rider.longitude);
    final route = await _fetchRoute(from, seller);

    if (route != null) {
      _routePoints = route.points;
      _routeDistanceMeters = route.distance;
      _routeDurationSeconds = route.duration;
      _routeIsEstimate = false;
    } else {
      // Fallback: straight line so the rider still sees direction and distance.
      _routePoints = [from, seller];
      _routeDistanceMeters = const Distance().as(
        LengthUnit.Meter,
        from,
        seller,
      );
      _routeDurationSeconds = null;
      _routeIsEstimate = true;
    }
  }

  // Driving route from the public OSRM demo server.
  Future<_RouteResult?> _fetchRoute(LatLng from, LatLng to) async {
    try {
      final response = await http
          .get(
            Uri.https(
              'router.project-osrm.org',
              '/route/v1/driving/'
                  '${from.longitude},${from.latitude};'
                  '${to.longitude},${to.latitude}',
              {'overview': 'full', 'geometries': 'geojson'},
            ),
          )
          .timeout(const Duration(seconds: 12));

      if (response.statusCode != 200) return null;

      final data = jsonDecode(response.body) as Map<String, dynamic>;
      final routes = data['routes'];

      if (routes is! List || routes.isEmpty) return null;

      final route = routes.first as Map<String, dynamic>;
      final coordinates = (route['geometry']['coordinates'] as List)
          .map(
            (c) => LatLng((c[1] as num).toDouble(), (c[0] as num).toDouble()),
          )
          .toList();

      if (coordinates.length < 2) return null;

      return _RouteResult(
        points: coordinates,
        distance: (route['distance'] as num).toDouble(),
        duration: (route['duration'] as num).toDouble(),
      );
    } catch (_) {
      return null;
    }
  }

  String _formatDistance(double? meters) {
    if (meters == null) return '\u2014';
    if (meters < 1000) return '${meters.round()} m';
    return '${(meters / 1000).toStringAsFixed(1)} km';
  }

  String _formatDuration(double? seconds) {
    if (seconds == null) return '\u2014';

    final minutes = (seconds / 60).round().clamp(1, 100000);

    if (minutes < 60) return '$minutes min';

    final hours = minutes ~/ 60;
    final rest = minutes % 60;

    return rest == 0 ? '$hours hr' : '$hours hr $rest min';
  }

  // ---------------------------------------------------------------------------
  // Status helpers
  // ---------------------------------------------------------------------------

  int get _currentStage {
    switch (pickup.status) {
      case VanPickupStatus.assigned:
        return 0;
      case VanPickupStatus.inProgress:
        return 1;
      case VanPickupStatus.arrived:
        return 2;
      case VanPickupStatus.collected:
        return 3;
      case VanPickupStatus.sellerConfirmed:
        return 4;
      case VanPickupStatus.handedOver:
        return 5;
      case VanPickupStatus.failed:
        return 0; // Not used: the progress card shows the failed state.
    }
  }

  String get _actionLabel {
    switch (pickup.status) {
      case VanPickupStatus.assigned:
        return 'Start Pickup';
      case VanPickupStatus.inProgress:
        return 'Arrived at Seller';
      case VanPickupStatus.arrived:
        return 'Report Parcels Collected';
      case VanPickupStatus.collected:
        return 'Acknowledge Seller Confirmation';
      case VanPickupStatus.sellerConfirmed:
        return 'Confirm Sorting Center Handover';
      case VanPickupStatus.handedOver:
        return 'Pickup Completed';
      case VanPickupStatus.failed:
        return 'Pickup Failed';
    }
  }

  IconData get _actionIcon {
    switch (pickup.status) {
      case VanPickupStatus.assigned:
        return Icons.play_arrow_rounded;
      case VanPickupStatus.inProgress:
        return Icons.pin_drop_rounded;
      case VanPickupStatus.arrived:
        return Icons.inventory_2_rounded;
      case VanPickupStatus.collected:
        return Icons.verified_rounded;
      case VanPickupStatus.sellerConfirmed:
        return Icons.local_shipping_rounded;
      case VanPickupStatus.handedOver:
        return Icons.check_circle_rounded;
      case VanPickupStatus.failed:
        return Icons.cancel_rounded;
    }
  }

  String get _nextStepHint {
    switch (pickup.status) {
      case VanPickupStatus.assigned:
        return 'Tap Start Pickup when you are ready to head out.';
      case VanPickupStatus.inProgress:
        return "You're on your way. Tap below once you arrive at the "
            "seller's address.";
      case VanPickupStatus.arrived:
        return 'Collect all assigned parcels, then report the collection.';
      case VanPickupStatus.collected:
        return 'Once the seller has confirmed the pickup, acknowledge it '
            'below to proceed to the sorting center.';
      case VanPickupStatus.sellerConfirmed:
        return 'Bring the parcels to the sorting center and confirm the '
            'handover.';
      case VanPickupStatus.handedOver:
        return 'This pickup is complete. Great work!';
      case VanPickupStatus.failed:
        return 'This pickup was reported as failed.';
    }
  }

  // Same palette as the dashboard so statuses look consistent everywhere.
  Color _statusColor(VanPickupStatus status) {
    switch (status) {
      case VanPickupStatus.assigned:
        return const Color(0xFF9B7000);
      case VanPickupStatus.inProgress:
        return const Color(0xFF245CA6);
      case VanPickupStatus.arrived:
        return const Color(0xFF0E7C86);
      case VanPickupStatus.collected:
        return const Color(0xFF6548A5);
      case VanPickupStatus.sellerConfirmed:
        return const Color(0xFFB4570A);
      case VanPickupStatus.handedOver:
        return const Color(0xFF287544);
      case VanPickupStatus.failed:
        return const Color(0xFFB3261E);
    }
  }

  void _performAction() {
    switch (pickup.status) {
      case VanPickupStatus.assigned:
        pickup.startPickup();
        break;
      case VanPickupStatus.inProgress:
        // Rider has reached the seller's address.
        pickup.confirmArrival();
        break;
      case VanPickupStatus.arrived:
        pickup.reportCollection();
        break;
      case VanPickupStatus.collected:
        // Driver acknowledges that the seller confirmed the pickup.
        pickup.acknowledgeSellerConfirmation();
        break;
      case VanPickupStatus.sellerConfirmed:
        pickup.confirmHandover();
        break;
      case VanPickupStatus.handedOver:
      case VanPickupStatus.failed:
        return;
    }

    setState(() {});
    widget.onChanged();
  }

  Future<void> _copy(String label, String value) async {
    await Clipboard.setData(ClipboardData(text: value));
    _showSnack('$label copied');
  }

  // The rider can report a failed pickup any time before the parcels are
  // collected (e.g. the seller can't be reached).
  bool get _canReportFailed =>
      pickup.status == VanPickupStatus.assigned ||
      pickup.status == VanPickupStatus.inProgress ||
      pickup.status == VanPickupStatus.arrived;

  Future<void> _reportFailedPickup() async {
    final reason = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _FailedPickupSheet(),
    );

    if (reason == null || !mounted) return;

    pickup.reportFailedPickup(reason);

    setState(() {});
    widget.onChanged();
    _showSnack('Pickup reported as failed.');
  }

  // ---------------------------------------------------------------------------
  // Build
  // ---------------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: pageBg,
      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        title: const Text(
          'Pickup Details',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView(
              controller: _scrollController,
              padding: EdgeInsets.zero,
              children: [
                _buildMap(),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _buildHeaderCard(),
                      if (_showSeller) ...[
                        const SizedBox(height: 14),
                        _buildRouteCard(),
                      ],
                      const SizedBox(height: 14),
                      _buildProgressCard(),
                      const SizedBox(height: 14),
                      _buildSellerCard(),
                      const SizedBox(height: 14),
                      _buildAddressCard(),
                      const SizedBox(height: 14),
                      _buildParcelCard(),
                      const SizedBox(height: 14),
                      _buildNotesCard(),
                    ],
                  ),
                ),
              ],
            ),
          ),
          _buildBottomAction(),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Map
  // ---------------------------------------------------------------------------

  Widget _buildMap() {
    final position = _riderPosition;
    final seller = _sellerLocation;

    final center = position == null
        ? const LatLng(14.5995, 120.9842)
        : LatLng(position.latitude, position.longitude);

    final Color dotColor = _locationLoading
        ? Colors.orange.shade700
        : _locationUnavailable
        ? Colors.red.shade600
        : Colors.green.shade600;

    final String statusText = _locationLoading
        ? 'Getting location...'
        : _locationUnavailable
        ? 'Location unavailable'
        : 'Live rider location';

    final showSellerPin = _showSeller && seller != null;

    return SizedBox(
      height: 290,
      child: Stack(
        children: [
          Positioned.fill(
            bottom: 20,
            child: FlutterMap(
              mapController: _mapController,
              options: MapOptions(
                initialCenter: center,
                initialZoom: position == null ? 5 : 16,
                onMapReady: () {
                  _mapReady = true;
                  if (_showSeller && _sellerLocation != null) {
                    _fitMap();
                  } else if (_riderPosition != null) {
                    _moveToRider(_riderPosition!);
                  }
                },
              ),
              children: [
                TileLayer(
                  urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                  userAgentPackageName: 'com.example.flutter_application_1',
                ),
                if (showSellerPin && _routePoints.length > 1)
                  PolylineLayer(
                    polylines: [
                      Polyline(
                        points: _routePoints,
                        strokeWidth: 5,
                        color: maroon,
                        borderStrokeWidth: 2,
                        borderColor: Colors.white,
                      ),
                    ],
                  ),
                MarkerLayer(
                  markers: [
                    if (showSellerPin)
                      Marker(
                        point: seller,
                        width: 170,
                        height: 78,
                        alignment: Alignment.topCenter,
                        child: _buildSellerMarker(),
                      ),
                    if (position != null)
                      Marker(
                        point: LatLng(position.latitude, position.longitude),
                        width: 56,
                        height: 56,
                        child: Container(
                          decoration: BoxDecoration(
                            color: maroon,
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 3),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.25),
                                blurRadius: 8,
                                offset: const Offset(0, 3),
                              ),
                            ],
                          ),
                          child: const Icon(
                            Icons.local_shipping_rounded,
                            color: Colors.white,
                            size: 26,
                          ),
                        ),
                      ),
                  ],
                ),
                RichAttributionWidget(
                  alignment: AttributionAlignment.bottomLeft,
                  attributions: [
                    TextSourceAttribution('© OpenStreetMap contributors'),
                  ],
                ),
              ],
            ),
          ),

          // Rounded sheet edge that overlaps the bottom of the map.
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            child: Container(
              height: 24,
              decoration: const BoxDecoration(
                color: pageBg,
                borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
              ),
            ),
          ),

          // Location status pill
          Positioned(
            top: 12,
            left: 12,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.12),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 9,
                    height: 9,
                    decoration: BoxDecoration(
                      color: dotColor,
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    statusText,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: darkMaroon,
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Map controls
          Positioned(
            right: 14,
            bottom: 34,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (showSellerPin) ...[
                  FloatingActionButton.small(
                    heroTag: 'van_pickup_fit_route',
                    tooltip: 'Show full route',
                    backgroundColor: Colors.white,
                    foregroundColor: darkMaroon,
                    elevation: 3,
                    onPressed: _fitMap,
                    child: const Icon(Icons.zoom_out_map_rounded),
                  ),
                  const SizedBox(height: 8),
                ],
                FloatingActionButton.small(
                  heroTag: 'van_pickup_location',
                  tooltip: 'My location',
                  backgroundColor: Colors.white,
                  foregroundColor: darkMaroon,
                  elevation: 3,
                  onPressed: () {
                    if (_riderPosition != null) {
                      _moveToRider(_riderPosition!);
                    } else {
                      setState(() {
                        _locationLoading = true;
                        _locationUnavailable = false;
                      });
                      _initializeLocation();
                    }
                  },
                  child: const Icon(Icons.my_location),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSellerMarker() {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          constraints: const BoxConstraints(maxWidth: 160),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.2),
                blurRadius: 6,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.storefront_rounded, size: 14, color: maroon),
              const SizedBox(width: 5),
              Flexible(
                child: Text(
                  pickup.sellerName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 2),
        Icon(
          Icons.location_on_rounded,
          size: 42,
          color: Colors.red.shade700,
          shadows: const [Shadow(color: Colors.black38, blurRadius: 6)],
        ),
      ],
    );
  }

  // ---------------------------------------------------------------------------
  // Header card (seller summary + call / navigate)
  // ---------------------------------------------------------------------------

  Widget _buildHeaderCard() {
    final statusColor = _statusColor(pickup.status);
    final hasPhone = pickup.sellerPhone.trim().isNotEmpty;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: border),
        boxShadow: [
          BoxShadow(
            color: darkMaroon.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'PICKUP ${pickup.id}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: beige,
                    fontSize: 11,
                    letterSpacing: 1,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 5,
                ),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 7,
                      height: 7,
                      decoration: BoxDecoration(
                        color: statusColor,
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      pickup.statusLabel,
                      style: TextStyle(
                        color: statusColor,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            pickup.sellerName,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 22,
              height: 1.2,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              const Icon(Icons.location_on_outlined, size: 16, color: beige),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  '${pickup.barangay}, ${pickup.municipality}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.black54, fontSize: 13),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: cream,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.inventory_2_rounded,
                  size: 18,
                  color: darkMaroon,
                ),
                const SizedBox(width: 8),
                Text(
                  '${pickup.parcelCount} '
                  '${pickup.parcelCount == 1 ? 'parcel' : 'parcels'} for pickup',
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: hasPhone ? _callSeller : null,
                  icon: const Icon(Icons.call_rounded, size: 19),
                  label: const Text('Call Seller'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: maroon,
                    disabledForegroundColor: lightBeige,
                    side: BorderSide(color: hasPhone ? maroon : border),
                    minimumSize: const Size.fromHeight(48),
                    textStyle: const TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w800,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(13),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _routeLoading ? null : _showSellerOnMap,
                  icon: _routeLoading
                      ? const SizedBox(
                          width: 17,
                          height: 17,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.directions_rounded, size: 20),
                  label: Text(_showSeller ? 'Refresh Route' : 'Navigate'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: darkMaroon,
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: maroon,
                    disabledForegroundColor: Colors.white,
                    elevation: 0,
                    minimumSize: const Size.fromHeight(48),
                    textStyle: const TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w800,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(13),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Route card (shown after tapping Navigate)
  // ---------------------------------------------------------------------------

  Widget _buildRouteCard() {
    final hasSeller = _sellerLocation != null;
    final noRider = hasSeller && !_routeLoading && _routePoints.isEmpty;

    Widget body;

    if (_routeLoading) {
      body = const Padding(
        padding: EdgeInsets.symmetric(vertical: 6),
        child: Row(
          children: [
            SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(strokeWidth: 2.4, color: maroon),
            ),
            SizedBox(width: 12),
            Expanded(
              child: Text(
                'Locating the seller and calculating the route...',
                style: TextStyle(color: Colors.black54, fontSize: 13),
              ),
            ),
          ],
        ),
      );
    } else if (_routeError != null && !hasSeller) {
      body = Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.error_outline, size: 20, color: Colors.red.shade700),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  _routeError!,
                  style: const TextStyle(
                    color: Colors.black87,
                    fontSize: 13,
                    height: 1.4,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _showSellerOnMap,
                  icon: const Icon(Icons.refresh_rounded, size: 18),
                  label: const Text('Try again'),
                  style: _secondaryButtonStyle(),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: _openInGoogleMaps,
                  icon: const Icon(Icons.open_in_new_rounded, size: 18),
                  label: const Text('Google Maps'),
                  style: _primaryButtonStyle(),
                ),
              ),
            ],
          ),
        ],
      );
    } else {
      body = Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: _metricTile(
                  icon: Icons.route_rounded,
                  label: 'Distance',
                  value: noRider
                      ? '\u2014'
                      : _formatDistance(_routeDistanceMeters),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _metricTile(
                  icon: Icons.schedule_rounded,
                  label: 'Est. drive time',
                  value: noRider
                      ? '\u2014'
                      : _formatDuration(_routeDurationSeconds),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(Icons.location_on_rounded, size: 18, color: maroon),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  pickup.fullAddress,
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 13,
                    height: 1.4,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          ..._routeNotes(noRider),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: _openInGoogleMaps,
              icon: const Icon(Icons.turn_right_rounded, size: 20),
              label: const Text('Start turn-by-turn in Google Maps'),
              style: _primaryButtonStyle(),
            ),
          ),
        ],
      );
    }

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: cream,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(
                  Icons.directions_rounded,
                  size: 20,
                  color: maroon,
                ),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Text(
                  'Route to Seller',
                  style: TextStyle(
                    color: darkMaroon,
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              IconButton(
                tooltip: 'Hide route',
                visualDensity: VisualDensity.compact,
                onPressed: _hideSeller,
                icon: const Icon(Icons.close_rounded, color: beige),
              ),
            ],
          ),
          const SizedBox(height: 14),
          body,
        ],
      ),
    );
  }

  List<Widget> _routeNotes(bool noRider) {
    final notes = <(IconData, String)>[
      if (_sellerApproximate)
        (
          Icons.info_outline_rounded,
          'Approximate pin based on the barangay. Please confirm the exact '
              'location with the seller.',
        ),
      if (noRider)
        (
          Icons.location_disabled_outlined,
          'Turn on location services to see the route from your position.',
        ),
      if (_routeIsEstimate)
        (
          Icons.timeline_rounded,
          'Showing the straight-line distance. Road route is unavailable '
              'right now.',
        ),
    ];

    return notes
        .map(
          (note) => Padding(
            padding: const EdgeInsets.only(top: 10),
            child: Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF3D6),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFEAD39A)),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(note.$1, size: 16, color: amberText),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      note.$2,
                      style: const TextStyle(
                        color: Color(0xFF684D1B),
                        fontSize: 11.5,
                        height: 1.4,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        )
        .toList();
  }

  Widget _metricTile({
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: cream,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Icon(icon, size: 22, color: maroon),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(label, style: const TextStyle(color: beige, fontSize: 11)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  ButtonStyle _primaryButtonStyle() {
    return ElevatedButton.styleFrom(
      backgroundColor: darkMaroon,
      foregroundColor: Colors.white,
      elevation: 0,
      minimumSize: const Size.fromHeight(46),
      textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    );
  }

  ButtonStyle _secondaryButtonStyle() {
    return OutlinedButton.styleFrom(
      foregroundColor: maroon,
      side: const BorderSide(color: maroon),
      minimumSize: const Size.fromHeight(46),
      textStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    );
  }

  // ---------------------------------------------------------------------------
  // Progress
  // ---------------------------------------------------------------------------

  Widget _buildProgressCard() {
    if (pickup.status == VanPickupStatus.failed) return _buildFailedCard();

    const labels = [
      'Start Pickup',
      'In Transit',
      'Arrived',
      'Collected',
      'Seller Confirmed',
      'Handed Over',
    ];

    final waiting = pickup.status == VanPickupStatus.collected;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(14, 16, 14, 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 4),
            child: Text(
              'PICKUP PROGRESS',
              style: TextStyle(
                color: beige,
                fontSize: 11,
                letterSpacing: 1,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          const SizedBox(height: 18),
          _buildStepper(labels),
          const SizedBox(height: 16),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: waiting ? const Color(0xFFFFF3D6) : cream,
              borderRadius: BorderRadius.circular(12),
              border: waiting
                  ? Border.all(color: const Color(0xFFEAD39A))
                  : null,
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  waiting
                      ? Icons.hourglass_top_rounded
                      : Icons.arrow_circle_right_outlined,
                  size: 19,
                  color: waiting ? amberText : maroon,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        waiting
                            ? 'Waiting for seller confirmation'
                            : pickup.status == VanPickupStatus.handedOver
                            ? 'All done'
                            : 'Next step',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                          color: waiting ? amberText : darkMaroon,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        _nextStepHint,
                        style: TextStyle(
                          fontSize: 12,
                          height: 1.45,
                          color: waiting
                              ? const Color(0xFF684D1B)
                              : Colors.black87,
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

  // Shown instead of the stepper when the pickup could not be completed.
  Widget _buildFailedCard() {
    const Color red = Color(0xFFB3261E);
    final reason = (pickup.failureReason ?? '').trim();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFF2C6C2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: const Color(0xFFFDECEA),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.cancel_rounded, size: 20, color: red),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Text(
                  'Pickup could not be completed',
                  style: TextStyle(
                    color: red,
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFFDECEA),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'REASON',
                  style: TextStyle(
                    color: red,
                    fontSize: 10.5,
                    letterSpacing: 1,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  reason.isEmpty ? 'No reason provided.' : reason,
                  style: const TextStyle(
                    color: Colors.black87,
                    fontSize: 13.5,
                    height: 1.45,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          const Text(
            'The parcels were not collected from this seller.',
            style: TextStyle(color: Colors.black54, fontSize: 12, height: 1.4),
          ),
        ],
      ),
    );
  }

  // Step tracker. Every node sits in a fixed-size slot and every connector is
  // centered in a fixed-height row, so all lines stay perfectly aligned no
  // matter which step is current.
  Widget _buildStepper(List<String> labels) {
    const double slotSize = 34; // width/height reserved for each node
    const double nodeSize = 26; // regular node
    const double rowHeight = 36; // height of the node + connector row
    const double lineThickness = 3;

    Widget connector({required bool visible, required bool colored}) {
      return Expanded(
        child: Container(
          height: lineThickness,
          color: !visible
              ? Colors.transparent
              : (colored ? maroon : const Color(0xFFE6D8CC)),
        ),
      );
    }

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: List.generate(labels.length, (index) {
        final complete = index < _currentStage;
        final current = index == _currentStage;
        final active = complete || current;

        // Left half is colored once this step is reached, right half only
        // once this step is finished, so each connector is one solid color.
        final leftColored = index <= _currentStage;
        final rightColored = index < _currentStage;

        Widget node;

        if (current) {
          node = Container(
            width: slotSize,
            height: slotSize,
            decoration: BoxDecoration(
              color: maroon.withValues(alpha: 0.2),
              shape: BoxShape.circle,
            ),
            child: Center(
              child: Container(
                width: nodeSize,
                height: nodeSize,
                decoration: const BoxDecoration(
                  color: maroon,
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.circle, size: 9, color: Colors.white),
              ),
            ),
          );
        } else {
          node = SizedBox(
            width: slotSize,
            height: slotSize,
            child: Center(
              child: Container(
                width: nodeSize,
                height: nodeSize,
                decoration: BoxDecoration(
                  color: complete ? maroon : cream,
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  complete ? Icons.check_rounded : Icons.circle,
                  size: complete ? 16 : 8,
                  color: complete ? Colors.white : lightBeige,
                ),
              ),
            ),
          );
        }

        return Expanded(
          child: Column(
            children: [
              SizedBox(
                height: rowHeight,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    connector(visible: index != 0, colored: leftColored),
                    node,
                    connector(
                      visible: index != labels.length - 1,
                      colored: rightColored,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 6),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 1),
                child: Text(
                  labels[index],
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 9,
                    height: 1.25,
                    color: active ? darkMaroon : Colors.black45,
                    fontWeight: current ? FontWeight.w800 : FontWeight.w500,
                  ),
                ),
              ),
            ],
          ),
        );
      }),
    );
  }

  // ---------------------------------------------------------------------------
  // Info cards
  // ---------------------------------------------------------------------------

  Widget _buildSellerCard() {
    return _SectionCard(
      title: 'Seller Information',
      icon: Icons.storefront_rounded,
      children: [
        _InfoRow(
          icon: Icons.person_outline_rounded,
          label: 'Seller name',
          value: pickup.sellerName,
        ),
        _InfoRow(
          icon: Icons.phone_outlined,
          label: 'Contact number',
          value: pickup.sellerPhone,
          onCall: pickup.sellerPhone.trim().isEmpty ? null : _callSeller,
          onCopy: pickup.sellerPhone.isEmpty
              ? null
              : () => _copy('Contact number', pickup.sellerPhone),
        ),
        _InfoRow(
          icon: Icons.email_outlined,
          label: 'Email',
          value: pickup.sellerEmail,
          onCopy: pickup.sellerEmail.isEmpty
              ? null
              : () => _copy('Email', pickup.sellerEmail),
          isLast: true,
        ),
      ],
    );
  }

  Widget _buildAddressCard() {
    return _SectionCard(
      title: 'Pickup Address',
      icon: Icons.location_on_outlined,
      children: [
        _InfoRow(
          icon: Icons.home_outlined,
          label: 'Street / House',
          value: pickup.address,
        ),
        _InfoRow(
          icon: Icons.location_city_outlined,
          label: 'Barangay',
          value: pickup.barangay,
        ),
        _InfoRow(
          icon: Icons.map_outlined,
          label: 'Municipality / Province',
          value: '${pickup.municipality}, ${pickup.province}',
          onCopy: () => _copy('Address', pickup.fullAddress),
          isLast: true,
        ),
        const SizedBox(height: 14),
        SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: _routeLoading ? null : _showSellerOnMap,
            icon: const Icon(Icons.map_rounded, size: 19),
            label: const Text('View seller on map'),
            style: _secondaryButtonStyle(),
          ),
        ),
      ],
    );
  }

  Widget _buildParcelCard() {
    return _SectionCard(
      title: 'Parcel Summary',
      icon: Icons.inventory_2_outlined,
      children: [
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [darkMaroon, maroon],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.16),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.inventory_2_rounded,
                  size: 24,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Text(
                  'Total assigned parcels',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              Text(
                '${pickup.parcelCount}',
                style: const TextStyle(
                  fontSize: 28,
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildNotesCard() {
    final hasNotes = pickup.notes.trim().isNotEmpty;

    return _SectionCard(
      title: 'Pickup Instructions',
      icon: Icons.notes_rounded,
      children: [
        Text(
          hasNotes ? pickup.notes : 'No special instructions.',
          style: TextStyle(
            height: 1.5,
            fontSize: 13.5,
            color: hasNotes ? Colors.black87 : Colors.black45,
            fontStyle: hasNotes ? FontStyle.normal : FontStyle.italic,
          ),
        ),
      ],
    );
  }

  // ---------------------------------------------------------------------------
  // Bottom action
  // ---------------------------------------------------------------------------

  Widget _buildBottomAction() {
    final failed = pickup.status == VanPickupStatus.failed;
    final completed = pickup.status == VanPickupStatus.handedOver || failed;

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            blurRadius: 14,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (_canReportFailed)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: SizedBox(
                  width: double.infinity,
                  height: 44,
                  child: TextButton.icon(
                    onPressed: _reportFailedPickup,
                    icon: const Icon(Icons.report_problem_outlined, size: 19),
                    label: const Text("Can't pick up? Report failed pickup"),
                    style: TextButton.styleFrom(
                      foregroundColor: const Color(0xFFB3261E),
                      textStyle: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                ),
              ),
            SizedBox(
              width: double.infinity,
              height: 54,
              child: ElevatedButton.icon(
                onPressed: completed ? null : _performAction,
                style: ElevatedButton.styleFrom(
                  backgroundColor: darkMaroon,
                  disabledBackgroundColor: failed
                      ? Colors.red.shade700
                      : Colors.green.shade700,
                  foregroundColor: Colors.white,
                  disabledForegroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(15),
                  ),
                  elevation: 0,
                ),
                icon: Icon(_actionIcon, size: 22),
                label: Text(
                  _actionLabel,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 14,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// -----------------------------------------------------------------------------
// Small data holders
// -----------------------------------------------------------------------------

class _GeoResult {
  final LatLng point;
  final bool approximate;

  const _GeoResult({required this.point, required this.approximate});
}

class _RouteResult {
  final List<LatLng> points;
  final double distance;
  final double duration;

  const _RouteResult({
    required this.points,
    required this.distance,
    required this.duration,
  });
}

// -----------------------------------------------------------------------------
// Reusable pieces
// -----------------------------------------------------------------------------

class _SectionCard extends StatelessWidget {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color border = Color(0xFFEDE1D8);

  final String title;
  final IconData icon;
  final List<Widget> children;

  const _SectionCard({
    required this.title,
    required this.icon,
    required this.children,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: cream,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, size: 20, color: maroon),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          ...children,
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color beige = Color(0xFF9E7161);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color border = Color(0xFFEDE1D8);

  final IconData icon;
  final String label;
  final String value;
  final VoidCallback? onCopy;
  final VoidCallback? onCall;
  final bool isLast;

  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
    this.onCopy,
    this.onCall,
    this.isLast = false,
  });

  @override
  Widget build(BuildContext context) {
    final displayValue = value.trim().isEmpty ? '\u2014' : value;

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        border: isLast
            ? null
            : const Border(bottom: BorderSide(color: border, width: 1)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Icon(icon, size: 19, color: beige),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    color: Colors.black45,
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  displayValue,
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 14,
                    height: 1.3,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          if (onCall != null)
            IconButton(
              tooltip: 'Call',
              visualDensity: VisualDensity.compact,
              onPressed: onCall,
              style: IconButton.styleFrom(
                backgroundColor: cream,
                foregroundColor: maroon,
              ),
              icon: const Icon(Icons.call_rounded, size: 18),
            ),
          if (onCopy != null)
            IconButton(
              tooltip: 'Copy',
              visualDensity: VisualDensity.compact,
              onPressed: onCopy,
              icon: const Icon(Icons.copy_rounded, size: 18, color: beige),
            ),
        ],
      ),
    );
  }
}

// -----------------------------------------------------------------------------
// Failed pickup bottom sheet
// -----------------------------------------------------------------------------

class _FailedPickupSheet extends StatefulWidget {
  const _FailedPickupSheet();

  @override
  State<_FailedPickupSheet> createState() => _FailedPickupSheetState();
}

class _FailedPickupSheetState extends State<_FailedPickupSheet> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color beige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color pageBg = Color(0xFFFFFAF6);
  static const Color border = Color(0xFFEDE1D8);

  static const String _otherLabel = 'Other';

  static const List<(IconData, String)> _reasons = [
    (Icons.phone_missed_rounded, 'Seller is not answering calls'),
    (Icons.phone_disabled_rounded, 'Seller number is unreachable or wrong'),
    (Icons.person_off_rounded, 'Seller is not at the pickup address'),
    (Icons.wrong_location_rounded, 'Address is incorrect or cannot be found'),
    (Icons.inventory_2_outlined, 'Parcels are not ready for pickup'),
    (Icons.block_rounded, 'Seller cancelled or refused the pickup'),
    (Icons.more_horiz_rounded, _otherLabel),
  ];

  final TextEditingController _noteController = TextEditingController();
  String? _selected;

  bool get _isOther => _selected == _otherLabel;

  bool get _canSubmit =>
      _selected != null &&
      (!_isOther || _noteController.text.trim().isNotEmpty);

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  void _submit() {
    final selected = _selected;
    if (selected == null) return;

    final note = _noteController.text.trim();

    final String reason;
    if (_isOther) {
      reason = 'Other: $note';
    } else {
      reason = note.isEmpty ? selected : '$selected. $note';
    }

    Navigator.of(context).pop(reason);
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Container(
      decoration: const BoxDecoration(
        color: pageBg,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: EdgeInsets.fromLTRB(16, 10, 16, 16 + bottomInset),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: lightBeige,
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Report failed pickup',
                style: TextStyle(
                  color: darkMaroon,
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                'Select the reason you could not collect the parcels. '
                'The pickup will be marked as not collected.',
                style: TextStyle(
                  color: Colors.black54,
                  fontSize: 12.5,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 14),
              for (final reason in _reasons) _reasonTile(reason.$1, reason.$2),
              const SizedBox(height: 4),
              TextField(
                controller: _noteController,
                maxLines: 3,
                minLines: 2,
                maxLength: 200,
                textCapitalization: TextCapitalization.sentences,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(
                  hintText: _isOther
                      ? 'Describe what happened (required)'
                      : 'Add details (optional)',
                  hintStyle: const TextStyle(color: Colors.black38),
                  filled: true,
                  fillColor: Colors.white,
                  contentPadding: const EdgeInsets.all(12),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: border),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: maroon, width: 1.6),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.of(context).pop(),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: maroon,
                        side: const BorderSide(color: maroon),
                        minimumSize: const Size.fromHeight(48),
                        textStyle: const TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w800,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(13),
                        ),
                      ),
                      child: const Text('Cancel'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: _canSubmit ? _submit : null,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFFB3261E),
                        foregroundColor: Colors.white,
                        disabledBackgroundColor: const Color(0xFFE9CFCC),
                        disabledForegroundColor: Colors.white,
                        elevation: 0,
                        minimumSize: const Size.fromHeight(48),
                        textStyle: const TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w800,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(13),
                        ),
                      ),
                      child: const Text('Submit Report'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _reasonTile(IconData icon, String label) {
    final selected = _selected == label;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () => setState(() => _selected = label),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
          decoration: BoxDecoration(
            color: selected ? cream : Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: selected ? maroon : border,
              width: selected ? 1.6 : 1,
            ),
          ),
          child: Row(
            children: [
              Icon(icon, size: 20, color: selected ? maroon : beige),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  label,
                  style: TextStyle(
                    color: darkMaroon,
                    fontSize: 13.5,
                    fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                  ),
                ),
              ),
              Icon(
                selected
                    ? Icons.radio_button_checked_rounded
                    : Icons.radio_button_unchecked_rounded,
                size: 20,
                color: selected ? maroon : lightBeige,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

class TripDetailsScreen extends StatefulWidget {
  const TripDetailsScreen({super.key});

  @override
  State<TripDetailsScreen> createState() => _TripDetailsScreenState();
}

class _TripDetailsScreenState extends State<TripDetailsScreen> {
  static const Color darkMaroon = Color(0xFF671B1D);
  static const Color maroon = Color(0xFF873632);
  static const Color brown = Color(0xFF9E7161);
  static const Color cream = Color(0xFFFFF8E7);
  static const Color paleCream = Color(0xFFFFFDF5);
  static const Color border = Color(0xFFE8D6C3);

  // Trip information
  final String tripId = 'TRP-2301';
  final String origin = 'Victoria Hub';
  final String destination = 'Calamba Logistics Hub';
  final String estimatedArrival = '3:15 PM';
  final String cargo = 'Electronics';
  final int parcelCount = 24;
  final String distance = 'Approx. 35 km';

  // Replace these demo coordinates with your actual hub coordinates.
  final LatLng originLocation = const LatLng(14.2270, 121.3250);
  final LatLng destinationLocation = const LatLng(14.2110, 121.1650);

  final MapController _mapController = MapController();

  LatLng? _riderLocation;
  StreamSubscription<Position>? _positionSubscription;

  bool _didCenterOnRider = false;
  // ignore: unused_field
  bool _isNavigating = false;

  // Route generated from the routing service (follows real roads).
  List<LatLng> _routePoints = [];
  bool _isLoadingRoute = false;
  String? _routeDistanceText;
  String? _routeDurationText;

  // 0 Assigned
  // 1 Heading to Logistics
  // 2 In Transit
  // 3 Arrived at Logistics
  // 4 Handed Over
  int _currentStage = 0;

  DateTime? _departureTime;
  DateTime? _transitTime;
  DateTime? _arrivalTime;
  DateTime? _handoverTime;

  // Top notification
  OverlayEntry? _bannerEntry;

  final List<String> _stages = const [
    'Assigned',
    'Heading to Logistics',
    'In Transit',
    'Arrived at Logistics',
    'Handed Over',
  ];

  // Short labels used in the horizontal stepper.
  final List<String> _shortStages = const [
    'Assigned',
    'Departed',
    'In Transit',
    'Arrived',
    'Handed Over',
  ];

  final List<String> _descriptions = const [
    'Your trip has been assigned. Check your truck and prepare for departure.',
    'Your trip has started. Proceed to the assigned route.',
    'You are currently travelling to the Logistics Hub.',
    'You have arrived. Prepare the parcels for handover.',
    'All parcels have been handed over to the Logistics team.',
  ];

  @override
  void initState() {
    super.initState();
    _startLocationTracking();
  }

  @override
  void dispose() {
    _bannerEntry?.remove();
    _bannerEntry = null;
    _positionSubscription?.cancel();
    _mapController.dispose();
    super.dispose();
  }

  Future<void> _startLocationTracking() async {
    try {
      final serviceEnabled = await Geolocator.isLocationServiceEnabled();

      if (!mounted) return;

      if (!serviceEnabled) {
        _showMessage('Please turn on your device location.');
        return;
      }

      LocationPermission permission = await Geolocator.checkPermission();

      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }

      if (!mounted) return;

      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        _showMessage(
          'Location permission is needed to show your current position.',
        );
        return;
      }

      const locationSettings = LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 5,
      );

      await _positionSubscription?.cancel();

      _positionSubscription =
          Geolocator.getPositionStream(
            locationSettings: locationSettings,
          ).listen(
            (position) {
              if (!mounted) return;

              final location = LatLng(position.latitude, position.longitude);

              setState(() {
                _riderLocation = location;
              });

              if (!_didCenterOnRider) {
                _didCenterOnRider = true;

                WidgetsBinding.instance.addPostFrameCallback((_) {
                  if (!mounted || _riderLocation == null) return;

                  _mapController.move(_riderLocation!, 13);
                });
              }
            },
            onError: (Object error) {
              if (mounted) {
                _showMessage('Unable to update your current location.');
              }
            },
          );
    } catch (error) {
      if (mounted) {
        _showMessage('Could not start location tracking.');
      }
    }
  }

  String _formatDuration(double seconds) {
    final totalMinutes = (seconds / 60).round();
    final hours = totalMinutes ~/ 60;
    final minutes = totalMinutes % 60;

    if (hours == 0) return '$minutes min';
    return '$hours hr $minutes min';
  }

  /// Requests a road-following route and draws it on the map.
  Future<void> _fetchRoute() async {
    if (_isLoadingRoute) return;

    setState(() => _isLoadingRoute = true);

    // Start from the driver's live position when available.
    final start = _riderLocation ?? originLocation;
    final end = destinationLocation;

    try {
      final uri = Uri.parse(
        'https://router.project-osrm.org/route/v1/driving/'
        '${start.longitude},${start.latitude};'
        '${end.longitude},${end.latitude}'
        '?overview=full&geometries=geojson',
      );

      final response = await http.get(uri).timeout(const Duration(seconds: 15));

      if (!mounted) return;

      if (response.statusCode != 200) {
        throw Exception('HTTP ${response.statusCode}');
      }

      final data = jsonDecode(response.body) as Map<String, dynamic>;
      final routes = data['routes'] as List<dynamic>?;

      if (routes == null || routes.isEmpty) {
        _showMessage('No route found between these locations.');
        return;
      }

      final route = routes.first as Map<String, dynamic>;
      final coordinates =
          (route['geometry'] as Map<String, dynamic>)['coordinates']
              as List<dynamic>;

      final points = coordinates.map((c) {
        final pair = c as List<dynamic>;
        return LatLng((pair[1] as num).toDouble(), (pair[0] as num).toDouble());
      }).toList();

      final meters = (route['distance'] as num).toDouble();
      final seconds = (route['duration'] as num).toDouble();

      setState(() {
        _routePoints = points;
        _routeDistanceText = '${(meters / 1000).toStringAsFixed(1)} km';
        _routeDurationText = _formatDuration(seconds);
        _isNavigating = true;
      });

      _mapController.fitCamera(
        CameraFit.bounds(
          bounds: LatLngBounds.fromPoints(points),
          padding: const EdgeInsets.all(48),
        ),
      );

      _showMessage('Route ready: $_routeDistanceText • $_routeDurationText');
    } on TimeoutException {
      if (mounted) _showMessage('Route request timed out. Try again.');
    } catch (error) {
      if (mounted) _showMessage('Could not generate the route.');
    } finally {
      if (mounted) setState(() => _isLoadingRoute = false);
    }
  }

  /// Opens turn-by-turn navigation in the device's maps app.
  Future<void> _openExternalNavigation() async {
    final start = _riderLocation;
    final end = destinationLocation;

    final uri = Uri.parse(
      'https://www.google.com/maps/dir/?api=1'
      '${start != null ? '&origin=${start.latitude},${start.longitude}' : ''}'
      '&destination=${end.latitude},${end.longitude}'
      '&travelmode=driving',
    );

    try {
      final launched = await launchUrl(
        uri,
        mode: LaunchMode.externalApplication,
      );

      if (!launched) _showMessage('No maps app available.');
    } catch (error) {
      _showMessage('Could not open the maps app.');
    }
  }

  /// Shows a notification banner at the TOP of the screen.
  void _showMessage(String message) {
    if (!mounted) return;

    _bannerEntry?.remove();
    _bannerEntry = null;

    final overlay = Overlay.of(context);
    final topPadding = MediaQuery.of(context).padding.top;

    late final OverlayEntry entry;

    entry = OverlayEntry(
      builder: (_) => Positioned(
        top: topPadding + 8,
        left: 16,
        right: 16,
        child: Material(
          color: Colors.transparent,
          child: _TopBanner(
            key: UniqueKey(),
            message: message,
            onDismissed: () {
              if (entry.mounted) entry.remove();
              if (_bannerEntry == entry) _bannerEntry = null;
            },
          ),
        ),
      ),
    );

    _bannerEntry = entry;
    overlay.insert(entry);
  }

  Future<bool> _confirmAction({
    required String title,
    required String message,
    required String confirmLabel,
  }) async {
    final result = await showDialog<bool>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          backgroundColor: paleCream,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: Text(
            title,
            style: const TextStyle(
              color: darkMaroon,
              fontWeight: FontWeight.bold,
            ),
          ),
          content: Text(message),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel', style: TextStyle(color: brown)),
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: darkMaroon,
                foregroundColor: Colors.white,
              ),
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(confirmLabel),
            ),
          ],
        );
      },
    );

    return result == true;
  }

  Future<void> _updateStage() async {
    if (_currentStage >= _stages.length - 1) return;

    // Confirm departure before starting the trip.
    if (_currentStage == 0) {
      final confirmed = await _confirmAction(
        title: 'Start Trip',
        message:
            'Confirm that your truck is ready and you are starting '
            'the trip from $origin to $destination.',
        confirmLabel: 'Start Trip',
      );

      if (!confirmed || !mounted) return;

      final now = DateTime.now();

      setState(() {
        _currentStage = 1;
        _departureTime = now;
        _isNavigating = true;
      });

      _showMessage('Trip started. Heading to Logistics.');
      if (_routePoints.isEmpty) _fetchRoute();
      return;
    }

    // Transition from heading to logistics to in transit.
    if (_currentStage == 1) {
      setState(() {
        _currentStage = 2;
        _transitTime = DateTime.now();
      });

      _showMessage('Status updated: In Transit.');
      return;
    }

    // Confirm arrival at the destination hub.
    if (_currentStage == 2) {
      final confirmed = await _confirmAction(
        title: 'Confirm Arrival',
        message: 'Have you arrived at $destination?',
        confirmLabel: 'Confirm Arrival',
      );

      if (!confirmed || !mounted) return;

      setState(() {
        _currentStage = 3;
        _arrivalTime = DateTime.now();
        _isNavigating = false;
      });

      _showMessage('Arrival at Logistics Hub recorded.');
      return;
    }

    // Confirm the handover of all parcels.
    if (_currentStage == 3) {
      final confirmed = await _confirmAction(
        title: 'Confirm Parcel Handover',
        message:
            'Confirm that all $parcelCount parcels under trip '
            '$tripId have been handed over to the Logistics team.',
        confirmLabel: 'Confirm Handover',
      );

      if (!confirmed || !mounted) return;

      setState(() {
        _currentStage = 4;
        _handoverTime = DateTime.now();
      });

      _showMessage('Handover completed successfully.');
    }
  }

  String _formatTime(DateTime? dateTime) {
    if (dateTime == null) return 'Not recorded';

    final hour = dateTime.hour % 12 == 0 ? 12 : dateTime.hour % 12;

    final minute = dateTime.minute.toString().padLeft(2, '0');
    final period = dateTime.hour >= 12 ? 'PM' : 'AM';

    return '$hour:$minute $period';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          'Trip Details',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            // Actual map tiles and current GPS marker.
            SizedBox(
              height: 250,
              width: double.infinity,
              child: Stack(
                children: [
                  FlutterMap(
                    mapController: _mapController,
                    options: MapOptions(
                      initialCameraFit: CameraFit.bounds(
                        bounds: LatLngBounds.fromPoints([
                          originLocation,
                          destinationLocation,
                        ]),
                        padding: const EdgeInsets.all(48),
                      ),
                      minZoom: 5,
                      maxZoom: 18,
                    ),
                    children: [
                      TileLayer(
                        urlTemplate:
                            'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                        userAgentPackageName:
                            'com.example.flutter_application_1',
                      ),

                      // Before a route is generated: a thin straight guide line.
                      // After tapping Navigate: the real road-following route.
                      PolylineLayer(
                        polylines: [
                          if (_routePoints.isEmpty)
                            Polyline(
                              points: [originLocation, destinationLocation],
                              strokeWidth: 3,
                              color: brown.withValues(alpha: 0.7),
                            )
                          else
                            Polyline(
                              points: _routePoints,
                              strokeWidth: 6,
                              color: maroon,
                              borderStrokeWidth: 2,
                              borderColor: Colors.white,
                            ),
                        ],
                      ),

                      MarkerLayer(
                        markers: [
                          Marker(
                            point: originLocation,
                            width: 48,
                            height: 48,
                            child: _mapMarker(
                              icon: Icons.warehouse_rounded,
                              color: darkMaroon,
                            ),
                          ),
                          Marker(
                            point: destinationLocation,
                            width: 48,
                            height: 48,
                            child: _mapMarker(
                              icon: Icons.location_on_rounded,
                              color: maroon,
                            ),
                          ),
                          if (_riderLocation != null)
                            Marker(
                              point: _riderLocation!,
                              width: 52,
                              height: 52,
                              child: _mapMarker(
                                icon: Icons.local_shipping_rounded,
                                color: Colors.blue.shade800,
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),

                  Positioned(
                    top: 12,
                    left: 12,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 8,
                      ),
                      decoration: BoxDecoration(
                        color: paleCream,
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: const [
                          BoxShadow(color: Colors.black12, blurRadius: 6),
                        ],
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.circle,
                            color: _riderLocation == null
                                ? brown
                                : Colors.green.shade700,
                            size: 10,
                          ),
                          const SizedBox(width: 7),
                          Text(
                            _riderLocation == null
                                ? 'Waiting for GPS'
                                : 'GPS location active',
                            style: const TextStyle(
                              color: darkMaroon,
                              fontWeight: FontWeight.bold,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  // Route summary chip (distance and duration).
                  if (_routeDistanceText != null && _routeDurationText != null)
                    Positioned(
                      top: 12,
                      right: 12,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 8,
                        ),
                        decoration: BoxDecoration(
                          color: darkMaroon,
                          borderRadius: BorderRadius.circular(12),
                          boxShadow: const [
                            BoxShadow(color: Colors.black26, blurRadius: 6),
                          ],
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(
                              Icons.alt_route_rounded,
                              color: cream,
                              size: 15,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              '$_routeDistanceText • $_routeDurationText',
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                  // Navigate button: generates the route on the map.
                  Positioned(
                    left: 12,
                    bottom: 12,
                    child: ElevatedButton.icon(
                      onPressed: _isLoadingRoute ? null : _fetchRoute,
                      icon: _isLoadingRoute
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : Icon(
                              _routePoints.isEmpty
                                  ? Icons.navigation_rounded
                                  : Icons.refresh_rounded,
                              size: 18,
                            ),
                      label: Text(
                        _isLoadingRoute
                            ? 'Finding route...'
                            : (_routePoints.isEmpty ? 'Navigate' : 'Re-route'),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: darkMaroon,
                        foregroundColor: Colors.white,
                        disabledBackgroundColor: maroon,
                        disabledForegroundColor: Colors.white70,
                        elevation: 4,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 12,
                        ),
                        textStyle: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 13,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(24),
                        ),
                      ),
                    ),
                  ),

                  Positioned(
                    right: 12,
                    bottom: 12,
                    child: FloatingActionButton.small(
                      heroTag: 'centerTripMap',
                      backgroundColor: paleCream,
                      foregroundColor: darkMaroon,
                      onPressed: () {
                        final target = _riderLocation ?? originLocation;

                        _mapController.move(target, 13);
                      },
                      child: const Icon(Icons.my_location_rounded),
                    ),
                  ),
                ],
              ),
            ),

            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _buildTripHeader(),
                    const SizedBox(height: 20),

                    _sectionTitle(Icons.timeline_rounded, 'Trip Progress'),
                    const SizedBox(height: 12),
                    _buildProgressTimeline(),

                    const SizedBox(height: 20),
                    _sectionTitle(Icons.route_rounded, 'Route Information'),
                    const SizedBox(height: 12),
                    _buildRouteInformation(),

                    const SizedBox(height: 20),
                    _sectionTitle(
                      Icons.inventory_2_outlined,
                      'Trip Information',
                    ),
                    const SizedBox(height: 12),
                    _buildTripInformation(),

                    const SizedBox(height: 24),
                    _buildActionButton(),

                    const SizedBox(height: 12),
                    const Center(
                      child: Text(
                        'Trip updates are currently stored locally.',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: brown, fontSize: 11),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _mapMarker({required IconData icon, required Color color}) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
        border: Border.all(color: color, width: 2),
        boxShadow: const [
          BoxShadow(color: Colors.black26, blurRadius: 7, offset: Offset(0, 2)),
        ],
      ),
      child: Icon(icon, color: color, size: 28),
    );
  }

  Widget _buildTripHeader() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [darkMaroon, maroon],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: darkMaroon.withValues(alpha: 0.25),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.local_shipping_rounded, color: cream, size: 28),
              const SizedBox(width: 10),
              const Expanded(
                child: Text(
                  'Assigned Trip',
                  style: TextStyle(
                    color: cream,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: cream,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  _stages[_currentStage],
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            tripId,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 26,
              fontWeight: FontWeight.bold,
              letterSpacing: 0.5,
            ),
          ),
          const SizedBox(height: 5),
          const Text(
            'Logistics Hub Transfer',
            style: TextStyle(color: cream, fontSize: 13),
          ),
          const SizedBox(height: 18),
          const Divider(color: Colors.white24),
          const SizedBox(height: 12),
          Row(
            children: [
              const Icon(Icons.access_time_rounded, color: cream, size: 20),
              const SizedBox(width: 8),
              const Expanded(
                child: Text(
                  'Estimated Arrival',
                  style: TextStyle(color: cream),
                ),
              ),
              Text(
                estimatedArrival,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _sectionTitle(IconData icon, String title) {
    return Row(
      children: [
        Icon(icon, color: darkMaroon, size: 23),
        const SizedBox(width: 8),
        Text(
          title,
          style: const TextStyle(
            color: darkMaroon,
            fontSize: 17,
            fontWeight: FontWeight.bold,
          ),
        ),
      ],
    );
  }

  // ---------------------------------------------------------------------------
  // Horizontal progress timeline
  // ---------------------------------------------------------------------------

  DateTime? _timeForStage(int index) {
    switch (index) {
      case 1:
        return _departureTime;
      case 2:
        return _transitTime;
      case 3:
        return _arrivalTime;
      case 4:
        return _handoverTime;
      default:
        return null;
    }
  }

  IconData _iconForStage(int index) {
    switch (index) {
      case 0:
        return Icons.assignment_turned_in_outlined;
      case 1:
        return Icons.directions_rounded;
      case 2:
        return Icons.local_shipping_rounded;
      case 3:
        return Icons.location_on_rounded;
      default:
        return Icons.verified_rounded;
    }
  }

  Widget _buildProgressTimeline() {
    final bool finished = _currentStage == _stages.length - 1;
    final int percent = ((_currentStage / (_stages.length - 1)) * 100).round();

    return Container(
      padding: const EdgeInsets.fromLTRB(8, 16, 8, 16),
      decoration: BoxDecoration(
        color: paleCream,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Progress summary
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Row(
              children: [
                Text(
                  'Step ${_currentStage + 1} of ${_stages.length}',
                  style: const TextStyle(
                    color: brown,
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    letterSpacing: 0.5,
                  ),
                ),
                const Spacer(),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: finished
                        ? Colors.green.withValues(alpha: 0.12)
                        : maroon.withValues(alpha: 0.10),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    '$percent% complete',
                    style: TextStyle(
                      color: finished ? Colors.green.shade800 : darkMaroon,
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),

          // Horizontal stepper
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: List.generate(
              _stages.length,
              (index) => Expanded(child: _buildHorizontalStep(index)),
            ),
          ),

          const SizedBox(height: 18),

          // Current stage detail panel
          _buildStageDetailPanel(finished),
        ],
      ),
    );
  }

  Widget _buildHorizontalStep(int index) {
    final bool completed = index < _currentStage;
    final bool active = index == _currentStage;
    final bool reached = completed || active;
    final bool isFirst = index == 0;
    final bool isLast = index == _stages.length - 1;
    final DateTime? recorded = _timeForStage(index);

    final bool allDone = _currentStage == _stages.length - 1;
    final Color activeColor = (active && allDone)
        ? Colors.green.shade700
        : darkMaroon;

    return Column(
      children: [
        SizedBox(
          height: 40,
          child: Row(
            children: [
              Expanded(
                child: Container(
                  height: 3,
                  decoration: BoxDecoration(
                    color: isFirst
                        ? Colors.transparent
                        : (reached ? maroon : border),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              AnimatedContainer(
                duration: const Duration(milliseconds: 300),
                width: active ? 38 : 32,
                height: active ? 38 : 32,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: reached
                      ? (active ? activeColor : darkMaroon)
                      : paleCream,
                  border: Border.all(
                    color: reached
                        ? (active ? activeColor : darkMaroon)
                        : border,
                    width: 2,
                  ),
                  boxShadow: active
                      ? [
                          BoxShadow(
                            color: activeColor.withValues(alpha: 0.28),
                            blurRadius: 0,
                            spreadRadius: 4,
                          ),
                        ]
                      : null,
                ),
                child: Icon(
                  completed ? Icons.check_rounded : _iconForStage(index),
                  color: reached ? Colors.white : brown.withValues(alpha: 0.6),
                  size: active ? 20 : 17,
                ),
              ),
              Expanded(
                child: Container(
                  height: 3,
                  decoration: BoxDecoration(
                    color: isLast
                        ? Colors.transparent
                        : (completed ? maroon : border),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 2),
          child: Text(
            _shortStages[index],
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: reached ? darkMaroon : brown,
              fontSize: 10.5,
              height: 1.2,
              fontWeight: active ? FontWeight.w800 : FontWeight.w600,
            ),
          ),
        ),
        const SizedBox(height: 3),
        Text(
          recorded != null ? _formatTime(recorded) : '--',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: recorded != null ? brown : Colors.black26,
            fontSize: 9.5,
          ),
        ),
      ],
    );
  }

  Widget _buildStageDetailPanel(bool finished) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.symmetric(horizontal: 8),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: finished
            ? Colors.green.withValues(alpha: 0.08)
            : maroon.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: finished ? Colors.green.shade300 : border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: finished ? Colors.green.shade700 : darkMaroon,
              shape: BoxShape.circle,
            ),
            child: Icon(
              finished
                  ? Icons.check_circle_rounded
                  : _iconForStage(_currentStage),
              color: Colors.white,
              size: 20,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'CURRENT STATUS',
                  style: TextStyle(
                    color: brown,
                    fontSize: 9.5,
                    fontWeight: FontWeight.bold,
                    letterSpacing: 0.8,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  _stages[_currentStage],
                  style: TextStyle(
                    color: finished ? Colors.green.shade800 : darkMaroon,
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _descriptions[_currentStage],
                  style: const TextStyle(
                    color: Colors.black54,
                    fontSize: 12,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------

  Widget _buildRouteInformation() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: paleCream,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: border),
      ),
      child: Column(
        children: [
          _routeRow(
            icon: Icons.warehouse_rounded,
            label: 'Starting Point',
            value: origin,
          ),
          const Padding(
            padding: EdgeInsets.only(left: 20),
            child: Align(
              alignment: Alignment.centerLeft,
              child: SizedBox(
                height: 30,
                child: VerticalDivider(color: brown, thickness: 2, width: 2),
              ),
            ),
          ),
          _routeRow(
            icon: Icons.location_on_rounded,
            label: 'Destination',
            value: destination,
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            height: 46,
            child: OutlinedButton.icon(
              onPressed: _openExternalNavigation,
              icon: const Icon(Icons.turn_right_rounded, size: 20),
              label: const Text('Open Turn-by-Turn in Maps App'),
              style: OutlinedButton.styleFrom(
                foregroundColor: darkMaroon,
                side: const BorderSide(color: maroon, width: 1.2),
                textStyle: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 13,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _routeRow({
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            color: maroon.withValues(alpha: 0.10),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, color: darkMaroon),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label.toUpperCase(),
                style: const TextStyle(
                  color: brown,
                  fontSize: 10,
                  fontWeight: FontWeight.bold,
                  letterSpacing: 0.6,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                value,
                style: const TextStyle(
                  color: darkMaroon,
                  fontSize: 14,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildTripInformation() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: paleCream,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: border),
      ),
      child: Column(
        children: [
          _infoRow(Icons.confirmation_number_outlined, 'Trip ID', tripId),
          const Divider(height: 24, color: border),
          _infoRow(
            Icons.inventory_2_outlined,
            'Total Parcels',
            '$parcelCount parcels',
          ),
          const Divider(height: 24, color: border),
          _infoRow(Icons.category_outlined, 'Cargo Type', cargo),
          const Divider(height: 24, color: border),
          _infoRow(
            Icons.straighten_rounded,
            'Estimated Distance',
            _routeDistanceText ?? distance,
          ),
          const Divider(height: 24, color: border),
          _infoRow(
            Icons.departure_board_rounded,
            'Departure',
            _formatTime(_departureTime),
          ),
          const Divider(height: 24, color: border),
          _infoRow(Icons.flag_outlined, 'Arrival', _formatTime(_arrivalTime)),
          const Divider(height: 24, color: border),
          _infoRow(
            Icons.fact_check_outlined,
            'Handover',
            _formatTime(_handoverTime),
          ),
        ],
      ),
    );
  }

  Widget _infoRow(IconData icon, String label, String value) {
    return Row(
      children: [
        Icon(icon, color: brown, size: 21),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            label,
            style: const TextStyle(color: Colors.black54, fontSize: 12),
          ),
        ),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 12,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildActionButton() {
    if (_currentStage == 4) {
      return SizedBox(
        width: double.infinity,
        height: 52,
        child: ElevatedButton.icon(
          onPressed: () => Navigator.pop(context),
          icon: const Icon(Icons.arrow_back_rounded),
          label: const Text('Back to Dashboard'),
          style: ElevatedButton.styleFrom(
            backgroundColor: darkMaroon,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
          ),
        ),
      );
    }

    final String buttonText;

    switch (_currentStage) {
      case 0:
        buttonText = 'Start Trip';
        break;
      case 1:
        buttonText = 'Mark In Transit';
        break;
      case 2:
        buttonText = 'Mark Arrived at Logistics';
        break;
      default:
        buttonText = 'Confirm Parcel Handover';
    }

    return SizedBox(
      width: double.infinity,
      height: 54,
      child: ElevatedButton.icon(
        onPressed: _updateStage,
        icon: Icon(
          _currentStage == 3
              ? Icons.verified_rounded
              : Icons.arrow_forward_rounded,
        ),
        label: Text(buttonText),
        style: ElevatedButton.styleFrom(
          backgroundColor: darkMaroon,
          foregroundColor: Colors.white,
          elevation: 3,
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
    );
  }
}

/// Animated banner that slides in from the top, then auto-dismisses.
class _TopBanner extends StatefulWidget {
  final String message;
  final VoidCallback onDismissed;

  const _TopBanner({
    super.key,
    required this.message,
    required this.onDismissed,
  });

  @override
  State<_TopBanner> createState() => _TopBannerState();
}

class _TopBannerState extends State<_TopBanner>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<Offset> _slide;
  late final Animation<double> _fade;
  Timer? _timer;
  bool _closing = false;

  @override
  void initState() {
    super.initState();

    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 320),
    );

    _slide = Tween<Offset>(
      begin: const Offset(0, -1.2),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic));

    _fade = CurvedAnimation(parent: _controller, curve: Curves.easeOut);

    _controller.forward();

    _timer = Timer(const Duration(milliseconds: 2800), _close);
  }

  Future<void> _close() async {
    if (_closing || !mounted) return;
    _closing = true;
    _timer?.cancel();

    await _controller.reverse();
    widget.onDismissed();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SlideTransition(
      position: _slide,
      child: FadeTransition(
        opacity: _fade,
        child: GestureDetector(
          onTap: _close,
          onVerticalDragEnd: (details) {
            if ((details.primaryVelocity ?? 0) < 0) _close();
          },
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              color: const Color(0xFF671B1D),
              borderRadius: BorderRadius.circular(14),
              boxShadow: const [
                BoxShadow(
                  color: Colors.black26,
                  blurRadius: 14,
                  offset: Offset(0, 5),
                ),
              ],
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: const BoxDecoration(
                    color: Color(0xFFFFF8E7),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.notifications_active_rounded,
                    color: Color(0xFF671B1D),
                    size: 16,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    widget.message,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

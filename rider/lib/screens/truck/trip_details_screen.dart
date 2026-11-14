import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../services/rider_api_service.dart';

class TripDetailsScreen extends StatefulWidget {
  const TripDetailsScreen({super.key, required this.task});

  final Map<String, dynamic> task;

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

  late Map<String, dynamic> _task;
  bool _isUpdating = false;
  String? _error;
  final MapController _mapController = MapController();
  StreamSubscription<Position>? _positionSubscription;
  LatLng? _originLocation;
  LatLng? _destinationLocation;
  LatLng? _truckLocation;
  List<LatLng> _routePoints = [];
  bool _loadingLocations = false;
  bool _loadingRoute = false;
  bool _didFitMap = false;

  int get _taskId => (_task['id'] as num).toInt();
  String get _trackingCode => (_task['tracking_code'] ?? 'Parcel').toString();
  String get _origin => (_task['origin'] ?? 'Origin hub').toString();
  String get _destination => (_task['destination'] ?? 'Destination hub').toString();
  String get _originAddress => _hubAddress('origin');
  String get _destinationAddress => _hubAddress('destination');
  String get _status => (_task['status'] ?? 'assigned').toString();
  String get _note => (_task['note'] ?? '').toString().trim();

  static const List<String> _stages = [
    'Assigned',
    'Heading to Hub',
    'In Transit',
    'Arrived at Hub',
    'Received by Hub',
  ];

  int get _currentStage => switch (_status) {
    'heading' => 1,
    'in_transit' => 2,
    'arrived' => 3,
    'completed' => 4,
    _ => 0,
  };

  bool get _isFailed => _status == 'failed';
  bool get _isWaitingForHub => _status == 'arrived';
  bool get _isComplete => _status == 'completed';

  @override
  void initState() {
    super.initState();
    _task = Map<String, dynamic>.from(widget.task);
    _loadHubLocations();
    _startTruckLocationTracking();
  }

  @override
  void dispose() {
    _positionSubscription?.cancel();
    super.dispose();
  }

  String _hubAddress(String prefix) {
    final name = prefix == 'origin' ? _origin : _destination;
    final municipality = (_task['${prefix}_municipality'] ?? '').toString();
    final province = (_task['${prefix}_province'] ?? '').toString();
    return [name, municipality, province]
        .where((value) => value.trim().isNotEmpty)
        .toSet()
        .join(', ');
  }

  Future<LatLng?> _geocodeHub(String address) async {
    try {
      final response = await http
          .get(
            Uri.https('nominatim.openstreetmap.org', '/search', {
              'q': address,
              'format': 'jsonv2',
              'limit': '1',
            }),
            headers: const {'User-Agent': 'LumearRider/1.0'},
          )
          .timeout(const Duration(seconds: 12));
      if (response.statusCode != 200) return null;
      final results = jsonDecode(response.body) as List<dynamic>;
      if (results.isEmpty) return null;
      final result = results.first as Map<String, dynamic>;
      final latitude = double.tryParse(result['lat']?.toString() ?? '');
      final longitude = double.tryParse(result['lon']?.toString() ?? '');
      if (latitude == null || longitude == null) return null;
      return LatLng(latitude, longitude);
    } catch (_) {
      return null;
    }
  }

  Future<void> _loadHubLocations() async {
    if (!mounted) return;
    setState(() => _loadingLocations = true);
    final origin = await _geocodeHub(_originAddress);
    await Future<void>.delayed(const Duration(seconds: 1));
    final destination = await _geocodeHub(_destinationAddress);
    if (!mounted) return;
    setState(() {
      _originLocation = origin;
      _destinationLocation = destination;
      _loadingLocations = false;
    });
    _fitMap(force: true);
  }

  Future<void> _startTruckLocationTracking() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) return;
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        return;
      }
      final position = await Geolocator.getCurrentPosition();
      if (!mounted) return;
      setState(() {
        _truckLocation = LatLng(position.latitude, position.longitude);
      });
      _fitMap(force: true);
      _positionSubscription = Geolocator.getPositionStream(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          distanceFilter: 20,
        ),
      ).listen((position) {
        if (!mounted) return;
        setState(() {
          _truckLocation = LatLng(position.latitude, position.longitude);
        });
        if (!_didFitMap) _fitMap();
      });
    } catch (_) {
      // Hub route remains usable even when device location is unavailable.
    }
  }

  void _fitMap({bool force = false}) {
    if (_didFitMap && !force) return;
    final points = [
      _originLocation,
      _destinationLocation,
      _truckLocation,
    ].whereType<LatLng>().toList();
    if (points.isEmpty) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      try {
        if (points.length == 1) {
          _mapController.move(points.first, 12);
        } else {
          _mapController.fitCamera(
            CameraFit.bounds(
              bounds: LatLngBounds.fromPoints(points),
              padding: const EdgeInsets.all(42),
            ),
          );
        }
        _didFitMap = true;
      } catch (_) {
        // The map controller may not be attached before the first frame.
      }
    });
  }

  Future<List<LatLng>?> _fetchRoute(LatLng from, LatLng to) async {
    try {
      final response = await http
          .get(
            Uri.parse(
              'https://router.project-osrm.org/route/v1/driving/'
              '${from.longitude},${from.latitude};${to.longitude},${to.latitude}'
              '?overview=full&geometries=geojson',
            ),
          )
          .timeout(const Duration(seconds: 12));
      if (response.statusCode != 200) return null;
      final data = jsonDecode(response.body) as Map<String, dynamic>;
      final routes = data['routes'] as List<dynamic>?;
      if (routes == null || routes.isEmpty) return null;
      final geometry = (routes.first as Map<String, dynamic>)['geometry'] as Map<String, dynamic>;
      final coordinates = geometry['coordinates'] as List<dynamic>;
      return coordinates.map((coordinate) {
        final pair = coordinate as List<dynamic>;
        return LatLng((pair[1] as num).toDouble(), (pair[0] as num).toDouble());
      }).toList();
    } catch (_) {
      return null;
    }
  }

  Future<void> _showRoute() async {
    final from = _originLocation;
    final to = _destinationLocation;
    if (from == null || to == null) {
      setState(() => _error = 'Hub locations are unavailable. Open route in Maps to navigate by hub name.');
      return;
    }
    setState(() {
      _loadingRoute = true;
      _error = null;
    });
    var route = await _fetchRoute(from, to);
    route ??= [from, to];
    if (!mounted) return;
    setState(() {
      _routePoints = route!;
      _loadingRoute = false;
    });
    _fitMap(force: true);
  }

  Widget _mapCard() {
    final hasHub = _originLocation != null || _destinationLocation != null;
    return Container(
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: paleCream,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 10, 10, 4),
            child: Row(
              children: [
                const Icon(Icons.map_outlined, color: darkMaroon),
                const SizedBox(width: 8),
                const Expanded(child: Text('Truck route map', style: TextStyle(color: darkMaroon, fontWeight: FontWeight.bold))),
                TextButton.icon(
                  onPressed: _loadingRoute ? null : _showRoute,
                  icon: _loadingRoute
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Icon(Icons.alt_route),
                  label: const Text('Show route'),
                ),
              ],
            ),
          ),
          SizedBox(
            height: 250,
            child: !hasHub
                ? Center(
                    child: Text(
                      _loadingLocations ? 'Finding hub locations…' : 'Hub map locations are unavailable.',
                      style: const TextStyle(color: brown),
                    ),
                  )
                : FlutterMap(
                    mapController: _mapController,
                    options: MapOptions(
                      initialCenter: _originLocation ?? _destinationLocation!,
                      initialZoom: 8,
                    ),
                    children: [
                      TileLayer(
                        urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                        userAgentPackageName: 'com.example.flutter_application_1',
                      ),
                      if (_routePoints.length > 1)
                        PolylineLayer(
                          polylines: [Polyline(points: _routePoints, strokeWidth: 5, color: maroon)],
                        ),
                      MarkerLayer(
                        markers: [
                          if (_originLocation != null)
                            Marker(
                              point: _originLocation!,
                              width: 46,
                              height: 46,
                              child: const Icon(Icons.warehouse_rounded, color: darkMaroon, size: 36),
                            ),
                          if (_destinationLocation != null)
                            Marker(
                              point: _destinationLocation!,
                              width: 46,
                              height: 46,
                              child: const Icon(Icons.location_on_rounded, color: maroon, size: 38),
                            ),
                          if (_truckLocation != null)
                            Marker(
                              point: _truckLocation!,
                              width: 44,
                              height: 44,
                              child: const Icon(Icons.local_shipping_rounded, color: Colors.blue, size: 34),
                            ),
                        ],
                      ),
                      RichAttributionWidget(
                        attributions: [TextSourceAttribution('© OpenStreetMap contributors')],
                      ),
                    ],
                  ),
          ),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Text(
              _truckLocation == null
                  ? 'Hub markers use their registered town and province. Enable location to show the truck’s live position.'
                  : 'Blue truck marker: this device’s live GPS · Hub pins may be approximate.',
              style: const TextStyle(color: brown, fontSize: 11),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _refresh() async {
    setState(() {
      _isUpdating = true;
      _error = null;
    });
    try {
      final tasks = await RiderApiService.tasks();
      final latest = tasks.where((task) => (task['id'] as num).toInt() == _taskId);
      if (latest.isEmpty) {
        throw const RiderApiException('This trip is no longer assigned to your account.');
      }
      if (mounted) setState(() => _task = Map<String, dynamic>.from(latest.first));
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _isUpdating = false);
    }
  }

  Future<bool> _confirmStart() async {
    return await showDialog<bool>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            backgroundColor: paleCream,
            title: const Text('Start hub transfer?'),
            content: Text('Confirm that you are leaving $_origin for $_destination with this parcel.'),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext, false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                style: FilledButton.styleFrom(backgroundColor: darkMaroon),
                onPressed: () => Navigator.pop(dialogContext, true),
                child: const Text('Start trip'),
              ),
            ],
          ),
        ) ??
        false;
  }

  Future<void> _advanceTrip() async {
    if (_isUpdating || _isWaitingForHub || _isComplete || _isFailed) return;

    final nextStatus = switch (_status) {
      'assigned' => 'heading',
      'heading' => 'in_transit',
      'in_transit' => 'arrived',
      _ => null,
    };
    if (nextStatus == null) return;

    if (_status == 'assigned' && !await _confirmStart()) return;

    setState(() {
      _isUpdating = true;
      _error = null;
    });
    try {
      await RiderApiService.updateTask(_taskId, nextStatus);
      if (mounted) {
        setState(() => _task = {..._task, 'status': nextStatus});
      }
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _isUpdating = false);
    }
  }

  Future<void> _openMaps() async {
    final uri = Uri.https('www.google.com', '/maps/dir/', {
      'api': '1',
      'origin': _originAddress,
      'destination': _destinationAddress,
      'travelmode': 'driving',
    });
    try {
      if (!await launchUrl(uri, mode: LaunchMode.externalApplication) && mounted) {
        setState(() => _error = 'Could not open a maps app.');
      }
    } catch (error) {
      if (mounted) setState(() => _error = 'Could not open a maps app.');
    }
  }

  String get _statusDescription => switch (_status) {
    'assigned' => 'Review the trip details, then confirm when you are ready to depart.',
    'heading' => 'You are heading to the origin hub to begin the long-distance transfer.',
    'in_transit' => 'The parcel is in your custody and travelling to the destination hub.',
    'arrived' => 'You marked arrival. Wait for the destination hub to receive and verify the parcel.',
    'completed' => 'The destination hub received the parcel.',
    'failed' => 'This trip was marked as failed. Contact your logistics hub for help.',
    _ => 'Trip status: $_status',
  };

  String get _buttonLabel => switch (_status) {
    'assigned' => 'Confirm heading to hub',
    'heading' => 'Mark in transit',
    'in_transit' => 'Confirm arrival at hub',
    _ => '',
  };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        title: const Text('Trip Details', style: TextStyle(fontWeight: FontWeight.bold)),
        actions: [
          IconButton(
            tooltip: 'Refresh trip',
            onPressed: _isUpdating ? null : _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _tripHeader(),
            const SizedBox(height: 18),
            _sectionTitle(Icons.timeline_rounded, 'Trip Progress'),
            const SizedBox(height: 10),
            _progressCard(),
            const SizedBox(height: 18),
            _sectionTitle(Icons.route_rounded, 'Route Information'),
            const SizedBox(height: 10),
            _routeCard(),
            const SizedBox(height: 10),
            _mapCard(),
            const SizedBox(height: 18),
            _sectionTitle(Icons.inventory_2_outlined, 'Parcel Information'),
            const SizedBox(height: 10),
            _parcelCard(),
            if (_error != null) ...[
              const SizedBox(height: 12),
              _messageCard(_error!, isError: true),
            ],
            if (_isWaitingForHub) ...[
              const SizedBox(height: 12),
              _messageCard('You have arrived. The receiving hub must confirm the parcel handover before this trip is complete.'),
            ],
            if (_buttonLabel.isNotEmpty) ...[
              const SizedBox(height: 18),
              SizedBox(
                height: 54,
                child: FilledButton.icon(
                  onPressed: _isUpdating ? null : _advanceTrip,
                  style: FilledButton.styleFrom(
                    backgroundColor: darkMaroon,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  icon: _isUpdating
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.arrow_forward_rounded),
                  label: Text(_isUpdating ? 'Updating trip...' : _buttonLabel),
                ),
              ),
            ],
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _tripHeader() {
    final statusLabel = _status.replaceAll('_', ' ');
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [darkMaroon, maroon]),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          const Icon(Icons.local_shipping_rounded, color: cream),
          const SizedBox(width: 9),
          const Expanded(child: Text('Long-distance hub transfer', style: TextStyle(color: cream, fontWeight: FontWeight.bold))),
          Text(statusLabel.toUpperCase(), style: const TextStyle(color: cream, fontSize: 11, fontWeight: FontWeight.bold)),
        ]),
        const SizedBox(height: 16),
        Text(_trackingCode, style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        Text(_statusDescription, style: const TextStyle(color: cream, height: 1.4)),
      ]),
    );
  }

  Widget _progressCard() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: paleCream, borderRadius: BorderRadius.circular(18), border: Border.all(color: border)),
      child: Column(children: List.generate(_stages.length, (index) {
        final reached = index <= _currentStage;
        final active = index == _currentStage;
        return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          SizedBox(
            width: 34,
            child: Column(children: [
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(shape: BoxShape.circle, color: reached ? darkMaroon : paleCream, border: Border.all(color: reached ? darkMaroon : border, width: 2)),
                child: reached ? const Icon(Icons.check_rounded, color: Colors.white, size: 17) : Center(child: Text('${index + 1}', style: const TextStyle(color: brown, fontWeight: FontWeight.bold))),
              ),
              if (index < _stages.length - 1) Container(width: 2, height: 26, color: index < _currentStage ? darkMaroon : border),
            ]),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(top: 4, bottom: 20),
              child: Text(_stages[index], style: TextStyle(color: reached ? darkMaroon : brown, fontWeight: active ? FontWeight.bold : FontWeight.w600)),
            ),
          ),
        ]);
      })),
    );
  }

  Widget _routeCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: paleCream, borderRadius: BorderRadius.circular(18), border: Border.all(color: border)),
      child: Column(children: [
        _routeRow(Icons.warehouse_rounded, 'Origin hub', _origin),
        const Padding(padding: EdgeInsets.only(left: 20), child: Align(alignment: Alignment.centerLeft, child: SizedBox(height: 25, child: VerticalDivider(color: brown, thickness: 2)))),
        _routeRow(Icons.location_on_rounded, 'Destination hub', _destination),
        const SizedBox(height: 14),
        SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: _openMaps,
            icon: const Icon(Icons.directions_rounded),
            label: const Text('Open route in Maps'),
            style: OutlinedButton.styleFrom(foregroundColor: darkMaroon, side: const BorderSide(color: maroon)),
          ),
        ),
        const SizedBox(height: 4),
        const Text('Map pins use each hub’s registered town and province, so locations may be approximate.', style: TextStyle(color: brown, fontSize: 11)),
      ]),
    );
  }

  Widget _routeRow(IconData icon, String label, String value) {
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(icon, color: darkMaroon),
      const SizedBox(width: 10),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label.toUpperCase(), style: const TextStyle(color: brown, fontSize: 10, fontWeight: FontWeight.bold, letterSpacing: 0.5)),
        const SizedBox(height: 4),
        Text(value, style: const TextStyle(color: darkMaroon, fontWeight: FontWeight.bold)),
      ])),
    ]);
  }

  Widget _parcelCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: paleCream, borderRadius: BorderRadius.circular(18), border: Border.all(color: border)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _infoRow('Tracking number', _trackingCode),
        const Divider(color: border),
        _infoRow('Trip status', _status.replaceAll('_', ' ')),
        if (_note.isNotEmpty) ...[
          const Divider(color: border),
          _infoRow('Logistics note', _note),
        ],
      ]),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Expanded(child: Text(label, style: const TextStyle(color: brown))),
        const SizedBox(width: 12),
        Flexible(child: Text(value, textAlign: TextAlign.right, style: const TextStyle(color: darkMaroon, fontWeight: FontWeight.bold))),
      ]),
    );
  }

  Widget _sectionTitle(IconData icon, String title) {
    return Row(children: [Icon(icon, color: darkMaroon, size: 22), const SizedBox(width: 8), Text(title, style: const TextStyle(color: darkMaroon, fontSize: 17, fontWeight: FontWeight.bold))]);
  }

  Widget _messageCard(String message, {bool isError = false}) {
    final color = isError ? Colors.red.shade800 : darkMaroon;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: isError ? Colors.red.shade50 : maroon.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(14), border: Border.all(color: isError ? Colors.red.shade200 : border)),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(isError ? Icons.error_outline_rounded : Icons.info_outline_rounded, color: color),
        const SizedBox(width: 10),
        Expanded(child: Text(message, style: TextStyle(color: color, height: 1.35))),
      ]),
    );
  }
}

import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:http/http.dart' as http;
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/rider_api_service.dart';

class ReturnPickupsScreen extends StatefulWidget {
  const ReturnPickupsScreen({super.key});

  @override
  State<ReturnPickupsScreen> createState() => _ReturnPickupsScreenState();
}

class _ReturnPickupsScreenState extends State<ReturnPickupsScreen> {
  static const Color wine = Color(0xFF671B1D);
  static const Color cream = Color(0xFFFFF8E7);
  static const Color muted = Color(0xFF9E7161);

  List<Map<String, dynamic>> _tasks = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _refresh();
  }

  Future<void> _refresh() async {
    if (mounted) setState(() => _loading = true);
    try {
      final tasks = await RiderApiService.tasks();
      if (!mounted) return;
      setState(() {
        _tasks = tasks
            .where(
              (task) =>
                  task['return_request_id'] != null &&
                  (task['return_leg'] == null ||
                      task['return_leg'] == 'pickup' ||
                      task['return_leg'] == 'seller_delivery'),
            )
            .toList();
        _error = null;
      });
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openDetails(Map<String, dynamic> task) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => ReturnPickupDetailsScreen(task: task),
      ),
    );
    await _refresh();
  }

  String _status(Map<String, dynamic> task) =>
      (task['status'] ?? 'assigned').toString().replaceAll('_', ' ');

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: cream,
    appBar: AppBar(
      backgroundColor: wine,
      foregroundColor: Colors.white,
      title: const Text('Return Pickups'),
      actions: [
        IconButton(
          onPressed: _loading ? null : _refresh,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: RefreshIndicator(
      onRefresh: _refresh,
      child: ListView(
        padding: const EdgeInsets.all(18),
        children: [
          const Text(
            'Pick up approved returns or deliver received items back to sellers.',
            style: TextStyle(color: muted),
          ),
          if (_loading) const LinearProgressIndicator(color: wine),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(top: 16),
              child: Text(_error!, style: const TextStyle(color: Colors.red)),
            ),
          if (!_loading && _tasks.isEmpty)
            const Padding(
              padding: EdgeInsets.all(28),
              child: Text(
                'No return assignments. Buyer pickups and seller deliveries assigned by Logistics will appear here.',
              ),
            ),
          for (final task in _tasks) ...[
            const SizedBox(height: 14),
            Card(
              color: Colors.white,
              child: InkWell(
                borderRadius: BorderRadius.circular(12),
                onTap: () => _openDetails(task),
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(
                            Icons.assignment_return_rounded,
                            color: wine,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              '${task['return_leg'] == 'seller_delivery' ? 'Return Delivery' : 'Return Pickup'} · ${task['reference'] ?? task['tracking_code'] ?? 'Return'}',
                              style: const TextStyle(
                                color: wine,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          Text(
                            _status(task),
                            style: const TextStyle(
                              color: muted,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Text(
                        '${task['return_product_name'] ?? 'Returned item'} · ${task['return_leg'] == 'seller_delivery' ? task['seller_name'] ?? 'Seller' : task['return_buyer_name'] ?? 'Buyer'}',
                      ),
                      const SizedBox(height: 8),
                      const Text(
                        'Open Return Details →',
                        style: TextStyle(
                          color: wine,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    ),
  );
}

class ReturnPickupDetailsScreen extends StatefulWidget {
  const ReturnPickupDetailsScreen({super.key, required this.task});

  final Map<String, dynamic> task;

  @override
  State<ReturnPickupDetailsScreen> createState() =>
      _ReturnPickupDetailsScreenState();
}

class _ReturnPickupDetailsScreenState extends State<ReturnPickupDetailsScreen> {
  static const Color wine = Color(0xFF671B1D);
  static const Color cream = Color(0xFFFFF8E7);
  static const Color muted = Color(0xFF9E7161);
  late Map<String, dynamic> _task;
  bool _updating = false;
  String? _error;
  final MapController _mapController = MapController();
  StreamSubscription<Position>? _positionSubscription;
  LatLng? _sellerLocation;
  LatLng? _riderLocation;
  List<LatLng> _routePoints = [];
  bool _loadingSellerLocation = false;
  bool _loadingRoute = false;
  bool _didFitMap = false;

  @override
  void initState() {
    super.initState();
    _task = Map<String, dynamic>.from(widget.task);
    _sellerLocation = _coordinatesFromTask();
    if (_isSellerDelivery) {
      _loadSellerLocation();
      _startLocationTracking();
    }
  }

  @override
  void dispose() {
    _positionSubscription?.cancel();
    super.dispose();
  }

  int get _taskId => (_task['id'] as num).toInt();
  String get _status => (_task['status'] ?? 'assigned').toString();
  bool get _isSellerDelivery => _task['return_leg'] == 'seller_delivery';

  String get _buyerAddress {
    dynamic address = _task['shipping_address'];
    if (address is String) {
      try {
        address = jsonDecode(address);
      } on FormatException {
        return 'Pickup address unavailable';
      }
    }
    if (address is! Map) return 'Pickup address unavailable';
    final fields = ['line1', 'barangay', 'city', 'province']
        .map((key) => address[key]?.toString() ?? '')
        .where((value) => value.trim().isNotEmpty);
    final formatted = fields.join(', ');
    return formatted.isEmpty ? 'Pickup address unavailable' : formatted;
  }

  String get _sellerAddress {
    final fields = [
      _task['pickup_address'],
      _task['pickup_city'],
      _task['pickup_province'],
    ].map((value) => value?.toString() ?? '').where((value) => value.trim().isNotEmpty);
    final address = fields.join(', ');
    return address.isEmpty ? 'Seller address unavailable' : address;
  }

  String get _taskAddress => _isSellerDelivery ? _sellerAddress : _buyerAddress;

  LatLng? _coordinatesFromTask() {
    final latitude = double.tryParse(
      (_task['pickup_latitude'] ?? _task['seller_latitude'] ?? '').toString(),
    );
    final longitude = double.tryParse(
      (_task['pickup_longitude'] ?? _task['seller_longitude'] ?? '').toString(),
    );
    if (latitude == null || longitude == null) return null;
    if (latitude == 0 || longitude == 0) return null;
    return LatLng(latitude, longitude);
  }

  Future<void> _loadSellerLocation() async {
    if (!_isSellerDelivery || _sellerLocation != null) return;
    final address = _sellerAddress;
    if (address == 'Seller address unavailable') return;

    setState(() => _loadingSellerLocation = true);
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
      if (response.statusCode != 200) return;
      final results = jsonDecode(response.body) as List<dynamic>;
      if (results.isEmpty || !mounted) return;
      final result = results.first as Map<String, dynamic>;
      final latitude = double.tryParse(result['lat']?.toString() ?? '');
      final longitude = double.tryParse(result['lon']?.toString() ?? '');
      if (latitude == null || longitude == null) return;
      setState(() => _sellerLocation = LatLng(latitude, longitude));
      _fitMap(force: true);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not locate the seller address on the map.');
    } finally {
      if (mounted) setState(() => _loadingSellerLocation = false);
    }
  }

  Future<void> _startLocationTracking() async {
    if (!await Geolocator.isLocationServiceEnabled()) return;
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      return;
    }

    _positionSubscription = Geolocator.getPositionStream(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: 10,
      ),
    ).listen((position) {
      if (!mounted) return;
      setState(() {
        _riderLocation = LatLng(position.latitude, position.longitude);
      });
      if (!_didFitMap) _fitMap();
    });
  }

  void _fitMap({bool force = false}) {
    if (_didFitMap && !force) return;
    final points = [_sellerLocation, _riderLocation].whereType<LatLng>().toList();
    if (points.isEmpty) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      try {
        if (points.length == 1) {
          _mapController.move(points.first, 15);
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
        // The map may not be attached until the card is built.
      }
    });
  }

  Future<List<LatLng>?> _fetchRoadRoute(LatLng from, LatLng to) async {
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
    final destination = _sellerLocation;
    final rider = _riderLocation;
    if (destination == null) {
      setState(() => _error = 'Seller location is not available yet.');
      return;
    }
    if (rider == null) {
      setState(() => _error = 'Turn on location permission to show your live position.');
      return;
    }
    setState(() {
      _loadingRoute = true;
      _error = null;
    });
    var route = await _fetchRoadRoute(rider, destination);
    route ??= [rider, destination];
    if (!mounted) return;
    setState(() {
      _routePoints = route!;
      _loadingRoute = false;
    });
    _fitMap(force: true);
  }

  Widget _buildMapCard() {
    final destination = _sellerLocation;
    return Card(
      color: Colors.white,
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
            child: Row(
              children: [
                const Icon(Icons.map_outlined, color: wine),
                const SizedBox(width: 8),
                const Expanded(child: Text('Seller location', style: TextStyle(color: wine, fontWeight: FontWeight.w800))),
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
            child: destination == null
                ? Center(
                    child: Text(
                      _loadingSellerLocation ? 'Finding seller location…' : 'Seller location is not available on the map.',
                      style: const TextStyle(color: muted),
                    ),
                  )
                : FlutterMap(
                    mapController: _mapController,
                    options: MapOptions(initialCenter: destination, initialZoom: 14),
                    children: [
                      TileLayer(
                        urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                        userAgentPackageName: 'com.example.flutter_application_1',
                      ),
                      if (_routePoints.length > 1)
                        PolylineLayer(
                          polylines: [Polyline(points: _routePoints, strokeWidth: 5, color: wine)],
                        ),
                      MarkerLayer(
                        markers: [
                          Marker(
                            point: destination,
                            width: 46,
                            height: 46,
                            child: const Icon(Icons.storefront_rounded, color: wine, size: 38),
                          ),
                          if (_riderLocation != null)
                            Marker(
                              point: _riderLocation!,
                              width: 44,
                              height: 44,
                              child: const Icon(Icons.navigation_rounded, color: Colors.blue, size: 34),
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
              _riderLocation == null
                  ? 'Seller pin is based on the saved address. Enable location to show your live position.'
                  : 'Blue marker: your live GPS · Store marker: seller address${_coordinatesFromTask() == null ? ' (approximate)' : ''}',
              style: const TextStyle(color: muted, fontSize: 12),
            ),
          ),
        ],
      ),
    );
  }

  String? get _nextStatus => _isSellerDelivery
      ? switch (_status) {
          'assigned' => 'heading',
          'heading' => 'arrived',
          'arrived' => 'delivered',
          _ => null,
        }
      : switch (_status) {
          'assigned' => 'heading',
          'heading' => 'arrived',
          'arrived' => 'collected',
          _ => null,
        };

  String get _nextLabel => _isSellerDelivery
      ? switch (_nextStatus) {
          'heading' => 'Start return delivery',
          'arrived' => 'Arrived at seller',
          'delivered' => 'Confirm delivered to seller',
          _ => '',
        }
      : switch (_nextStatus) {
          'heading' => 'Start return pickup',
          'arrived' => 'Arrived at buyer',
          'collected' => 'Confirm item collected',
          _ => '',
        };

  Future<void> _refresh() async {
    setState(() {
      _updating = true;
      _error = null;
    });
    try {
      final tasks = await RiderApiService.tasks();
      final matching = tasks.where(
        (task) => (task['id'] as num?)?.toInt() == _taskId,
      );
      if (matching.isEmpty) {
        throw const RiderApiException(
          'This return task is no longer assigned to you.',
        );
      }
      if (mounted) {
        setState(() => _task = Map<String, dynamic>.from(matching.first));
      }
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _updating = false);
    }
  }

  Future<void> _advance() async {
    final next = _nextStatus;
    if (_updating || next == null) return;
    setState(() {
      _updating = true;
      _error = null;
    });
    try {
      await RiderApiService.updateTask(_taskId, next);
      if (mounted) setState(() => _task = {..._task, 'status': next});
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _updating = false);
    }
  }

  Future<void> _openMaps() async {
    final seller = _sellerLocation;
    final rider = _riderLocation;
    final uri = Uri.https('www.google.com', '/maps/dir/', {
      'api': '1',
      'destination': seller == null
          ? _taskAddress
          : '${seller.latitude},${seller.longitude}',
      if (rider != null) 'origin': '${rider.latitude},${rider.longitude}',
      'travelmode': 'driving',
    });
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication) &&
        mounted) {
      setState(() => _error = 'Could not open a maps app.');
    }
  }

  Future<void> _callBuyer() async {
    final phone = (_isSellerDelivery
            ? _task['seller_phone'] ?? ''
            : _task['return_buyer_phone'] ?? '')
        .toString()
        .trim();
    if (phone.isEmpty) {
      setState(() => _error = 'No contact number is available.');
      return;
    }
    final uri = Uri(scheme: 'tel', path: phone);
    if (!await launchUrl(uri) && mounted) {
      setState(() => _error = 'Could not start a phone call.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final stages = _isSellerDelivery
        ? ['assigned', 'heading', 'arrived', 'delivered']
        : ['assigned', 'heading', 'arrived', 'collected'];
    final stage = stages.indexOf(_status).clamp(0, stages.length - 1);
    final title =
        '${_isSellerDelivery ? 'Return Delivery' : 'Return Pickup'} · ${_task['reference'] ?? _task['tracking_code'] ?? 'Return'}';
    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: wine,
        foregroundColor: Colors.white,
        title: Text(_isSellerDelivery ? 'Return Delivery Details' : 'Return Pickup Details'),
        actions: [
          IconButton(
            onPressed: _updating ? null : _refresh,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 16, 18, 30),
        children: [
          Text(
            title,
            style: const TextStyle(
              color: wine,
              fontSize: 21,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 14),
          Card(
            color: Colors.white,
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _isSellerDelivery ? 'DELIVER TO SELLER' : 'PICK UP FROM BUYER',
                    style: TextStyle(
                      color: Color(0xFF9E7161),
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1,
                    ),
                  ),
                  const SizedBox(height: 12),
                  _DetailRow(
                    label: _isSellerDelivery ? 'Seller' : 'Buyer',
                    value: (_isSellerDelivery
                            ? _task['seller_name']
                            : _task['return_buyer_name'] ?? 'Buyer')
                        .toString(),
                  ),
                  _DetailRow(
                    label: 'Phone',
                    value: (_isSellerDelivery
                            ? _task['seller_phone']
                            : _task['return_buyer_phone'] ?? 'Not provided')
                        .toString(),
                  ),
                  _DetailRow(
                    label: _isSellerDelivery ? 'Seller address' : 'Pickup address',
                    value: _taskAddress,
                  ),
                  _DetailRow(
                    label: 'Item',
                    value: (_task['return_product_name'] ?? 'Returned item')
                        .toString(),
                  ),
                  _DetailRow(
                    label: 'Waybill',
                    value: (_task['tracking_code'] ?? 'Not provided')
                        .toString(),
                  ),
                  _DetailRow(
                    label: 'Return reason',
                    value: (_task['return_reason'] ?? 'Not provided')
                        .toString(),
                  ),
                  if ((_task['return_description'] ?? '').toString().isNotEmpty)
                    _DetailRow(
                      label: 'Buyer details',
                      value: _task['return_description'].toString(),
                    ),
                  if (_isSellerDelivery && _task['return_destination_provider_id'] != null)
                    _DetailRow(
                      label: 'Seller hub',
                      value: (_task['destination'] ?? 'Assigned hub').toString(),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),
          if (_isSellerDelivery) ...[
            _buildMapCard(),
            const SizedBox(height: 14),
          ],
          _ReturnProgressCard(stage: stage, status: _status, isSellerDelivery: _isSellerDelivery),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _callBuyer,
                  icon: const Icon(Icons.call),
                  label: Text(_isSellerDelivery ? 'Call seller' : 'Call buyer'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _openMaps,
                  icon: const Icon(Icons.navigation),
                  label: const Text('Navigate'),
                ),
              ),
            ],
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(_error!, style: const TextStyle(color: Colors.red)),
            ),
          if (_nextStatus != null) ...[
            const SizedBox(height: 10),
            SizedBox(
              height: 52,
              child: FilledButton.icon(
                onPressed: _updating ? null : _advance,
                style: FilledButton.styleFrom(
                  backgroundColor: wine,
                  foregroundColor: Colors.white,
                ),
                icon: _updating
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.assignment_return),
                label: Text(_nextLabel),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _ReturnProgressCard extends StatelessWidget {
  const _ReturnProgressCard({required this.stage, required this.status, required this.isSellerDelivery});

  final int stage;
  final String status;
  final bool isSellerDelivery;

  static const Color _wine = Color(0xFF671B1D);
  static const Color _muted = Color(0xFF9E7161);
  static const Color _border = Color(0xFFE9D8C8);
  static const Color _future = Color(0xFFD9C9BE);

  List<String> get _labels => isSellerDelivery
      ? const ['Assigned', 'Heading to\nseller', 'Arrived at\nseller', 'Delivered']
      : const ['Assigned', 'Heading to\nbuyer', 'Arrived at\nbuyer', 'Collected'];

  bool _isComplete(int index) =>
      index < stage ||
      ((status == 'collected' || status == 'delivered') && index == stage);

  String get _statusLabel => switch (status) {
    'assigned' => 'Assigned',
    'heading' => 'On the way',
    'arrived' => isSellerDelivery ? 'At seller' : 'At buyer',
    'collected' => 'Collected at buyer',
    'delivered' => 'Delivered to seller',
    _ => status.replaceAll('_', ' '),
  };

  @override
  Widget build(BuildContext context) {
    final isComplete = status == 'collected' || status == 'delivered';

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: _border),
        boxShadow: const [
          BoxShadow(
            color: Color(0x10000000),
            blurRadius: 14,
            offset: Offset(0, 5),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: const Color(0xFFF7EEE8),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: const Icon(Icons.route_rounded, color: _wine),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      isSellerDelivery ? 'Seller delivery progress' : 'Return pickup progress',
                      style: const TextStyle(
                        color: _wine,
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      isSellerDelivery ? 'Return parcel handoff to seller' : 'Collect item and bring it to the hub',
                      style: const TextStyle(color: _muted, fontSize: 12),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                decoration: BoxDecoration(
                  color: isComplete
                      ? const Color(0xFFEAF4E8)
                      : const Color(0xFFF8EFDF),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  _statusLabel,
                  style: TextStyle(
                    color: isComplete ? const Color(0xFF36724B) : _wine,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 22),
          Row(
            children: [
              for (var i = 0; i < _labels.length; i++) ...[
                Expanded(
                  flex: 5,
                  child: Center(
                    child: Container(
                      width: 32,
                      height: 32,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: _isComplete(i) || i == stage
                            ? _wine
                            : Colors.white,
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: _isComplete(i) || i == stage ? _wine : _future,
                          width: 2,
                        ),
                      ),
                      child: _isComplete(i)
                          ? const Icon(
                              Icons.check_rounded,
                              color: Colors.white,
                              size: 19,
                            )
                          : i == stage
                          ? Text(
                              '${i + 1}',
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w800,
                                fontSize: 13,
                              ),
                            )
                          : null,
                    ),
                  ),
                ),
                if (i < _labels.length - 1)
                  Expanded(
                    flex: 2,
                    child: Container(
                      height: 3,
                      decoration: BoxDecoration(
                        color: i < stage ? _wine : _future,
                        borderRadius: BorderRadius.circular(4),
                      ),
                    ),
                  ),
              ],
            ],
          ),
          const SizedBox(height: 9),
          Row(
            children: [
              for (var i = 0; i < _labels.length; i++) ...[
                Expanded(
                  flex: 5,
                  child: Text(
                    _labels[i],
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    style: TextStyle(
                      color: _isComplete(i) || i == stage ? _wine : _muted,
                      fontSize: 10.5,
                      height: 1.15,
                      fontWeight: _isComplete(i) || i == stage
                          ? FontWeight.w800
                          : FontWeight.w600,
                    ),
                  ),
                ),
                if (i < _labels.length - 1)
                  const Expanded(flex: 2, child: SizedBox()),
              ],
            ],
          ),
          if (isComplete) ...[
            const SizedBox(height: 16),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(13),
              decoration: BoxDecoration(
                color: const Color(0xFFF7EEE8),
                borderRadius: BorderRadius.circular(13),
              ),
              child: Text(
                isSellerDelivery
                    ? 'Return parcel delivered to the seller.'
                    : 'Return the item to your assigned hub. Logistics must confirm receipt there to complete this task.',
                style: const TextStyle(color: _wine, fontSize: 12, height: 1.4),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 7),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            color: Color(0xFF9E7161),
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: const TextStyle(
            color: Color(0xFF39272B),
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    ),
  );
}

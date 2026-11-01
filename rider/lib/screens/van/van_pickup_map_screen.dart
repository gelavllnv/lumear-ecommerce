import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';

import 'van_pickup_widgets.dart';

class VanPickupMapScreen extends StatefulWidget {
  final String sellerName;
  final String sellerAddress;
  final double sellerLatitude;
  final double sellerLongitude;

  const VanPickupMapScreen({
    super.key,
    required this.sellerName,
    required this.sellerAddress,
    required this.sellerLatitude,
    required this.sellerLongitude,
  });

  @override
  State<VanPickupMapScreen> createState() => _VanPickupMapScreenState();
}

class _VanPickupMapScreenState extends State<VanPickupMapScreen> {
  final MapController _mapController = MapController();

  StreamSubscription<Position>? _positionSubscription;
  Position? _currentPosition;

  bool _loading = true;
  String? _errorMessage;

  LatLng get _sellerLocation =>
      LatLng(widget.sellerLatitude, widget.sellerLongitude);

  @override
  void initState() {
    super.initState();
    _startLocationTracking();
  }

  Future<void> _startLocationTracking() async {
    try {
      final serviceEnabled = await Geolocator.isLocationServiceEnabled();

      if (!serviceEnabled) {
        throw Exception('Location services are disabled. Please enable GPS.');
      }

      LocationPermission permission = await Geolocator.checkPermission();

      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }

      if (permission == LocationPermission.denied) {
        throw Exception('Location permission was denied.');
      }

      if (permission == LocationPermission.deniedForever) {
        throw Exception(
          'Location permission is permanently denied. '
          'Enable it in your device settings.',
        );
      }

      final position = await Geolocator.getCurrentPosition();

      if (!mounted) return;

      setState(() {
        _currentPosition = position;
        _loading = false;
        _errorMessage = null;
      });

      _moveToCurrentPosition(position);

      _positionSubscription =
          Geolocator.getPositionStream(
            locationSettings: const LocationSettings(
              accuracy: LocationAccuracy.high,
              distanceFilter: 10,
            ),
          ).listen(
            (position) {
              if (!mounted) return;

              setState(() {
                _currentPosition = position;
              });
            },
            onError: (Object error) {
              if (!mounted) return;

              setState(() {
                _errorMessage = 'Unable to update rider location.';
              });
            },
          );
    } catch (error) {
      if (!mounted) return;

      setState(() {
        _loading = false;
        _errorMessage = error.toString().replaceFirst('Exception: ', '');
      });
    }
  }

  void _moveToCurrentPosition(Position position) {
    _mapController.move(LatLng(position.latitude, position.longitude), 15);
  }

  void _centerOnRider() {
    final position = _currentPosition;
    if (position == null) return;

    _moveToCurrentPosition(position);
  }

  void _centerOnSeller() {
    _mapController.move(_sellerLocation, 15);
  }

  double _distanceToSeller() {
    final position = _currentPosition;
    if (position == null) return 0;

    return Geolocator.distanceBetween(
      position.latitude,
      position.longitude,
      widget.sellerLatitude,
      widget.sellerLongitude,
    );
  }

  @override
  void dispose() {
    _positionSubscription?.cancel();
    _mapController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final riderPosition = _currentPosition;

    final initialCenter = riderPosition == null
        ? _sellerLocation
        : LatLng(riderPosition.latitude, riderPosition.longitude);

    return Scaffold(
      backgroundColor: vanCream,
      appBar: AppBar(
        backgroundColor: vanDarkMaroon,
        foregroundColor: Colors.white,
        title: const Text('Navigate to Seller'),
      ),
      body: Column(
        children: [
          Expanded(
            child: Stack(
              children: [
                FlutterMap(
                  mapController: _mapController,
                  options: MapOptions(
                    initialCenter: initialCenter,
                    initialZoom: 13,
                  ),
                  children: [
                    TileLayer(
                      urlTemplate: 'https://api.maptiler.com/maps/streets/{z}/{x}/{y}.png?key=YOUR_MAPTILER_API_KEY',
                      userAgentPackageName: 'com.lumear.rider',
                    ),
                    MarkerLayer(
                      markers: [
                        Marker(
                          point: _sellerLocation,
                          width: 170,
                          height: 75,
                          child: Column(
                            children: [
                              const Icon(
                                Icons.store,
                                color: vanDarkMaroon,
                                size: 36,
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 8,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Text(
                                  widget.sellerName,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        if (riderPosition != null)
                          Marker(
                            point: LatLng(
                              riderPosition.latitude,
                              riderPosition.longitude,
                            ),
                            width: 50,
                            height: 50,
                            child: Container(
                              decoration: BoxDecoration(
                                color: Colors.blue.withValues(alpha: 0.15),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(
                                Icons.navigation,
                                color: Colors.blue,
                                size: 30,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
                if (_loading) const Center(child: CircularProgressIndicator()),
                if (_errorMessage != null)
                  Positioned(
                    top: 16,
                    left: 16,
                    right: 16,
                    child: Material(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      elevation: 4,
                      child: Padding(
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          children: [
                            Text(_errorMessage!, textAlign: TextAlign.center),
                            const SizedBox(height: 8),
                            TextButton(
                              onPressed: _startLocationTracking,
                              child: const Text('Try Again'),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                Positioned(
                  right: 16,
                  bottom: 18,
                  child: Column(
                    children: [
                      FloatingActionButton.small(
                        heroTag: 'center_rider',
                        backgroundColor: Colors.white,
                        foregroundColor: vanDarkMaroon,
                        onPressed: _centerOnRider,
                        child: const Icon(Icons.my_location),
                      ),
                      const SizedBox(height: 10),
                      FloatingActionButton.small(
                        heroTag: 'center_seller',
                        backgroundColor: Colors.white,
                        foregroundColor: vanDarkMaroon,
                        onPressed: _centerOnSeller,
                        child: const Icon(Icons.store),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(18),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
            ),
            child: SafeArea(
              top: false,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    widget.sellerName,
                    style: const TextStyle(
                      color: vanDarkMaroon,
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    widget.sellerAddress,
                    style: const TextStyle(color: Colors.black54, fontSize: 13),
                  ),
                  const SizedBox(height: 12),
                  if (riderPosition != null)
                    Text(
                      'Straight-line distance: '
                      '${(_distanceToSeller() / 1000).toStringAsFixed(2)} km',
                      style: const TextStyle(
                        color: vanMaroon,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  const SizedBox(height: 14),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.check_circle_outline),
                      label: const Text('Back to Pickup Details'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: vanDarkMaroon,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 15),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

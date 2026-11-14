import 'dart:convert';

import 'package:flutter/material.dart';

import '../models/rider_profile.dart';
import '../services/rider_api_service.dart';
import 'login_screen.dart';

class RiderTasksScreen extends StatefulWidget {
  const RiderTasksScreen({super.key});

  @override
  State<RiderTasksScreen> createState() => _RiderTasksScreenState();
}

class _RiderTasksScreenState extends State<RiderTasksScreen> {
  List<Map<String, dynamic>> tasks = [];
  String? error;
  bool loading = true;

  @override
  void initState() {
    super.initState();
    refresh();
  }

  Future<void> refresh() async {
    try {
      final result = await RiderApiService.tasks();
      if (mounted) setState(() { tasks = result; error = null; });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> update(Map<String, dynamic> task, String status) async {
    if (loading) return;
    setState(() => loading = true);
    try {
      await RiderApiService.updateTask((task['id'] as num).toInt(), status);
      await refresh();
    } catch (e) {
      if (mounted) setState(() { error = e.toString(); loading = false; });
    }
  }

  String _returnLeg(Map<String, dynamic> task) =>
      (task['return_leg'] ?? 'pickup').toString();

  bool _isReturnTask(Map<String, dynamic> task) =>
      task['return_request_id'] != null;

  bool _isReturnPickup(Map<String, dynamic> task) =>
      _isReturnTask(task) && _returnLeg(task) == 'pickup';

  bool _isReturnDelivery(Map<String, dynamic> task) =>
      _isReturnTask(task) && _returnLeg(task) == 'seller_delivery';

  bool _isReturnTransfer(Map<String, dynamic> task) =>
      _isReturnTask(task) && _returnLeg(task) == 'hub_transfer';

  String? next(Map<String, dynamic> task) {
    if (_isReturnPickup(task)) {
      return switch (task['status']) {
        'assigned' => 'heading',
        'heading' => 'arrived',
        'arrived' => 'collected',
        _ => null,
      };
    }

    if (_isReturnDelivery(task)) {
      return switch (task['status']) {
        'assigned' => 'heading',
        'heading' => 'arrived',
        'arrived' => 'delivered',
        _ => null,
      };
    }

    return switch (task['status']) {
      'assigned' => 'heading',
      'heading' => task['job_type'] == 'Seller Pickup' ? 'arrived' : 'in_transit',
      'in_transit' => 'arrived',
      'arrived' when task['job_type'] == 'Seller Pickup' => 'collected',
      _ => null,
    };
  }

  String _returnPickupAddress(Map<String, dynamic> task) {
    final rawAddress = task['shipping_address'];
    dynamic address = rawAddress;
    if (rawAddress is String) {
      try {
        address = jsonDecode(rawAddress);
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

  String _returnSellerAddress(Map<String, dynamic> task) {
    final fields = [task['pickup_address'], task['pickup_city'], task['pickup_province']]
        .map((value) => value?.toString() ?? '')
        .where((value) => value.trim().isNotEmpty);
    final address = fields.join(', ');
    return address.isEmpty ? 'Seller address unavailable' : address;
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(
        RiderProfile.normalizedVehicleType == 'truck'
            ? 'Truck Trips'
            : RiderProfile.jobType,
      ),
      actions: [
      IconButton(onPressed: loading ? null : refresh, icon: const Icon(Icons.refresh)),
      IconButton(onPressed: () async {
        await RiderApiService.logout();
        if (context.mounted) Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false);
      }, icon: const Icon(Icons.logout)),
      ],
    ),
    body: RefreshIndicator(onRefresh: refresh, child: ListView(padding: const EdgeInsets.all(20), children: [
      Text('${RiderProfile.name} · ${RiderProfile.vehicleType}', style: Theme.of(context).textTheme.titleLarge),
      SwitchListTile(title: const Text('Available for assignments'), value: RiderProfile.isAvailable ?? false, onChanged: loading ? null : (value) async {
        try { await RiderApiService.updateAvailability(value); if (mounted) setState(() {}); }
        catch (e) { if (mounted) setState(() => error = e.toString()); }
      }),
      if (loading) const LinearProgressIndicator(),
      if (error != null) Text(error!, style: const TextStyle(color: Colors.red)),
      if (!loading && tasks.isEmpty) const Padding(padding: EdgeInsets.all(24), child: Text('No assignments yet. Your logistics provider assigns work to this account.')),
      for (final task in tasks) Card(child: Padding(padding: const EdgeInsets.all(18), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(_isReturnTask(task) ? '${_isReturnPickup(task) ? 'Return pickup' : _isReturnTransfer(task) ? 'Return truck transfer' : 'Return delivery'} · ${task['reference']}' : task['tracking_code'].toString(), style: Theme.of(context).textTheme.titleMedium),
        if (_isReturnPickup(task) || _isReturnDelivery(task)) ...[
          Text('Item: ${task['return_product_name'] ?? 'Returned product'}'),
          Text(_isReturnDelivery(task) ? 'Seller: ${task['seller_name'] ?? 'Seller'} · ${task['seller_phone'] ?? ''}' : 'Buyer: ${task['return_buyer_name'] ?? 'Buyer'} · ${task['return_buyer_phone'] ?? ''}'),
          Text(_isReturnDelivery(task) ? 'Seller address: ${_returnSellerAddress(task)}' : 'Pickup address: ${_returnPickupAddress(task)}'),
          if (task['return_reason'] != null) Text('Reason: ${task['return_reason']}'),
        ] else if (_isReturnTransfer(task)) ...[
          Text('Item: ${task['return_product_name'] ?? 'Returned product'}'),
          Text('Hub route: ${task['origin']} → ${task['destination']}'),
          const Text('Deliver this return parcel to the destination hub.'),
        ] else ...[
          Text(task['job_type'] == 'Seller Pickup' ? 'Pickup: ${task['seller_name']}' : '${task['origin']} → ${task['destination']}'),
          if (task['seller_phone'] != null && task['job_type'] == 'Seller Pickup') Text('Seller: ${task['seller_phone']}'),
        ],
        Text('Status: ${task['status'].toString().replaceAll('_', ' ')}'),
        if (_isReturnPickup(task) && task['status'] == 'collected') const Text('Return item to your assigned hub. Hub staff must confirm receipt.'),
        if (_isReturnTransfer(task) && task['status'] == 'arrived') const Text('Waiting for the destination hub to confirm receipt.'),
        if (task['job_type'] == 'Seller Pickup' && task['pickup_address'] != null)
          Text(task['pickup_address'].toString()),
        if (next(task) != null) FilledButton(onPressed: loading ? null : () => update(task, next(task)!), child: Text('Confirm ${next(task)!.replaceAll('_', ' ')}')),
        if (task['status'] == 'collected' && !_isReturnPickup(task)) const Text('Waiting for the seller to confirm collection.'),
        if (task['status'] == 'seller_confirmed' || (task['status'] == 'arrived' && task['job_type'] != 'Seller Pickup')) const Text('Waiting for the receiving logistics hub to accept the parcels.'),
      ]))),
    ])),
  );
}

import 'package:flutter/material.dart';
import '../logistics_api.dart';
import 'login_screen.dart';

class ConnectedLogisticsScreen extends StatefulWidget {
  const ConnectedLogisticsScreen({super.key});
  @override
  State<ConnectedLogisticsScreen> createState() => _ConnectedLogisticsScreenState();
}

class _ConnectedLogisticsScreenState extends State<ConnectedLogisticsScreen> {
  Map<String, dynamic> data = {};
  List<dynamic> applications = [];
  String? error;
  bool busy = false;

  @override
  void initState() { super.initState(); refresh(); }

  Future<void> refresh() async {
    if (busy) return;
    setState(() => busy = true);
    try {
      final results = await Future.wait([LogisticsApi.request('GET', '/logistics/workspace'), LogisticsApi.request('GET', '/logistics/riders')]);
      if (mounted) setState(() { data = results[0]; applications = results[1]['applications'] as List? ?? []; error = null; });
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => busy = false); }
  }

  Future<void> mutate(String method, String path, Map<String, dynamic> body) async {
    if (busy) return;
    setState(() => busy = true);
    try {
      await LogisticsApi.request(method, path, body);
      if (mounted) setState(() => busy = false);
      await refresh();
    } catch (e) { if (mounted) setState(() { error = e.toString(); busy = false; }); }
  }

  Future<void> assign(Map<String, dynamic> shipment, String job) async {
    final riders = (data['riders'] as List? ?? []).where((r) => r['job_type'] == job && r['management_status'] == 'Available' && (r['is_active'] == true || r['is_active'] == 1)).toList();
    dynamic riderId;
    dynamic hubId;
    final accepted = await showDialog<bool>(context: context, builder: (context) => StatefulBuilder(builder: (context, update) => AlertDialog(
      title: Text('Assign $job'), content: Column(mainAxisSize: MainAxisSize.min, children: [
        DropdownButtonFormField<dynamic>(items: [for (final rider in riders) DropdownMenuItem(value: rider['id'], child: Text('${rider['name']} · ${rider['vehicle_type']}'))], onChanged: (value) => update(() => riderId = value), decoration: const InputDecoration(labelText: 'Available rider')),
        if (riders.isEmpty) const Text('No available rider has this job.'),
        if (job == 'Long-distance Handover') DropdownButtonFormField<dynamic>(items: [for (final hub in data['hubs'] as List? ?? []) DropdownMenuItem(value: hub['id'], child: Text(hub['name'].toString()))], onChanged: (value) => update(() => hubId = value), decoration: const InputDecoration(labelText: 'Destination hub')),
      ]), actions: [TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')), FilledButton(onPressed: riderId == null || (job == 'Long-distance Handover' && hubId == null) ? null : () => Navigator.pop(context, true), child: const Text('Assign'))],
    )));
    if (accepted != true) return;
    if (job == 'Delivery') {
      await mutate('POST', '/logistics/delivery-assignment', {'rider_id': riderId, 'shipment_ids': [shipment['id']]});
    } else {
      await mutate('POST', '/logistics/tasks', {'rider_id': riderId, 'shipment_id': shipment['id'], 'job_type': job, 'destination_provider_id': hubId});
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(data['provider']?['name']?.toString() ?? 'Lumear Logistics'), actions: [IconButton(onPressed: busy ? null : refresh, icon: const Icon(Icons.refresh)), IconButton(onPressed: () async {
      try { await LogisticsApi.logout(); } catch (_) {}
      if (context.mounted) Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false);
    }, icon: const Icon(Icons.logout))]),
    body: RefreshIndicator(onRefresh: refresh, child: ListView(padding: const EdgeInsets.all(18), children: [
      if (busy) const LinearProgressIndicator(),
      if (error != null) Text(error!, style: const TextStyle(color: Colors.red)),
      Text('Rider applications', style: Theme.of(context).textTheme.titleLarge),
      for (final application in applications.where((a) => a['status'] == 'Pending')) Card(child: ListTile(title: Text('${application['firstName']} ${application['lastName']}'), subtitle: Text('${application['vehicle']} · ${application['jobType']}'), trailing: TextButton(onPressed: busy ? null : () => mutate('PATCH', '/logistics/applications/${int.parse(application['id'].toString().split('-').last)}', {'status': 'Approved'}), child: const Text('Approve')))),
      Text('Parcels', style: Theme.of(context).textTheme.titleLarge),
      for (final shipment in data['shipments'] as List? ?? []) Card(child: Padding(padding: const EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('${shipment['tracking_code']} · ${shipment['seller_name']}'), Text(shipment['status'].toString()),
        Wrap(spacing: 8, children: [
          if (shipment['status'] == 'unassigned') TextButton(onPressed: busy ? null : () => assign(Map<String, dynamic>.from(shipment), 'Seller Pickup'), child: const Text('Assign van pickup')),
          if (['unassigned', 'picked_up', 'in_transit'].contains(shipment['status']) && shipment['rider_id'] == null && shipment['sorting_stage'] == 'sorted' && shipment['waybill_verified_at'] != null) TextButton(onPressed: busy ? null : () => assign(Map<String, dynamic>.from(shipment), 'Long-distance Handover'), child: const Text('Assign truck transfer')),
          if (shipment['sorting_stage'] == 'sorted') TextButton(onPressed: busy ? null : () => assign(Map<String, dynamic>.from(shipment), 'Delivery'), child: const Text('Assign customer delivery')),
          if (shipment['status'] == 'assigned') TextButton(onPressed: busy ? null : () => mutate('POST', '/logistics/deliveries/${shipment['id']}/dispatch', {}), child: const Text('Dispatch')),
        ]),
      ]))),
      Text('Pickup and hub tasks', style: Theme.of(context).textTheme.titleLarge),
      for (final task in data['tasks'] as List? ?? []) Card(child: ListTile(title: Text('${task['tracking_code']} · ${task['job_type']}'), subtitle: Text('${task['origin']} → ${task['destination']}\n${task['status']}'), trailing: task['destination_provider_id'] == data['provider']?['id'] && (task['status'] == 'seller_confirmed' || (task['job_type'] == 'Long-distance Handover' && task['status'] == 'arrived')) ? TextButton(onPressed: busy ? null : () => mutate('PATCH', '/logistics/tasks/${task['id']}', {'status': 'completed'}), child: const Text('Accept at hub')) : null)),
      Text('Riders', style: Theme.of(context).textTheme.titleLarge),
      for (final rider in data['riders'] as List? ?? []) ListTile(title: Text(rider['name'].toString()), subtitle: Text('${rider['vehicle_type']} · ${rider['job_type']} · ${rider['management_status']}')),
    ])),
  );
}

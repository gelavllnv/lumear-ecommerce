import 'package:flutter/material.dart';
import '../services/api_service.dart';

class SellerPickupTasksScreen extends StatefulWidget {
  const SellerPickupTasksScreen({super.key});
  @override
  State<SellerPickupTasksScreen> createState() => _SellerPickupTasksScreenState();
}

class _SellerPickupTasksScreenState extends State<SellerPickupTasksScreen> {
  List<dynamic> tasks = [];
  String? error;
  bool busy = false;
  @override
  void initState() { super.initState(); refresh(); }
  Future<void> refresh() async {
    setState(() => busy = true);
    try {
      final result = await ApiService.get('/seller/tasks');
      if (mounted) setState(() { tasks = result['tasks'] as List; error = null; });
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => busy = false); }
  }
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Confirm rider pickups'), actions: [IconButton(onPressed: busy ? null : refresh, icon: const Icon(Icons.refresh))]),
    body: ListView(padding: const EdgeInsets.all(20), children: [
      if (busy) const LinearProgressIndicator(),
      if (error != null) Text(error!, style: const TextStyle(color: Colors.red)),
      if (!busy && tasks.isEmpty) const Text('No pickup assignments yet.'),
      for (final task in tasks) Card(child: ListTile(title: Text('${task['tracking_code']} · ${task['job_type']}'), subtitle: Text(task['status'].toString().replaceAll('_', ' ')), trailing: task['job_type'] == 'Seller Pickup' && task['status'] == 'collected' ? TextButton(onPressed: busy ? null : () async {
        setState(() => busy = true);
        try { await ApiService.patch('/seller/tasks/${task['id']}', body: {'status': 'seller_confirmed'}); await refresh(); }
        catch (e) { if (mounted) setState(() { error = e.toString(); busy = false; }); }
      }, child: const Text('Confirm collected')) : null)),
    ]),
  );
}

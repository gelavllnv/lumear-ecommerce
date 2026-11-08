import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../services/buyer_data.dart';

class BuyerWalletScreen extends StatefulWidget {
  const BuyerWalletScreen({super.key});
  @override
  State<BuyerWalletScreen> createState() => _BuyerWalletScreenState();
}

class _BuyerWalletScreenState extends State<BuyerWalletScreen> {
  Map<String, dynamic>? wallet;
  List<dynamic> orders = [];
  String? error;
  bool loading = true;
  String money(num minor) => '₱${(minor / 100).toStringAsFixed(2)}';
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    if (!BuyerData.instance.connected) {
      setState(() {
        wallet = {'balance_minor': 0, 'transactions': [], 'returns': []};
        loading = false;
      });
      return;
    }
    try {
      final results = await Future.wait([
        ApiService.get('/buyer/wallet'),
        ApiService.get('/orders'),
      ]);
      if (!mounted) return;
      setState(() {
        wallet = Map<String, dynamic>.from(results[0]);
        orders = List<dynamic>.from(results[1]);
      });
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('My e-wallet'),
      actions: [
        IconButton(
          tooltip: 'Refresh wallet',
          onPressed: loading ? null : load,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : error != null
        ? Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(error!),
                TextButton(onPressed: load, child: const Text('Retry')),
              ],
            ),
          )
        : ListView(
            padding: const EdgeInsets.all(24),
            children: [
              const Text('Refund balance'),
              Text(
                money(wallet!['balance_minor'] as num),
                style: Theme.of(context).textTheme.headlineLarge,
              ),
              const SizedBox(height: 12),
              Text(
                BuyerData.instance.connected
                    ? 'Confirmed refunds are credited here after return review and refund processing. Purchases and withdrawals are not available yet.'
                    : 'Local preview: no real funds. Refunds and returns require the shared service.',
              ),
              const SizedBox(height: 24),
              Text(
                'Transaction history',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              if ((wallet!['transactions'] as List).isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 16),
                  child: Text('No refunds credited yet.'),
                ),
              for (final t in wallet!['transactions'])
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.account_balance_wallet_outlined),
                  title: Text('Refund · ${t['reference']}'),
                  subtitle: Text(
                    DateTime.fromMillisecondsSinceEpoch(
                      (t['created_at'] as num).toInt() * 1000,
                    ).toLocal().toString().split('.').first,
                  ),
                  trailing: Text('+${money(t['amount_minor'] as num)}'),
                ),
              const SizedBox(height: 24),
              Text(
                'Returns and refunds',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              if ((wallet!['returns'] as List).isEmpty)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 16),
                  child: Text('No return requests yet.'),
                ),
              for (final r in wallet!['returns'])
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(r['product'] as String),
                  subtitle: Text(
                    '${r['reference']} · ${r['status']}${(r['response'] as String? ?? '').isEmpty ? '' : '\nSeller: ${r['response']}'}',
                  ),
                  trailing: Text(money(r['amount_minor'] as num)),
                ),
              const SizedBox(height: 24),
              Text(
                'Request a return',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const Text(
                'Select an item from a delivered, paid order. Each request covers all units of that item. Shipping is excluded.',
              ),
              for (final order in orders.where(
                (o) =>
                    o['paid'] == true &&
                    ['delivered', 'completed'].contains(o['raw_status']),
              ))
                for (final item in (order['items'] as List).where(
                  (item) => item['return_status'] == null,
                ))
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: Text(item['name'] as String),
                    subtitle: Text(order['reference'] as String),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () async {
                      final saved = await Navigator.push<bool>(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ReturnRequestScreen(
                            item: Map<String, dynamic>.from(item),
                          ),
                        ),
                      );
                      if (saved == true && mounted) await load();
                    },
                  ),
            ],
          ),
  );
}

class ReturnRequestScreen extends StatefulWidget {
  const ReturnRequestScreen({super.key, required this.item});
  final Map<String, dynamic> item;
  @override
  State<ReturnRequestScreen> createState() => _ReturnFormState();
}

class _ReturnFormState extends State<ReturnRequestScreen> {
  final form = GlobalKey<FormState>();
  final reason = TextEditingController(), description = TextEditingController();
  bool busy = false;
  String? error;
  @override
  void dispose() {
    reason.dispose();
    description.dispose();
    super.dispose();
  }

  Future<void> submit() async {
    if (busy || !form.currentState!.validate()) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ApiService.post(
        '/buyer/returns',
        body: {
          'order_item_id': widget.item['id'].toString(),
          'reason': reason.text.trim(),
          'description': description.text.trim(),
        },
      );
      if (!mounted) return;
      Navigator.pop(context, true);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Request return / refund')),
    body: SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Form(
        key: form,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              widget.item['name'] as String,
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 12),
            const Text(
              'Refund destination: Lumear e-wallet. Your request must be reviewed and processed before money is credited.',
            ),
            TextFormField(
              controller: reason,
              enabled: !busy,
              maxLength: 100,
              decoration: const InputDecoration(labelText: 'Reason'),
              validator: (v) => (v?.trim().length ?? 0) < 3
                  ? 'Enter a reason (at least 3 characters).'
                  : null,
            ),
            TextFormField(
              controller: description,
              enabled: !busy,
              maxLength: 3000,
              minLines: 3,
              maxLines: 6,
              decoration: const InputDecoration(
                labelText: 'Describe the issue',
              ),
              validator: (v) => (v?.trim().length ?? 0) < 10
                  ? 'Describe the issue (at least 10 characters).'
                  : null,
            ),
            if (error != null) Text(error!),
            if (busy) const LinearProgressIndicator(),
            FilledButton(
              onPressed: busy ? null : submit,
              child: const Text('Submit return request'),
            ),
          ],
        ),
      ),
    ),
  );
}

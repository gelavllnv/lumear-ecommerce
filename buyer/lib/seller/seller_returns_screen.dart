import 'package:flutter/material.dart';

import '../services/api_service.dart';

class SellerReturnsScreen extends StatefulWidget {
  const SellerReturnsScreen({super.key, required this.connected});
  final bool connected;
  @override
  State<SellerReturnsScreen> createState() => _SellerReturnsScreenState();
}

class _SellerReturnsScreenState extends State<SellerReturnsScreen> {
  List<dynamic> requests = [];
  bool loading = true;
  String? error;
  String money(num value) => '₱${(value / 100).toStringAsFixed(2)}';
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
    try {
      final result = widget.connected
          ? await ApiService.get('/seller/returns')
          : <dynamic>[];
      if (mounted) setState(() => requests = List<dynamic>.from(result));
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> review(Map<String, dynamic> request, String action) async {
    final saved = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (_) => _ReviewDialog(request: request, action: action),
    );
    if (mounted && saved == true) await load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Returns and refunds'),
      actions: [
        IconButton(
          tooltip: 'Refresh returns',
          onPressed: loading ? null : load,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : ListView(
            padding: const EdgeInsets.all(20),
            children: [
              const Text(
                'Review the buyer’s issue, arrange the return, then refund to their Lumear e-wallet after receiving the returned items.',
              ),
              if (!widget.connected)
                const Text(
                  'Local preview: real returns require the shared service.',
                ),
              if (error != null) ...[
                Text(error!),
                TextButton(onPressed: load, child: const Text('Retry')),
              ],
              if (error == null && requests.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(24),
                  child: Text('No return requests yet.'),
                ),
              for (final r in requests)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${r['reference']} · ${r['status']}',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        Text('${r['product']} · ${r['buyer']}'),
                        Text('${r['reason']}\n${r['description']}'),
                        const SizedBox(height: 8),
                        Text(
                          'Refund to buyer wallet: ${money(r['amount_minor'] as num)}',
                        ),
                        if ((r['response'] as String).isNotEmpty)
                          Text('Your response: ${r['response']}'),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 8,
                          children: [
                            if (r['status'] == 'requested') ...[
                              OutlinedButton(
                                onPressed: () => review(
                                  Map<String, dynamic>.from(r),
                                  'reject',
                                ),
                                child: const Text('Reject'),
                              ),
                              FilledButton(
                                onPressed: () => review(
                                  Map<String, dynamic>.from(r),
                                  'approve',
                                ),
                                child: const Text('Approve return'),
                              ),
                            ],
                            if (r['status'] == 'approved')
                              FilledButton(
                                onPressed: () => review(
                                  Map<String, dynamic>.from(r),
                                  'refund',
                                ),
                                child: const Text('Refund to buyer wallet'),
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
  );
}

class _ReviewDialog extends StatefulWidget {
  const _ReviewDialog({required this.request, required this.action});
  final Map<String, dynamic> request;
  final String action;
  @override
  State<_ReviewDialog> createState() => _ReviewDialogState();
}

class _ReviewDialogState extends State<_ReviewDialog> {
  final response = TextEditingController();
  final form = GlobalKey<FormState>();
  bool busy = false, received = false;
  String? error;
  bool get refund => widget.action == 'refund';
  @override
  void dispose() {
    response.dispose();
    super.dispose();
  }

  Future<void> save() async {
    if (busy || !form.currentState!.validate() || refund && !received) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await ApiService.post(
        '/seller/returns/${widget.request['id']}/${widget.action}',
        body: {
          if (refund) 'items_received': true,
          if (!refund) 'response': response.text.trim(),
        },
      );
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !busy,
    child: AlertDialog(
      title: Text(
        refund
            ? 'Confirm wallet refund'
            : widget.action == 'approve'
            ? 'Approve this return?'
            : 'Reject this return?',
      ),
      content: SingleChildScrollView(
        child: Form(
          key: form,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (refund) ...[
                Text(
                  'Credit ₱${((widget.request['amount_minor'] as num) / 100).toStringAsFixed(2)} to the buyer’s Lumear e-wallet. Seller and platform allocations will be reversed. This action cannot be undone.',
                ),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('I have received the returned items'),
                  value: received,
                  onChanged: busy
                      ? null
                      : (v) => setState(() => received = v == true),
                ),
              ] else
                TextFormField(
                  controller: response,
                  enabled: !busy,
                  minLines: 2,
                  maxLines: 5,
                  maxLength: 2000,
                  decoration: InputDecoration(
                    labelText: widget.action == 'approve'
                        ? 'Return instructions for buyer'
                        : 'Reason for rejection',
                  ),
                  validator: (v) => (v?.trim().length ?? 0) < 3
                      ? 'Enter a response (at least 3 characters).'
                      : null,
                ),
              if (error != null) Text(error!),
              if (busy) const LinearProgressIndicator(),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: busy ? null : () => Navigator.pop(context, false),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: busy || refund && !received ? null : save,
          child: Text(
            refund
                ? 'Confirm refund'
                : widget.action == 'approve'
                ? 'Approve'
                : 'Reject return',
          ),
        ),
      ],
    ),
  );
}

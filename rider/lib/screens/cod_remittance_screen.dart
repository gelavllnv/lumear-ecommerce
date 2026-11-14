import 'package:flutter/material.dart';

import '../services/rider_api_service.dart';

class CodRemittanceScreen extends StatefulWidget {
  const CodRemittanceScreen({super.key});

  @override
  State<CodRemittanceScreen> createState() => _CodRemittanceScreenState();
}

class _CodRemittanceScreenState extends State<CodRemittanceScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color muted = Color(0xFF8B6F63);

  Map<String, dynamic>? _data;
  bool _isLoading = true;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _isLoading = true);
    try {
      await RiderApiService.syncCodRemittances();
      final data = await RiderApiService.codRemittances();
      if (mounted) setState(() => _data = data);
    } catch (error) {
      if (mounted) _message(error.toString());
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  int _amount(String key) {
    final value = _data?[key];
    return value is num ? value.toInt() : int.tryParse('$value') ?? 0;
  }

  String _money(int minor) => '₱${(minor / 100).toStringAsFixed(2)}';

  Future<void> _submit() async {
    final amountMinor = _amount('available_to_submit_minor');
    if (amountMinor <= 0 || _isSubmitting) return;
    final providerName = _data?['provider_name']?.toString() ?? 'Logistics Hub';

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Are you sure you handed over the COD?'),
        content: Text(
          'Confirm that you physically gave ${_money(amountMinor)} cash to $providerName. Submitting will create a reference and notify the hub to confirm receipt. Continue only after the cash handoff.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: darkMaroon),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Yes, submit handoff'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _isSubmitting = true);
    try {
      final result = await RiderApiService.submitCodRemittance(
        amountMinor: amountMinor,
      );
      final reference = result['transaction_reference']?.toString() ?? '';
      _message('Submitted to $providerName. Reference: $reference');
      await _load();
    } catch (error) {
      _message(error.toString());
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  void _message(String text) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }

  @override
  Widget build(BuildContext context) {
    final balance = _data?['balance'];
    final outstanding = balance is Map && balance['outstanding_minor'] is num
        ? (balance['outstanding_minor'] as num).toInt()
        : 0;
    final pending = _amount('pending_minor');
    final available = _amount('available_to_submit_minor');
    final history =
        (_data?['history'] as List?)?.whereType<Map>().toList() ?? [];

    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        title: const Text(
          'COD Remittance',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        actions: [
          IconButton(onPressed: _load, icon: const Icon(Icons.refresh)),
        ],
      ),
      body: _isLoading && _data == null
          ? const Center(child: CircularProgressIndicator(color: darkMaroon))
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  _balanceCard(outstanding, pending),
                  const SizedBox(height: 16),
                  if (available > 0) _submissionCard(available),
                  if (pending > 0) _pendingNotice(pending),
                  if (available == 0 && pending == 0) _emptyBalanceNotice(),
                  const SizedBox(height: 24),
                  const Text(
                    'Remittance History',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                      color: darkMaroon,
                    ),
                  ),
                  const SizedBox(height: 10),
                  if (history.isEmpty)
                    _emptyHistory()
                  else
                    ...history.map((item) => _historyCard(item)),
                ],
              ),
            ),
    );
  }

  Widget _balanceCard(int outstanding, int pending) {
    final providerName = _data?['provider_name']?.toString() ?? 'Logistics Hub';
    return Card(
      color: darkMaroon,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'OUTSTANDING COD',
              style: TextStyle(
                color: Colors.white70,
                fontWeight: FontWeight.bold,
                letterSpacing: 1,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              _money(outstanding),
              style: const TextStyle(
                color: Colors.white,
                fontSize: 32,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 12),
            Text(
              'Hub: $providerName',
              style: const TextStyle(color: Colors.white70),
            ),
            const SizedBox(height: 4),
            Text(
              'Waiting for hub confirmation: ${_money(pending)}',
              style: const TextStyle(color: Colors.white70),
            ),
          ],
        ),
      ),
    );
  }

  Widget _submissionCard(int amountMinor) {
    final providerName = _data?['provider_name']?.toString() ?? 'Logistics Hub';
    return Card(
      color: Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Submit collected COD',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w900,
                color: darkMaroon,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              'Hand the full ${_money(amountMinor)} cash balance to $providerName first. This button records your handoff; the hub must confirm they received the cash.',
              style: const TextStyle(color: muted, height: 1.35),
            ),
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: _isSubmitting ? null : _submit,
                icon: _isSubmitting
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.send_rounded),
                label: Text(
                  _isSubmitting
                      ? 'Submitting handoff...'
                      : 'Submit cash to $providerName',
                ),
                style: FilledButton.styleFrom(
                  backgroundColor: maroon,
                  padding: const EdgeInsets.symmetric(vertical: 15),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _pendingNotice(int pending) => Card(
    color: const Color(0xFFFFF3D6),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Text(
        'May cash handoff ka nang ${_money(pending)} na pending. Hintayin munang kumpirmahin o i-reject ng ${_data?['provider_name'] ?? 'Logistics Hub'} bago magsumite ulit.',
        style: const TextStyle(color: darkMaroon, fontWeight: FontWeight.w700),
      ),
    ),
  );

  Widget _emptyBalanceNotice() => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Text(
        'Wala kang outstanding COD na puwedeng i-remit ngayon.',
        style: TextStyle(color: muted, fontWeight: FontWeight.w700),
      ),
    ),
  );

  Widget _emptyHistory() => const Card(
    child: Padding(
      padding: EdgeInsets.all(18),
      child: Text('Wala pang na-submit na remittance.'),
    ),
  );

  Widget _historyCard(Map item) {
    final amount = item['amount_minor'] is num
        ? (item['amount_minor'] as num).toInt()
        : 0;
    final status = item['status']?.toString() ?? 'pending_verification';
    final methodLabel = item['method_label']?.toString() ?? 'Cash Handoff';
    final statusLabel = switch (status) {
      'verified' => 'RECEIVED BY HUB',
      'rejected' => 'REJECTED',
      _ => 'AWAITING HUB CONFIRMATION',
    };
    final submittedAt = item['submitted_at']?.toString() ?? '';
    final color = switch (status) {
      'verified' => const Color(0xFF2E7D32),
      'rejected' => const Color(0xFFB3261E),
      _ => const Color(0xFFC77700),
    };
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    _money(amount),
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                      color: darkMaroon,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    statusLabel,
                    style: TextStyle(
                      color: color,
                      fontSize: 11,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 7),
            Text(
              item['remittance_number']?.toString() ?? '',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            Text(
              '$methodLabel · ${item['payment_provider'] ?? ''}',
              style: const TextStyle(color: muted),
            ),
            Text(
              'Reference: ${item['transaction_reference'] ?? ''}',
              style: const TextStyle(color: muted),
            ),
            Text(
              submittedAt.replaceFirst('T', ' ').split('.').first,
              style: const TextStyle(color: muted, fontSize: 12),
            ),
            if (status == 'rejected' && item['rejection_reason'] != null)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                  'Reason: ${item['rejection_reason']}',
                  style: const TextStyle(color: Color(0xFFB3261E)),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:flutter_application_1/services/rider_api_service.dart';

class EarningsScreen extends StatefulWidget {
  const EarningsScreen({super.key});

  @override
  State<EarningsScreen> createState() => _EarningsScreenState();
}

class _EarningsScreenState extends State<EarningsScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color border = Color(0xFFEDE1D8);
  static const Color muted = Color(0xFF8B6F63);

  Map<String, dynamic> _payroll = <String, dynamic>{};
  bool _loading = true;
  String? _error;

  List<Map<String, dynamic>> get _records {
    final rows = _payroll['payrolls'];
    if (rows is! List) return const [];
    return rows.whereType<Map<String, dynamic>>().toList(growable: false);
  }

  Map<String, dynamic> get _currentPeriod {
    final value = _payroll['current_period'];
    return value is Map<String, dynamic> ? value : <String, dynamic>{};
  }

  @override
  void initState() {
    super.initState();
    _refreshPayroll();
  }

  Future<void> _refreshPayroll() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }

    try {
      await RiderApiService.syncPayrollDeliveries();
      final result = await RiderApiService.payroll();
      if (!mounted) return;
      setState(() {
        _payroll = result;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.toString();
        _loading = false;
      });
    }
  }

  int _minor(dynamic value) {
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? 0;
  }

  String _money(dynamic minor) =>
      '₱${(_minor(minor) / 100).toStringAsFixed(2)}';

  String _date(dynamic value) {
    final parsed = DateTime.tryParse(value?.toString() ?? '');
    if (parsed == null) return '—';
    return '${parsed.day.toString().padLeft(2, '0')} '
        '${_month(parsed.month)} ${parsed.year}';
  }

  String _month(int month) => const [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ][month - 1];

  String _statusLabel(String? status) => switch (status) {
    'paid' => 'Paid',
    'approved' => 'Approved · not yet paid',
    'draft' => 'Draft · awaiting approval',
    _ => status ?? 'Unknown',
  };

  Color _statusColor(String? status) => switch (status) {
    'paid' => Colors.green.shade700,
    'approved' => Colors.blueGrey.shade700,
    'draft' => Colors.orange.shade800,
    _ => muted,
  };

  @override
  Widget build(BuildContext context) {
    final paidMinor = _records
        .where((record) => record['status'] == 'paid')
        .fold<int>(0, (sum, record) => sum + _minor(record['gross_pay_minor']));

    return Scaffold(
      backgroundColor: cream,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _refreshPayroll,
          color: darkMaroon,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 125),
            children: [
              Row(
                children: [
                  const Expanded(
                    child: Text(
                      'My Payroll',
                      style: TextStyle(
                        color: darkMaroon,
                        fontSize: 28,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: _loading ? null : _refreshPayroll,
                    tooltip: 'Refresh payroll',
                    icon: const Icon(Icons.refresh_rounded, color: darkMaroon),
                  ),
                ],
              ),
              Text(
                (_payroll['provider_name'] ?? 'Logistics').toString(),
                style: const TextStyle(color: muted, fontSize: 13),
              ),
              const SizedBox(height: 18),
              if (_loading) ...[
                const LinearProgressIndicator(color: darkMaroon),
                const SizedBox(height: 16),
              ],
              if (_error != null) ...[
                _messageCard(
                  icon: Icons.cloud_off_outlined,
                  title: 'Could not load payroll',
                  message: _error!,
                  action: TextButton(
                    onPressed: _refreshPayroll,
                    child: const Text('Try again'),
                  ),
                ),
              ] else if (!_loading) ...[
                _paidBalanceCard(paidMinor),
                const SizedBox(height: 14),
                _currentPeriodCard(_currentPeriod),
                const SizedBox(height: 22),
                const Text(
                  'Payroll History',
                  style: TextStyle(
                    color: darkMaroon,
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 10),
                if (_records.isEmpty)
                  _messageCard(
                    icon: Icons.receipt_long_outlined,
                    title: 'No payroll records yet',
                    message:
                        'Your Logistics hub has not generated a payroll record for you yet. The current period preview above is an estimate, not a payment.',
                  )
                else
                  ..._records.map(_payrollCard),
                const SizedBox(height: 12),
                _messageCard(
                  icon: Icons.info_outline_rounded,
                  title: 'Payroll status',
                  message:
                      'Draft and approved payrolls are not proof of payment. A payroll counts as paid only when Logistics marks it Paid; its payment reference will appear here.',
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _paidBalanceCard(int paidMinor) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [darkMaroon, maroon],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: darkMaroon.withValues(alpha: 0.2),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'PAYROLL MARKED PAID',
            style: TextStyle(
              color: Colors.white70,
              fontSize: 12,
              letterSpacing: 1,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            _money(paidMinor),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 34,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Total of payroll records Logistics marked Paid',
            style: TextStyle(color: cream, fontSize: 12),
          ),
        ],
      ),
    );
  }

  Widget _currentPeriodCard(Map<String, dynamic> period) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'CURRENT PERIOD PREVIEW',
            style: TextStyle(
              color: maroon,
              fontSize: 11,
              letterSpacing: 0.8,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            '${_date(period['period_start'])} – ${_date(period['period_end'])}',
            style: const TextStyle(
              color: darkMaroon,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 14),
          _amountRow('Verified paid days', '${period['paid_days'] ?? 0}'),
          _amountRow(
            'Successful deliveries',
            '${period['successful_deliveries'] ?? 0}',
          ),
          _amountRow(
            'Base salary',
            _money(period['base_salary_minor']),
          ),
          _amountRow(
            'Delivery commission',
            _money(period['commission_minor']),
          ),
          _amountRow(
            'Shipping fee per successful delivery',
            _money(period['shipping_fee_minor']),
          ),
          _amountRow(
            'Logistics share per delivery',
            _money(period['logistics_share_minor']),
          ),
          _amountRow(
            'Rider share per delivery',
            _money(period['delivery_rate_minor']),
          ),
          const Divider(height: 22),
          _amountRow(
            'Estimated gross pay',
            _money(period['gross_pay_minor']),
            bold: true,
          ),
          const SizedBox(height: 8),
          const Text(
            'This preview uses verified attendance and delivery records. It is not an approved or paid payroll.',
            style: TextStyle(color: muted, fontSize: 11, height: 1.4),
          ),
        ],
      ),
    );
  }

  Widget _payrollCard(Map<String, dynamic> record) {
    final status = record['status']?.toString();
    final statusColor = _statusColor(status);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Payroll Period',
                  style: TextStyle(
                    color: darkMaroon,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  _statusLabel(status),
                  style: TextStyle(
                    color: statusColor,
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 5),
          Text(
            '${_date(record['period_start'])} – ${_date(record['period_end'])}',
            style: const TextStyle(color: muted, fontSize: 12),
          ),
          const SizedBox(height: 12),
          _amountRow('Paid days', '${record['paid_days'] ?? 0}'),
          _amountRow(
            'Successful deliveries',
            '${record['successful_deliveries'] ?? 0}',
          ),
          _amountRow('Base salary', _money(record['base_salary_minor'])),
          _amountRow('Commission', _money(record['commission_minor'])),
          const Divider(height: 20),
          _amountRow(
            'Gross pay',
            _money(record['gross_pay_minor']),
            bold: true,
          ),
          if (record['paid_at'] != null) ...[
            const SizedBox(height: 7),
            Text(
              'Paid ${_date(record['paid_at'])}',
              style: const TextStyle(color: muted, fontSize: 11),
            ),
          ],
          if ((record['payment_reference']?.toString() ?? '').isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              'Reference: ${record['payment_reference']}',
              style: const TextStyle(color: muted, fontSize: 11),
            ),
          ],
          if ((record['remarks']?.toString() ?? '').isNotEmpty) ...[
            const SizedBox(height: 7),
            Text(
              record['remarks'].toString(),
              style: const TextStyle(color: muted, fontSize: 11, height: 1.4),
            ),
          ],
        ],
      ),
    );
  }

  Widget _amountRow(String label, String value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                color: bold ? darkMaroon : muted,
                fontSize: 12,
                fontWeight: bold ? FontWeight.w800 : FontWeight.w500,
              ),
            ),
          ),
          Text(
            value,
            style: TextStyle(
              color: darkMaroon,
              fontSize: 12,
              fontWeight: bold ? FontWeight.w800 : FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _messageCard({
    required IconData icon,
    required String title,
    required String message,
    Widget? action,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: maroon, size: 21),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: darkMaroon,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  message,
                  style: const TextStyle(
                    color: muted,
                    fontSize: 11.5,
                    height: 1.4,
                  ),
                ),
                ?action,
              ],
            ),
          ),
        ],
      ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

import '../../models/van_pickup_request.dart';
import 'van_earnings_screen.dart';

/// Delivery report for the van rider.
///
/// Pass the same `pickups` list used by the dashboard so the report can
/// include the rider's wallet earnings (credited by Lumear Logistics after
/// each successful hub handover):
///
///   VanRiderDeliveryReportsScreen(pickups: vanPickupDemoData)
class VanRiderDeliveryReportsScreen extends StatefulWidget {
  final List<VanPickupRequest> pickups;

  const VanRiderDeliveryReportsScreen({super.key, this.pickups = const []});

  @override
  State<VanRiderDeliveryReportsScreen> createState() =>
      _VanRiderDeliveryReportsScreenState();
}

class _VanRiderDeliveryReportsScreenState
    extends State<VanRiderDeliveryReportsScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color cardBorder = Color(0xFFEDE1D8);
  static const Color pendingAmber = Color(0xFF815C13);

  static const List<String> _rangeOptions = [
    'Today',
    'Last 7 days',
    'Last 30 days',
    'This month',
    'Custom',
  ];

  static const List<String> _statusFilters = [
    'All',
    'Delivered',
    'Failed',
    'Returned',
    'Pending',
  ];

  String _selectedRange = 'Last 7 days';
  String _statusFilter = 'All';

  DateTime _startDate = DateTime.now().subtract(const Duration(days: 6));
  DateTime _endDate = DateTime.now();

  // Sample records for UI testing. Replace these with records from your API/database.
  final List<_DeliveryRecord> _records = [
    _DeliveryRecord(
      parcelId: 'LMR-1001',
      product: 'Household Items',
      recipient: 'Juan Dela Cruz',
      address: 'Los Baños, Laguna',
      deliveredAt: DateTime.now().subtract(const Duration(days: 1, hours: 2)),
      status: 'Delivered',
    ),
    _DeliveryRecord(
      parcelId: 'LMR-1002',
      product: 'Clothing',
      recipient: 'Maria Santos',
      address: 'Calamba, Laguna',
      deliveredAt: DateTime.now().subtract(const Duration(days: 2, hours: 1)),
      status: 'Delivered',
    ),
    _DeliveryRecord(
      parcelId: 'LMR-1003',
      product: 'Electronics',
      recipient: 'Pedro Reyes',
      address: 'Bay, Laguna',
      deliveredAt: DateTime.now().subtract(const Duration(days: 3)),
      status: 'Failed',
    ),
    _DeliveryRecord(
      parcelId: 'LMR-1004',
      product: 'Personal Care',
      recipient: 'Ana Garcia',
      address: 'Victoria, Laguna',
      deliveredAt: DateTime.now().subtract(const Duration(days: 4)),
      status: 'Returned',
    ),
    _DeliveryRecord(
      parcelId: 'LMR-1005',
      product: 'Books',
      recipient: 'Carlo Mendoza',
      address: 'Los Baños, Laguna',
      deliveredAt: DateTime.now(),
      status: 'Pending',
    ),
  ];

  // ---------------------------------------------------------------------------
  // DATA
  // ---------------------------------------------------------------------------

  DateTime get _rangeStart =>
      DateTime(_startDate.year, _startDate.month, _startDate.day);

  DateTime get _rangeEndExclusive =>
      DateTime(_endDate.year, _endDate.month, _endDate.day + 1);

  bool _inRange(DateTime date) =>
      !date.isBefore(_rangeStart) && date.isBefore(_rangeEndExclusive);

  // All delivery records inside the selected dates (used by summary + PDF).
  List<_DeliveryRecord> get _recordsInRange {
    return _records.where((r) => _inRange(r.deliveredAt)).toList()
      ..sort((a, b) => b.deliveredAt.compareTo(a.deliveredAt));
  }

  // Records shown in the on-screen list (also respects the status chips).
  List<_DeliveryRecord> get _visibleRecords {
    final records = _recordsInRange;
    if (_statusFilter == 'All') return records;
    return records.where((r) => r.status == _statusFilter).toList();
  }

  int _countStatus(String status) =>
      _recordsInRange.where((r) => r.status == status).length;

  // Success rate is based on finished attempts only (pending is excluded).
  double get _successRate {
    final delivered = _countStatus('Delivered');
    final finished =
        delivered + _countStatus('Failed') + _countStatus('Returned');
    if (finished == 0) return 0;
    return delivered / finished;
  }

  // ----- Earnings (wallet credits from Lumear Logistics) -----

  double _creditFor(VanPickupRequest pickup) =>
      pickup.totalAmount * VanEarningsScreen.creditRate;

  // Pickups handed over to the hub inside the selected dates.
  List<VanPickupRequest> get _creditedInRange {
    final result = widget.pickups.where((p) {
      final handedOverAt = p.handedOverAt;
      return p.status == VanPickupStatus.handedOver &&
          handedOverAt != null &&
          _inRange(handedOverAt.toLocal());
    }).toList();

    result.sort((a, b) => b.handedOverAt!.compareTo(a.handedOverAt!));
    return result;
  }

  // Pickups not handed over yet (estimate, not tied to the date range).
  List<VanPickupRequest> get _incomingPickups => widget.pickups
      .where((p) => p.status != VanPickupStatus.handedOver)
      .toList();

  double get _earnedInRange =>
      _creditedInRange.fold(0.0, (total, p) => total + _creditFor(p));

  double get _incomingTotal =>
      _incomingPickups.fold(0.0, (total, p) => total + _creditFor(p));

  // ---------------------------------------------------------------------------
  // DATE HELPERS
  // ---------------------------------------------------------------------------

  void _applyRange(String option) {
    final now = DateTime.now();

    setState(() {
      _selectedRange = option;

      switch (option) {
        case 'Today':
          _startDate = now;
          _endDate = now;
          break;
        case 'Last 7 days':
          _startDate = now.subtract(const Duration(days: 6));
          _endDate = now;
          break;
        case 'Last 30 days':
          _startDate = now.subtract(const Duration(days: 29));
          _endDate = now;
          break;
        case 'This month':
          _startDate = DateTime(now.year, now.month, 1);
          _endDate = now;
          break;
        default:
          break; // Custom keeps the current dates.
      }
    });
  }

  Future<void> _pickDate({required bool isStart}) async {
    final initial = isStart ? _startDate : _endDate;
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      helpText: isStart ? 'Select start date' : 'Select end date',
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: darkMaroon,
              onPrimary: Colors.white,
              surface: Colors.white,
              onSurface: darkMaroon,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked == null || !mounted) return;

    setState(() {
      _selectedRange = 'Custom';

      if (isStart) {
        _startDate = picked;
        if (_endDate.isBefore(_startDate)) _endDate = picked;
      } else {
        _endDate = picked;
        if (_startDate.isAfter(_endDate)) _startDate = picked;
      }
    });
  }

  String _formatDate(DateTime date) {
    final local = date.toLocal();
    return '${local.day.toString().padLeft(2, '0')}/'
        '${local.month.toString().padLeft(2, '0')}/'
        '${local.year}';
  }

  String _fileDate(DateTime date) =>
      '${date.year}-${date.month.toString().padLeft(2, '0')}-'
      '${date.day.toString().padLeft(2, '0')}';

  // 1234.5 -> 1,234.50
  String _money(double value) {
    final parts = value.toStringAsFixed(2).split('.');
    final whole = parts[0].replaceAllMapped(
      RegExp(r'\B(?=(\d{3})+(?!\d))'),
      (match) => ',',
    );
    return '$whole.${parts[1]}';
  }

  // On screen we can use the peso sign.
  String _peso(double value) => '₱${_money(value)}';

  // The default PDF font has no peso glyph, so the PDF uses "PHP".
  String _pdfPeso(double value) => 'PHP ${_money(value)}';

  // ---------------------------------------------------------------------------
  // PDF
  // ---------------------------------------------------------------------------

  Future<void> _generatePdf() async {
    final records = _recordsInRange;
    final credited = _creditedInRange;

    if (records.isEmpty && credited.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'No delivery or earnings records found for the selected dates.',
          ),
        ),
      );
      return;
    }

    const maroonPdf = PdfColor.fromInt(0xFF62191C);
    const creamPdf = PdfColor.fromInt(0xFFF3E9E1);
    const borderPdf = PdfColor.fromInt(0xFFE3D2C8);

    final delivered = records.where((r) => r.status == 'Delivered').length;
    final failed = records.where((r) => r.status == 'Failed').length;
    final returned = records.where((r) => r.status == 'Returned').length;
    final pending = records.where((r) => r.status == 'Pending').length;
    final finished = delivered + failed + returned;
    final successRate = finished == 0 ? 0 : (delivered / finished) * 100;

    final totalParcelsHandedOver = credited.fold<int>(
      0,
      (total, p) => total + p.parcelCount,
    );

    final pdf = pw.Document();

    pdf.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4,
        margin: const pw.EdgeInsets.all(32),
        footer: (context) => pw.Container(
          alignment: pw.Alignment.centerRight,
          margin: const pw.EdgeInsets.only(top: 10),
          child: pw.Text(
            'Page ${context.pageNumber} of ${context.pagesCount}',
            style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700),
          ),
        ),
        build: (context) => [
          // HEADER
          pw.Container(
            padding: const pw.EdgeInsets.only(bottom: 12),
            decoration: const pw.BoxDecoration(
              border: pw.Border(
                bottom: pw.BorderSide(color: maroonPdf, width: 2),
              ),
            ),
            child: pw.Column(
              crossAxisAlignment: pw.CrossAxisAlignment.start,
              children: [
                pw.Text(
                  'LUMEAR LOGISTICS',
                  style: pw.TextStyle(
                    fontSize: 22,
                    fontWeight: pw.FontWeight.bold,
                    color: maroonPdf,
                  ),
                ),
                pw.SizedBox(height: 4),
                pw.Text(
                  'Van Rider Delivery & Earnings Report',
                  style: pw.TextStyle(
                    fontSize: 16,
                    fontWeight: pw.FontWeight.bold,
                  ),
                ),
                pw.SizedBox(height: 6),
                pw.Text(
                  'Report period: ${_formatDate(_startDate)} - '
                  '${_formatDate(_endDate)}',
                  style: const pw.TextStyle(fontSize: 10),
                ),
                pw.Text(
                  'Generated on: ${_formatDate(DateTime.now())}',
                  style: const pw.TextStyle(fontSize: 10),
                ),
              ],
            ),
          ),

          // 1. DELIVERY SUMMARY
          pw.SizedBox(height: 18),
          _pdfSectionTitle('1. Delivery Summary'),
          pw.SizedBox(height: 8),
          pw.Table(
            border: pw.TableBorder.all(color: borderPdf),
            columnWidths: {
              0: const pw.FlexColumnWidth(3),
              1: const pw.FlexColumnWidth(1),
            },
            children: [
              _pdfSummaryRow(
                'Total parcels',
                '${records.length}',
                header: true,
                fill: creamPdf,
              ),
              _pdfSummaryRow('Delivered', '$delivered'),
              _pdfSummaryRow('Failed', '$failed'),
              _pdfSummaryRow('Returned', '$returned'),
              _pdfSummaryRow('Pending', '$pending'),
              _pdfSummaryRow(
                'Delivery success rate (excluding pending)',
                '${successRate.toStringAsFixed(1)}%',
                header: true,
                fill: creamPdf,
              ),
            ],
          ),

          // 2. EARNINGS SUMMARY
          pw.SizedBox(height: 18),
          _pdfSectionTitle('2. Earnings Summary (Wallet)'),
          pw.SizedBox(height: 4),
          pw.Text(
            'Earnings are credited to the rider wallet by Lumear Logistics '
            'after each successful hub handover. Logistics handles the '
            'actual payout.',
            style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700),
          ),
          pw.SizedBox(height: 8),
          pw.Table(
            border: pw.TableBorder.all(color: borderPdf),
            columnWidths: {
              0: const pw.FlexColumnWidth(3),
              1: const pw.FlexColumnWidth(1),
            },
            children: [
              _pdfSummaryRow(
                'Total earned in this period',
                _pdfPeso(_earnedInRange),
                header: true,
                fill: creamPdf,
              ),
              _pdfSummaryRow('Successful hub handovers', '${credited.length}'),
              _pdfSummaryRow(
                'Parcels handed over to hub',
                '$totalParcelsHandedOver',
              ),
              _pdfSummaryRow(
                'Incoming credits (not yet credited)',
                _pdfPeso(_incomingTotal),
              ),
            ],
          ),

          // 3. EARNINGS BREAKDOWN
          pw.SizedBox(height: 18),
          _pdfSectionTitle('3. Earnings Breakdown'),
          pw.SizedBox(height: 8),
          if (credited.isEmpty)
            pw.Text(
              'No wallet credits were recorded in this period.',
              style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700),
            )
          else ...[
            pw.TableHelper.fromTextArray(
              headers: [
                'Pickup ID',
                'Seller',
                'Parcels',
                'Handed Over',
                'Pickup Total',
                'Wallet Credit',
              ],
              data: [
                ...credited.map(
                  (p) => [
                    p.id,
                    p.sellerName,
                    '${p.parcelCount}',
                    _formatDate(p.handedOverAt!),
                    _pdfPeso(p.totalAmount),
                    _pdfPeso(_creditFor(p)),
                  ],
                ),
                [
                  'TOTAL',
                  '',
                  '$totalParcelsHandedOver',
                  '',
                  '',
                  _pdfPeso(_earnedInRange),
                ],
              ],
              headerDecoration: const pw.BoxDecoration(color: maroonPdf),
              headerStyle: pw.TextStyle(
                color: PdfColors.white,
                fontWeight: pw.FontWeight.bold,
                fontSize: 8,
              ),
              cellStyle: const pw.TextStyle(fontSize: 7.5),
              cellPadding: const pw.EdgeInsets.all(5),
              cellAlignments: {
                0: pw.Alignment.centerLeft,
                1: pw.Alignment.centerLeft,
                2: pw.Alignment.center,
                3: pw.Alignment.center,
                4: pw.Alignment.centerRight,
                5: pw.Alignment.centerRight,
              },
              border: pw.TableBorder.all(color: borderPdf, width: 0.5),
            ),
          ],

          // 4. PARCEL DETAILS
          pw.SizedBox(height: 18),
          _pdfSectionTitle('4. Parcel Details'),
          pw.SizedBox(height: 8),
          if (records.isEmpty)
            pw.Text(
              'No parcel delivery records were found in this period.',
              style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700),
            )
          else
            pw.TableHelper.fromTextArray(
              headers: [
                'Parcel ID',
                'Product',
                'Recipient',
                'Address',
                'Date',
                'Status',
              ],
              data: records
                  .map(
                    (r) => [
                      r.parcelId,
                      r.product,
                      r.recipient,
                      r.address,
                      _formatDate(r.deliveredAt),
                      r.status,
                    ],
                  )
                  .toList(),
              headerDecoration: const pw.BoxDecoration(color: maroonPdf),
              headerStyle: pw.TextStyle(
                color: PdfColors.white,
                fontWeight: pw.FontWeight.bold,
                fontSize: 8,
              ),
              cellStyle: const pw.TextStyle(fontSize: 7.5),
              cellPadding: const pw.EdgeInsets.all(5),
              border: pw.TableBorder.all(color: borderPdf, width: 0.5),
            ),

          pw.SizedBox(height: 14),
          pw.Text(
            'Note: This report is generated from the delivery and wallet '
            'records available in the app. Amounts are in Philippine pesos '
            '(PHP).',
            style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700),
          ),
        ],
      ),
    );

    await Printing.layoutPdf(
      onLayout: (format) async => pdf.save(),
      name:
          'Lumear_Delivery_Report_${_fileDate(_startDate)}_to_${_fileDate(_endDate)}.pdf',
    );
  }

  pw.Widget _pdfSectionTitle(String title) {
    return pw.Text(
      title,
      style: pw.TextStyle(
        fontSize: 14,
        fontWeight: pw.FontWeight.bold,
        color: const PdfColor.fromInt(0xFF62191C),
      ),
    );
  }

  pw.TableRow _pdfSummaryRow(
    String label,
    String value, {
    bool header = false,
    PdfColor fill = PdfColors.white,
  }) {
    final weight = header ? pw.FontWeight.bold : pw.FontWeight.normal;

    return pw.TableRow(
      decoration: pw.BoxDecoration(color: header ? fill : PdfColors.white),
      children: [
        pw.Padding(
          padding: const pw.EdgeInsets.all(7),
          child: pw.Text(
            label,
            style: pw.TextStyle(fontSize: 9, fontWeight: weight),
          ),
        ),
        pw.Padding(
          padding: const pw.EdgeInsets.all(7),
          child: pw.Text(
            value,
            textAlign: pw.TextAlign.right,
            style: pw.TextStyle(fontSize: 9, fontWeight: weight),
          ),
        ),
      ],
    );
  }

  // ---------------------------------------------------------------------------
  // UI HELPERS
  // ---------------------------------------------------------------------------

  Color _statusColor(String status) {
    switch (status) {
      case 'Delivered':
        return Colors.green.shade700;
      case 'Failed':
        return Colors.red.shade700;
      case 'Returned':
        return Colors.deepOrange.shade700;
      default:
        return Colors.blueGrey.shade700;
    }
  }

  IconData _statusIcon(String status) {
    switch (status) {
      case 'Delivered':
        return Icons.check_circle_outline;
      case 'Failed':
        return Icons.cancel_outlined;
      case 'Returned':
        return Icons.keyboard_return;
      default:
        return Icons.pending_actions;
    }
  }

  Widget _sectionTitle(String title, {String? trailing}) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 17,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
        if (trailing != null)
          Text(
            trailing,
            style: const TextStyle(color: brownBeige, fontSize: 12),
          ),
      ],
    );
  }

  Widget _chip({
    required String label,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        label: Text(label),
        selected: selected,
        showCheckmark: false,
        onSelected: (_) => onTap(),
        selectedColor: darkMaroon,
        backgroundColor: Colors.white,
        side: BorderSide(color: selected ? darkMaroon : cardBorder),
        labelStyle: TextStyle(
          color: selected ? Colors.white : darkMaroon,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  Widget _dateTile(String label, DateTime date, VoidCallback onTap) {
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: cream, width: 1.5),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(
                  color: brownBeige,
                  fontSize: 11,
                  letterSpacing: 0.6,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(Icons.calendar_month, color: maroon, size: 18),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      _formatDate(date),
                      style: const TextStyle(
                        color: darkMaroon,
                        fontWeight: FontWeight.bold,
                        fontSize: 13,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  // Hero card: total parcels + success rate.
  Widget _buildHeroCard(int total) {
    final rate = _successRate;

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
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'TOTAL PARCELS',
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 12,
                        letterSpacing: 1,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '$total',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 34,
                        fontWeight: FontWeight.w800,
                        height: 1,
                      ),
                    ),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    '${(rate * 100).toStringAsFixed(0)}%',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 26,
                      fontWeight: FontWeight.w800,
                      height: 1,
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Success rate',
                    style: TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 16),
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: LinearProgressIndicator(
              value: rate,
              minHeight: 7,
              backgroundColor: Colors.white24,
              valueColor: const AlwaysStoppedAnimation<Color>(
                Color(0xFF8FE3A0),
              ),
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Based on finished deliveries (pending excluded)',
            style: TextStyle(color: cream, fontSize: 11),
          ),
        ],
      ),
    );
  }

  // Equal-size status card (used in a 2x2 layout).
  Widget _statusCard(String status) {
    final color = _statusColor(status);

    return Container(
      height: 76,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: cardBorder),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(_statusIcon(status), color: color, size: 22),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${_countStatus(status)}',
                  style: TextStyle(
                    color: color,
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    height: 1.1,
                  ),
                ),
                Text(
                  status,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: brownBeige, fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusGrid() {
    return Column(
      children: [
        Row(
          children: [
            Expanded(child: _statusCard('Delivered')),
            const SizedBox(width: 12),
            Expanded(child: _statusCard('Failed')),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(child: _statusCard('Returned')),
            const SizedBox(width: 12),
            Expanded(child: _statusCard('Pending')),
          ],
        ),
      ],
    );
  }

  // Wallet earnings card for the selected period.
  Widget _buildEarningsCard() {
    final handovers = _creditedInRange.length;
    const green = Color(0xFF287544);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: cardBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: green.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.account_balance_wallet_outlined,
                  color: green,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Earned this period',
                      style: TextStyle(color: brownBeige, fontSize: 12),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      _peso(_earnedInRange),
                      style: const TextStyle(
                        color: green,
                        fontSize: 24,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          const Divider(height: 1, color: cardBorder),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _earningsMini(
                  label: 'Hub handovers',
                  value: '$handovers',
                  color: darkMaroon,
                ),
              ),
              Container(width: 1, height: 34, color: cardBorder),
              Expanded(
                child: _earningsMini(
                  label: 'Incoming credits',
                  value: _peso(_incomingTotal),
                  color: pendingAmber,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          const Text(
            'Credited to your wallet by Lumear Logistics after each '
            'successful hub handover.',
            style: TextStyle(color: brownBeige, fontSize: 11, height: 1.4),
          ),
          if (widget.pickups.isEmpty) ...[
            const SizedBox(height: 6),
            const Text(
              'No pickup data connected yet, so earnings show as zero.',
              style: TextStyle(color: Colors.black45, fontSize: 11),
            ),
          ],
        ],
      ),
    );
  }

  Widget _earningsMini({
    required String label,
    required String value,
    required Color color,
  }) {
    return Column(
      children: [
        Text(
          value,
          style: TextStyle(
            color: color,
            fontSize: 16,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 3),
        Text(label, style: const TextStyle(color: brownBeige, fontSize: 11)),
      ],
    );
  }

  Widget _recordCard(_DeliveryRecord record) {
    final color = _statusColor(record.status);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: cardBorder),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(_statusIcon(record.status), color: color, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        record.parcelId,
                        style: const TextStyle(
                          color: darkMaroon,
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                        ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: color.withValues(alpha: 0.10),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        record.status,
                        style: TextStyle(
                          color: color,
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  record.product,
                  style: const TextStyle(
                    color: darkMaroon,
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${record.recipient} • ${record.address}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: brownBeige, fontSize: 12),
                ),
                const SizedBox(height: 2),
                Text(
                  _formatDate(record.deliveredAt),
                  style: const TextStyle(color: brownBeige, fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _emptyRecords() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(26),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: cardBorder),
      ),
      child: const Column(
        children: [
          Icon(Icons.inbox_outlined, size: 36, color: brownBeige),
          SizedBox(height: 8),
          Text(
            'No records found',
            style: TextStyle(color: darkMaroon, fontWeight: FontWeight.bold),
          ),
          SizedBox(height: 4),
          Text(
            'Try another date range or status filter.',
            textAlign: TextAlign.center,
            style: TextStyle(color: brownBeige, fontSize: 12),
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // BUILD
  // ---------------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final inRange = _recordsInRange;
    final visible = _visibleRecords;

    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          'Delivery Reports',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const Text(
              'Delivery & Earnings Report',
              style: TextStyle(
                color: darkMaroon,
                fontSize: 22,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'Review your parcel deliveries and wallet earnings, then '
              'export them as a PDF.',
              style: TextStyle(color: brownBeige, height: 1.4),
            ),
            const SizedBox(height: 18),

            // REPORT PERIOD
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: cardBorder),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Report Period',
                    style: TextStyle(
                      color: darkMaroon,
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 12),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: _rangeOptions
                          .map(
                            (option) => _chip(
                              label: option,
                              selected: _selectedRange == option,
                              onTap: () => _applyRange(option),
                            ),
                          )
                          .toList(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      _dateTile(
                        'START DATE',
                        _startDate,
                        () => _pickDate(isStart: true),
                      ),
                      const SizedBox(width: 10),
                      _dateTile(
                        'END DATE',
                        _endDate,
                        () => _pickDate(isStart: false),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // DELIVERY SUMMARY
            const SizedBox(height: 22),
            _sectionTitle('Delivery Summary'),
            const SizedBox(height: 10),
            _buildHeroCard(inRange.length),
            const SizedBox(height: 12),
            _buildStatusGrid(),

            // EARNINGS
            const SizedBox(height: 22),
            _sectionTitle('Wallet Earnings'),
            const SizedBox(height: 10),
            _buildEarningsCard(),

            // PARCEL DETAILS
            const SizedBox(height: 22),
            _sectionTitle(
              'Parcel Details',
              trailing: '${visible.length} record(s)',
            ),
            const SizedBox(height: 10),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: _statusFilters
                    .map(
                      (status) => _chip(
                        label: status,
                        selected: _statusFilter == status,
                        onTap: () => setState(() => _statusFilter = status),
                      ),
                    )
                    .toList(),
              ),
            ),
            const SizedBox(height: 6),
            if (visible.isEmpty)
              _emptyRecords()
            else
              ...visible.map(_recordCard),

            // PDF BUTTON
            const SizedBox(height: 12),
            SizedBox(
              height: 54,
              child: ElevatedButton.icon(
                onPressed: _generatePdf,
                icon: const Icon(Icons.picture_as_pdf_outlined),
                label: const Text(
                  'Generate Report (PDF)',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: darkMaroon,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'The PDF includes the delivery summary, wallet earnings, '
              'earnings breakdown, and all parcels in the selected dates.',
              textAlign: TextAlign.center,
              style: TextStyle(color: brownBeige, fontSize: 11, height: 1.4),
            ),
            const SizedBox(height: 6),
            const Text(
              'Note: Parcel records are sample data. Connect your Laravel '
              'API to show actual rider delivery records.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.black45, fontSize: 11),
            ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }
}

class _DeliveryRecord {
  final String parcelId;
  final String product;
  final String recipient;
  final String address;
  final DateTime deliveredAt;
  final String status;

  const _DeliveryRecord({
    required this.parcelId,
    required this.product,
    required this.recipient,
    required this.address,
    required this.deliveredAt,
    required this.status,
  });
}

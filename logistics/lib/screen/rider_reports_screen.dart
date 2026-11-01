import 'package:flutter/material.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

// ============================================================
// LUMEAR COLORS
// ============================================================

const Color darkMaroon = Color(0xFF671B1D);
const Color maroon = Color(0xFF873632);
const Color brown = Color(0xFF9E7161);
const Color cream = Color(0xFFFFF8E7);
const Color paleCream = Color(0xFFFFFDF5);
const Color borderColor = Color(0xFFE8D6C3);

const Color _textColor = Color(0xFF382523);
const Color _mutedColor = Color(0xFF8C7770);
const Color _deliveredColor = Color(0xFF3D9875);
const Color _ongoingColor = Color(0xFF4A7FBF);
const Color _failedColor = Color(0xFFC4573A);

const double _cardRadius = 16;
const double _innerRadius = 12;

// ============================================================
// RIDER REPORTS SCREEN
// ============================================================

class RiderReportsScreen extends StatefulWidget {
  const RiderReportsScreen({super.key});

  @override
  State<RiderReportsScreen> createState() => _RiderReportsScreenState();
}

class _RiderReportsScreenState extends State<RiderReportsScreen> {
  static const List<String> _periods = [
    'Today',
    'This Week',
    'This Month',
    'All Time',
  ];

  static const List<String> _statuses = [
    'All',
    'Delivered',
    'Ongoing',
    'Failed',
  ];

  String _selectedPeriod = 'This Month';
  String _selectedStatus = 'All';
  bool _isGenerating = false;

  final List<Map<String, dynamic>> _reports = [
    {
      'id': 'TRK-1001',
      'rider': 'Juan Dela Cruz',
      'destination': 'Brgy. Masapang',
      'status': 'Delivered',
      'date': 'Oct 09, 2026',
    },
    {
      'id': 'TRK-1002',
      'rider': 'Pedro Santos',
      'destination': 'Victoria Town',
      'status': 'Ongoing',
      'date': 'Oct 09, 2026',
    },
    {
      'id': 'TRK-1003',
      'rider': 'Mark Reyes',
      'destination': 'Brgy. San Roque',
      'status': 'Failed',
      'date': 'Oct 08, 2026',
    },
    {
      'id': 'TRK-1004',
      'rider': 'Juan Dela Cruz',
      'destination': 'Brgy. Masapang',
      'status': 'Delivered',
      'date': 'Oct 08, 2026',
    },
    {
      'id': 'TRK-1005',
      'rider': 'Pedro Santos',
      'destination': 'Victoria Town',
      'status': 'Delivered',
      'date': 'Oct 07, 2026',
    },
  ];

  // ============================================================
  // DATE PARSING
  // ============================================================

  DateTime _parseDate(String value) {
    final parts = value.split(' ');

    const months = {
      'Jan': 1,
      'Feb': 2,
      'Mar': 3,
      'Apr': 4,
      'May': 5,
      'Jun': 6,
      'Jul': 7,
      'Aug': 8,
      'Sep': 9,
      'Oct': 10,
      'Nov': 11,
      'Dec': 12,
    };

    return DateTime(
      int.parse(parts[2]),
      months[parts[0]] ?? 1,
      int.parse(parts[1].replaceAll(',', '')),
    );
  }

  // ============================================================
  // PERIOD FILTER
  // ============================================================

  List<Map<String, dynamic>> get _periodReports {
    final now = DateTime.now();

    return _reports.where((report) {
      final date = _parseDate(report['date'] as String);

      switch (_selectedPeriod) {
        case 'Today':
          return date.year == now.year &&
              date.month == now.month &&
              date.day == now.day;

        case 'This Week':
          final startOfWeek = DateTime(
            now.year,
            now.month,
            now.day,
          ).subtract(Duration(days: now.weekday - 1));

          final endOfWeek = startOfWeek.add(const Duration(days: 7));

          return !date.isBefore(startOfWeek) && date.isBefore(endOfWeek);

        case 'This Month':
          return date.year == now.year && date.month == now.month;

        case 'All Time':
        default:
          return true;
      }
    }).toList();
  }

  // ============================================================
  // STATUS FILTER
  // ============================================================

  List<Map<String, dynamic>> get _filteredReports {
    if (_selectedStatus == 'All') {
      return _periodReports;
    }

    return _periodReports
        .where((report) => report['status'] == _selectedStatus)
        .toList();
  }

  int _countStatus(String status) {
    return _periodReports.where((report) => report['status'] == status).length;
  }

  // ============================================================
  // STATUS HELPERS
  // ============================================================

  Color _statusColor(String status) {
    switch (status) {
      case 'Delivered':
        return _deliveredColor;
      case 'Ongoing':
        return _ongoingColor;
      case 'Failed':
        return _failedColor;
      default:
        return brown;
    }
  }

  IconData _statusIcon(String status) {
    switch (status) {
      case 'Delivered':
        return Icons.check_circle_rounded;
      case 'Ongoing':
        return Icons.local_shipping_rounded;
      case 'Failed':
        return Icons.error_rounded;
      default:
        return Icons.inventory_2_rounded;
    }
  }

  String _initials(String name) {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .toList();

    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first[0].toUpperCase();

    return (parts.first[0] + parts.last[0]).toUpperCase();
  }

  BoxDecoration _cardDecoration() {
    return BoxDecoration(
      color: paleCream,
      borderRadius: BorderRadius.circular(_cardRadius),
      border: Border.all(color: borderColor.withValues(alpha: 0.8)),
    );
  }

  // ============================================================
  // MAIN UI
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final records = _filteredReports;

    return Container(
      color: cream,
      child: ListView(
        physics: const BouncingScrollPhysics(),
        // Bottom padding leaves room for the floating navigation bar.
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 120),
        children: [
          _buildHeader(),
          const SizedBox(height: 20),
          _buildPeriodSelector(),
          const SizedBox(height: 16),
          _buildOverviewCard(),
          const SizedBox(height: 24),
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Delivery records',
                  style: TextStyle(
                    color: _textColor,
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.2,
                  ),
                ),
              ),
              Text(
                records.length == 1 ? '1 record' : '${records.length} records',
                style: const TextStyle(color: _mutedColor, fontSize: 12),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _buildStatusFilter(),
          const SizedBox(height: 14),
          if (records.isEmpty)
            _buildEmptyState()
          else
            ...records.map(_buildReportItem),
        ],
      ),
    );
  }

  // ============================================================
  // HEADER WITH EXPORT ACTION
  // ============================================================

  Widget _buildHeader() {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Delivery reports',
                style: TextStyle(
                  color: darkMaroon,
                  fontSize: 26,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.6,
                ),
              ),
              SizedBox(height: 4),
              Text(
                'Parcel deliveries and rider performance.',
                style: TextStyle(color: _mutedColor, fontSize: 12),
              ),
            ],
          ),
        ),
        const SizedBox(width: 12),
        FilledButton.icon(
          onPressed: _isGenerating ? null : _generateReport,
          icon: _isGenerating
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : const Icon(Icons.picture_as_pdf_outlined, size: 18),
          label: Text(_isGenerating ? 'Preparing' : 'Export PDF'),
          style: FilledButton.styleFrom(
            backgroundColor: darkMaroon,
            foregroundColor: Colors.white,
            disabledBackgroundColor: brown,
            disabledForegroundColor: Colors.white,
            textStyle: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(_innerRadius),
            ),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // PERIOD SEGMENTED CONTROL
  // ============================================================

  Widget _buildPeriodSelector() {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: borderColor.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(_innerRadius + 2),
      ),
      child: Row(
        children: _periods.map((period) {
          final bool selected = _selectedPeriod == period;

          return Expanded(
            child: Semantics(
              button: true,
              selected: selected,
              label: 'Report period $period',
              child: GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () => setState(() => _selectedPeriod = period),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  curve: Curves.easeOut,
                  height: 38,
                  alignment: Alignment.center,
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  decoration: BoxDecoration(
                    color: selected ? paleCream : Colors.transparent,
                    borderRadius: BorderRadius.circular(_innerRadius - 1),
                    border: Border.all(
                      color: selected
                          ? borderColor.withValues(alpha: 0.9)
                          : Colors.transparent,
                    ),
                  ),
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      period,
                      style: TextStyle(
                        color: selected ? darkMaroon : _mutedColor,
                        fontSize: 12,
                        fontWeight: selected
                            ? FontWeight.w800
                            : FontWeight.w600,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  // ============================================================
  // OVERVIEW CARD (total, delivered rate, status split)
  // ============================================================

  Widget _buildOverviewCard() {
    final int total = _periodReports.length;
    final int delivered = _countStatus('Delivered');
    final int ongoing = _countStatus('Ongoing');
    final int failed = _countStatus('Failed');
    final int rate = total == 0 ? 0 : ((delivered / total) * 100).round();

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: _cardDecoration(),
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
                      'Total deliveries',
                      style: TextStyle(color: _mutedColor, fontSize: 12),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '$total',
                      style: const TextStyle(
                        color: _textColor,
                        fontSize: 34,
                        height: 1,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -1,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: _deliveredColor.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(_innerRadius - 2),
                ),
                child: Text(
                  '$rate% delivered',
                  style: const TextStyle(
                    color: _deliveredColor,
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          _buildStatusBar(delivered, ongoing, failed),
          const SizedBox(height: 16),
          Row(
            children: [
              _buildLegendItem('Delivered', delivered, _deliveredColor),
              _buildLegendItem('Ongoing', ongoing, _ongoingColor),
              _buildLegendItem('Failed', failed, _failedColor),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStatusBar(int delivered, int ongoing, int failed) {
    final int total = delivered + ongoing + failed;

    return ClipRRect(
      borderRadius: BorderRadius.circular(6),
      child: SizedBox(
        height: 8,
        child: total == 0
            ? Container(color: borderColor.withValues(alpha: 0.7))
            : Row(
                children: [
                  if (delivered > 0)
                    Expanded(
                      flex: delivered,
                      child: Container(color: _deliveredColor),
                    ),
                  if (ongoing > 0)
                    Expanded(
                      flex: ongoing,
                      child: Container(color: _ongoingColor),
                    ),
                  if (failed > 0)
                    Expanded(
                      flex: failed,
                      child: Container(color: _failedColor),
                    ),
                ],
              ),
      ),
    );
  }

  Widget _buildLegendItem(String label, int value, Color color) {
    return Expanded(
      child: Row(
        children: [
          Container(
            width: 9,
            height: 9,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 7),
          Flexible(
            child: Text.rich(
              TextSpan(
                children: [
                  TextSpan(
                    text: '$value ',
                    style: const TextStyle(
                      color: _textColor,
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  TextSpan(
                    text: label,
                    style: const TextStyle(color: _mutedColor, fontSize: 11),
                  ),
                ],
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // STATUS CHIPS (with counts)
  // ============================================================

  Widget _buildStatusFilter() {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      physics: const BouncingScrollPhysics(),
      child: Row(
        children: _statuses.map((status) {
          final bool selected = _selectedStatus == status;
          final int count = status == 'All'
              ? _periodReports.length
              : _countStatus(status);

          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: ChoiceChip(
              label: Text('$status  $count'),
              selected: selected,
              showCheckmark: false,
              onSelected: (_) => setState(() => _selectedStatus = status),
              selectedColor: darkMaroon,
              backgroundColor: paleCream,
              labelStyle: TextStyle(
                color: selected ? Colors.white : _mutedColor,
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
              side: BorderSide(color: selected ? darkMaroon : borderColor),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(_innerRadius - 2),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  // ============================================================
  // DELIVERY RECORD ITEM
  // ============================================================

  Widget _buildReportItem(Map<String, dynamic> report) {
    final String status = report['status'] as String;
    final String rider = report['rider'] as String;
    final Color statusColor = _statusColor(status);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: _cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: darkMaroon.withValues(alpha: 0.08),
                  shape: BoxShape.circle,
                ),
                child: Text(
                  _initials(rider),
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      rider,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: _textColor,
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      report['id'] as String,
                      style: const TextStyle(color: _mutedColor, fontSize: 11),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.11),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(_statusIcon(status), color: statusColor, size: 13),
                    const SizedBox(width: 5),
                    Text(
                      status,
                      style: TextStyle(
                        color: statusColor,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Divider(height: 1, color: borderColor.withValues(alpha: 0.7)),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                flex: 3,
                child: _detailItem(
                  Icons.location_on_outlined,
                  report['destination'] as String,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                flex: 2,
                child: _detailItem(
                  Icons.calendar_today_outlined,
                  report['date'] as String,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _detailItem(IconData icon, String text) {
    return Row(
      children: [
        Icon(icon, color: brown, size: 15),
        const SizedBox(width: 7),
        Expanded(
          child: Text(
            text,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: _mutedColor, fontSize: 12),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // EMPTY STATE
  // ============================================================

  Widget _buildEmptyState() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
      decoration: _cardDecoration(),
      child: Column(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: borderColor.withValues(alpha: 0.55),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.search_off_rounded, size: 26, color: brown),
          ),
          const SizedBox(height: 14),
          const Text(
            'No records found',
            style: TextStyle(
              color: _textColor,
              fontSize: 14,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 5),
          const Text(
            'Try a different report period or delivery status.',
            textAlign: TextAlign.center,
            style: TextStyle(color: _mutedColor, fontSize: 12, height: 1.4),
          ),
          const SizedBox(height: 14),
          OutlinedButton(
            onPressed: () {
              setState(() {
                _selectedPeriod = 'All Time';
                _selectedStatus = 'All';
              });
            },
            style: OutlinedButton.styleFrom(
              foregroundColor: darkMaroon,
              side: const BorderSide(color: borderColor),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(_innerRadius - 2),
              ),
            ),
            child: const Text(
              'Show all records',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // GENERATE PDF AND OPEN PRINT PREVIEW
  // ============================================================

  Future<void> _generateReport() async {
    if (_isGenerating) return;

    setState(() {
      _isGenerating = true;
    });

    try {
      // Snapshot of the currently filtered records.
      final records = List<Map<String, dynamic>>.from(_filteredReports);

      final deliveredCount = records
          .where((r) => r['status'] == 'Delivered')
          .length;
      final ongoingCount = records
          .where((r) => r['status'] == 'Ongoing')
          .length;
      final failedCount = records.where((r) => r['status'] == 'Failed').length;

      final generatedAt = DateTime.now();

      final pdf = pw.Document();

      final maroonPdf = PdfColor.fromHex('#671B1D');
      final brownPdf = PdfColor.fromHex('#9E7161');
      final creamPdf = PdfColor.fromHex('#FFF8E7');
      final borderPdf = PdfColor.fromHex('#E8D6C3');
      final greenPdf = PdfColor.fromHex('#3D9875');
      final bluePdf = PdfColor.fromHex('#4A7FBF');
      final redPdf = PdfColor.fromHex('#C4573A');

      pdf.addPage(
        pw.MultiPage(
          pageFormat: PdfPageFormat.a4,
          margin: const pw.EdgeInsets.all(32),

          header: (context) => pw.Container(
            padding: const pw.EdgeInsets.only(bottom: 12),
            decoration: pw.BoxDecoration(
              border: pw.Border(bottom: pw.BorderSide(color: borderPdf)),
            ),
            child: pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              children: [
                pw.Text(
                  'Lumear Logistics',
                  style: pw.TextStyle(
                    fontSize: 16,
                    fontWeight: pw.FontWeight.bold,
                    color: maroonPdf,
                  ),
                ),
                pw.Text(
                  'Delivery Report',
                  style: pw.TextStyle(fontSize: 10, color: brownPdf),
                ),
              ],
            ),
          ),

          footer: (context) => pw.Container(
            margin: const pw.EdgeInsets.only(top: 12),
            padding: const pw.EdgeInsets.only(top: 8),
            decoration: pw.BoxDecoration(
              border: pw.Border(top: pw.BorderSide(color: borderPdf)),
            ),
            child: pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              children: [
                pw.Text(
                  'Lumear Logistics',
                  style: pw.TextStyle(fontSize: 9, color: brownPdf),
                ),
                pw.Text(
                  'Page ${context.pageNumber} of ${context.pagesCount}',
                  style: pw.TextStyle(fontSize: 9, color: brownPdf),
                ),
              ],
            ),
          ),

          build: (context) => [
            pw.SizedBox(height: 24),

            pw.Text(
              'Delivery Reports Summary',
              style: pw.TextStyle(
                fontSize: 23,
                fontWeight: pw.FontWeight.bold,
                color: maroonPdf,
              ),
            ),

            pw.SizedBox(height: 8),

            pw.Text(
              'Report period: $_selectedPeriod',
              style: pw.TextStyle(fontSize: 11, color: brownPdf),
            ),

            pw.Text(
              'Status filter: $_selectedStatus',
              style: pw.TextStyle(fontSize: 11, color: brownPdf),
            ),

            pw.Text(
              'Generated: ${generatedAt.toLocal().toString().substring(0, 16)}',
              style: pw.TextStyle(fontSize: 10, color: brownPdf),
            ),

            pw.SizedBox(height: 22),

            pw.Text(
              'Performance overview',
              style: pw.TextStyle(
                fontSize: 13,
                fontWeight: pw.FontWeight.bold,
                color: maroonPdf,
              ),
            ),

            pw.SizedBox(height: 10),

            pw.Table(
              border: pw.TableBorder.all(color: borderPdf),
              children: [
                pw.TableRow(
                  decoration: pw.BoxDecoration(color: creamPdf),
                  children: [
                    _pdfCell('Total records', bold: true),
                    _pdfCell('Delivered', bold: true),
                    _pdfCell('Ongoing', bold: true),
                    _pdfCell('Failed', bold: true),
                  ],
                ),
                pw.TableRow(
                  children: [
                    _pdfCell('${records.length}'),
                    _pdfCell('$deliveredCount', color: greenPdf),
                    _pdfCell('$ongoingCount', color: bluePdf),
                    _pdfCell('$failedCount', color: redPdf),
                  ],
                ),
              ],
            ),

            pw.SizedBox(height: 26),

            pw.Text(
              'Delivery records',
              style: pw.TextStyle(
                fontSize: 13,
                fontWeight: pw.FontWeight.bold,
                color: maroonPdf,
              ),
            ),

            pw.SizedBox(height: 10),

            if (records.isEmpty)
              pw.Container(
                padding: const pw.EdgeInsets.all(16),
                decoration: pw.BoxDecoration(
                  border: pw.Border.all(color: borderPdf),
                ),
                child: pw.Text(
                  'No delivery records match the selected filters.',
                  style: pw.TextStyle(fontSize: 11, color: brownPdf),
                ),
              )
            else
              pw.TableHelper.fromTextArray(
                headers: [
                  'Tracking ID',
                  'Rider',
                  'Destination',
                  'Status',
                  'Date',
                ],
                data: records.map((record) {
                  return [
                    record['id']?.toString() ?? '',
                    record['rider']?.toString() ?? '',
                    record['destination']?.toString() ?? '',
                    record['status']?.toString() ?? '',
                    record['date']?.toString() ?? '',
                  ];
                }).toList(),
                headerDecoration: pw.BoxDecoration(color: maroonPdf),
                headerStyle: pw.TextStyle(
                  color: PdfColors.white,
                  fontSize: 9,
                  fontWeight: pw.FontWeight.bold,
                ),
                cellStyle: const pw.TextStyle(fontSize: 9),
                cellAlignment: pw.Alignment.centerLeft,
                cellPadding: const pw.EdgeInsets.all(7),
                border: pw.TableBorder.all(color: borderPdf),
                oddRowDecoration: pw.BoxDecoration(color: creamPdf),
              ),

            pw.SizedBox(height: 24),

            pw.Container(
              padding: const pw.EdgeInsets.all(12),
              decoration: pw.BoxDecoration(
                color: creamPdf,
                border: pw.Border.all(color: borderPdf),
              ),
              child: pw.Text(
                'Report generated by Lumear Logistics Admin. '
                'This report contains the records currently available '
                'in the application.',
                style: pw.TextStyle(fontSize: 9, color: brownPdf),
              ),
            ),
          ],
        ),
      );

      final pdfBytes = await pdf.save();

      if (!mounted) return;

      // Opens PDF print preview. Available save/print options
      // depend on the platform and installed print services.
      await Printing.layoutPdf(
        onLayout: (format) async => pdfBytes,
        name: 'Lumear_Delivery_Report.pdf',
      );
    } catch (error) {
      if (!mounted) return;

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text('Unable to generate PDF: $error')));
    } finally {
      if (mounted) {
        setState(() {
          _isGenerating = false;
        });
      }
    }
  }

  // ============================================================
  // PDF TABLE CELL
  // ============================================================

  pw.Widget _pdfCell(String text, {bool bold = false, PdfColor? color}) {
    return pw.Padding(
      padding: const pw.EdgeInsets.all(8),
      child: pw.Text(
        text,
        style: pw.TextStyle(
          fontSize: 9,
          fontWeight: bold ? pw.FontWeight.bold : pw.FontWeight.normal,
          color: color ?? PdfColors.black,
        ),
      ),
    );
  }
}

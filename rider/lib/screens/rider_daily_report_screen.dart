import 'package:flutter/material.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

import 'package:flutter_application_1/models/rider_profile.dart';
import 'package:flutter_application_1/models/parcel_store.dart';

// ============================================================
// DAILY REPORT DATA
// Computed once per build / per export, so every number on the
// screen and in the PDF comes from the exact same snapshot.
// ============================================================

class _DayReport {
  final DateTime day;
  final List<ParcelData> delivered;
  final List<ParcelData> failed;
  final double codCollected;
  final double riderEarnings;
  final double logisticsShare;

  const _DayReport({
    required this.day,
    required this.delivered,
    required this.failed,
    required this.codCollected,
    required this.riderEarnings,
    required this.logisticsShare,
  });

  int get attempts => delivered.length + failed.length;

  double get successRate => attempts == 0 ? 0 : delivered.length / attempts;

  bool get isEmpty => attempts == 0;

  static bool sameDay(DateTime? a, DateTime b) =>
      a != null && a.year == b.year && a.month == b.month && a.day == b.day;

  factory _DayReport.forDay(DateTime day) {
    final all = ParcelStore.parcels.value;

    final delivered = all
        .where((p) => p.status == 'Delivered' && sameDay(p.deliveredAt, day))
        .toList();

    final failed = all
        .where(
          (p) =>
              p.status == 'Delivery Failed' && sameDay(p.statusUpdatedAt, day),
        )
        .toList();

    // Newest first.
    delivered.sort((a, b) => b.deliveredAt!.compareTo(a.deliveredAt!));
    failed.sort((a, b) => b.statusUpdatedAt!.compareTo(a.statusUpdatedAt!));

    final double cod = delivered.fold<double>(
      0,
      (sum, p) => sum + p.codAmount.toDouble(),
    );

    return _DayReport(
      day: day,
      delivered: List.unmodifiable(delivered),
      failed: List.unmodifiable(failed),
      codCollected: cod,
      riderEarnings:
          delivered.length * ParcelStore.earningPerDelivery.toDouble(),
      logisticsShare:
          delivered.length * ParcelStore.logisticsSharePerDelivery.toDouble(),
    );
  }
}

// ============================================================
// SCREEN
// ============================================================

class RiderDailyReportScreen extends StatefulWidget {
  const RiderDailyReportScreen({super.key});

  @override
  State<RiderDailyReportScreen> createState() => _RiderDailyReportScreenState();
}

class _RiderDailyReportScreenState extends State<RiderDailyReportScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color muted = Color(0xFF8B6F63);
  static const Color danger = Color(0xFFB3261E);
  static const Color success = Color(0xFF2E7D32);

  DateTime _selectedDate = DateTime.now();

  bool _isPreviewing = false;
  bool _isSharing = false;

  bool get _isBusy => _isPreviewing || _isSharing;

  bool get _isToday => _DayReport.sameDay(_selectedDate, DateTime.now());

  // ============================================
  // FORMATTERS
  // ============================================

  /// Formats with thousands separators, e.g. ₱12,345.50
  String _money(num value, {String symbol = '₱'}) {
    final String fixed = value.toStringAsFixed(2);
    final List<String> parts = fixed.split('.');
    final String intPart = parts[0].replaceAllMapped(
      RegExp(r'\B(?=(\d{3})+(?!\d))'),
      (_) => ',',
    );
    return '$symbol$intPart.${parts[1]}';
  }

  String _dateLabel(DateTime d) =>
      '${d.day.toString().padLeft(2, '0')}/'
      '${d.month.toString().padLeft(2, '0')}/${d.year}';

  String _longDateLabel(DateTime d) {
    const months = [
      'January',
      'February',
      'March',
      'April',
      'May',
      'June',
      'July',
      'August',
      'September',
      'October',
      'November',
      'December',
    ];
    return '${months[d.month - 1]} ${d.day}, ${d.year}';
  }

  String _timeLabel(DateTime? d) {
    if (d == null) return '--:--';
    final int hour12 = d.hour % 12 == 0 ? 12 : d.hour % 12;
    final String period = d.hour >= 12 ? 'PM' : 'AM';
    return '$hour12:${d.minute.toString().padLeft(2, '0')} $period';
  }

  String _fileDate(DateTime d) =>
      '${d.year}-${d.month.toString().padLeft(2, '0')}-'
      '${d.day.toString().padLeft(2, '0')}';

  String _percent(double ratio) => '${(ratio * 100).round()}%';

  String _deliveriesText(int count) =>
      '$count successful ${count == 1 ? 'delivery' : 'deliveries'}';

  // ============================================
  // DATE SELECTION
  // ============================================

  Future<void> _pickDate() async {
    final DateTime now = DateTime.now();

    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: now.subtract(const Duration(days: 365)),
      lastDate: now,
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: darkMaroon,
              onPrimary: Colors.white,
              surface: cream,
              onSurface: darkMaroon,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null && mounted) {
      setState(() => _selectedDate = picked);
    }
  }

  void _shiftDay(int delta) {
    final DateTime next = _selectedDate.add(Duration(days: delta));
    if (next.isAfter(DateTime.now())) return;
    setState(() => _selectedDate = next);
  }

  // ============================================
  // PDF
  // ============================================

  Future<pw.Document> _buildPdf(_DayReport report) async {
    final pdf = pw.Document();
    final DateTime generatedAt = DateTime.now();

    final String rider = RiderProfile.name.trim().isEmpty
        ? 'Lumear Rider'
        : RiderProfile.name.trim();

    // The default PDF font cannot draw the peso sign (₱), so we try to load
    // a font that can. If that fails (e.g. offline), fall back to "PHP".
    pw.ThemeData? theme;
    String symbol = 'PHP ';

    try {
      final regular = await PdfGoogleFonts.notoSansRegular();
      final bold = await PdfGoogleFonts.notoSansBold();
      theme = pw.ThemeData.withFont(base: regular, bold: bold);
      symbol = '₱';
    } catch (_) {
      theme = null;
    }

    String money(num v) => _money(v, symbol: symbol);

    final PdfColor headerColor = PdfColor.fromInt(0xFF62191C);
    final PdfColor accentColor = PdfColor.fromInt(0xFF873632);

    pdf.addPage(
      pw.MultiPage(
        pageTheme: pw.PageTheme(
          pageFormat: PdfPageFormat.a4,
          margin: const pw.EdgeInsets.all(32),
          theme: theme,
        ),
        footer: (context) => pw.Container(
          alignment: pw.Alignment.centerRight,
          margin: const pw.EdgeInsets.only(top: 12),
          child: pw.Row(
            mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
            children: [
              pw.Text(
                'Generated ${_dateLabel(generatedAt)} ${_timeLabel(generatedAt)}',
                style: const pw.TextStyle(
                  fontSize: 8,
                  color: PdfColors.grey600,
                ),
              ),
              pw.Text(
                'Page ${context.pageNumber} of ${context.pagesCount}',
                style: const pw.TextStyle(
                  fontSize: 8,
                  color: PdfColors.grey600,
                ),
              ),
            ],
          ),
        ),
        build: (context) => [
          // ---------- HEADER ----------
          pw.Container(
            width: double.infinity,
            padding: const pw.EdgeInsets.symmetric(
              horizontal: 18,
              vertical: 20,
            ),
            decoration: pw.BoxDecoration(
              color: headerColor,
              borderRadius: pw.BorderRadius.circular(10),
            ),
            child: pw.Column(
              crossAxisAlignment: pw.CrossAxisAlignment.center,
              children: [
                pw.Text(
                  'LUMEAR LOGISTICS',
                  style: pw.TextStyle(
                    color: PdfColors.white,
                    fontSize: 11,
                    fontWeight: pw.FontWeight.bold,
                  ),
                ),
                pw.SizedBox(height: 8),
                pw.Text(
                  'Rider Daily Report',
                  style: pw.TextStyle(
                    color: PdfColors.white,
                    fontSize: 24,
                    fontWeight: pw.FontWeight.bold,
                  ),
                ),
                pw.SizedBox(height: 5),
                pw.Text(
                  'Date: ${_longDateLabel(report.day)}',
                  style: const pw.TextStyle(
                    color: PdfColors.white,
                    fontSize: 10,
                  ),
                ),
                pw.Text(
                  'Rider: $rider',
                  style: const pw.TextStyle(
                    color: PdfColors.white,
                    fontSize: 10,
                  ),
                ),
                if (RiderProfile.riderId.trim().isNotEmpty)
                  pw.Text(
                    'Rider ID: ${RiderProfile.riderId.trim()}',
                    style: const pw.TextStyle(
                      color: PdfColors.white,
                      fontSize: 10,
                    ),
                  ),
              ],
            ),
          ),

          pw.SizedBox(height: 20),

          // ---------- SUMMARY ----------
          pw.Text(
            'Daily Summary',
            style: pw.TextStyle(fontSize: 16, fontWeight: pw.FontWeight.bold),
          ),
          pw.SizedBox(height: 10),
          pw.Table(
            border: pw.TableBorder.all(color: PdfColors.grey300),
            columnWidths: {
              0: const pw.FlexColumnWidth(2),
              1: const pw.FlexColumnWidth(1),
            },
            children: [
              _pdfRow('Successful deliveries', '${report.delivered.length}'),
              _pdfRow('Failed delivery attempts', '${report.failed.length}'),
              _pdfRow('Success rate', _percent(report.successRate)),
              _pdfRow(
                'COD collected on delivered parcels',
                money(report.codCollected),
              ),
              _pdfRow(
                'Rider earnings (${money(ParcelStore.earningPerDelivery)} per delivery)',
                money(report.riderEarnings),
              ),
              _pdfRow(
                'Logistics share (${money(ParcelStore.logisticsSharePerDelivery)} per delivery)',
                money(report.logisticsShare),
              ),
              _pdfRow(
                'Total delivery allocation',
                money(report.riderEarnings + report.logisticsShare),
              ),
            ],
          ),

          pw.SizedBox(height: 22),

          // ---------- SUCCESSFUL ----------
          pw.Text(
            'Successful Deliveries',
            style: pw.TextStyle(fontSize: 16, fontWeight: pw.FontWeight.bold),
          ),
          pw.SizedBox(height: 8),
          if (report.delivered.isEmpty)
            pw.Text(
              'No successful deliveries recorded for this date.',
              style: const pw.TextStyle(fontSize: 10, color: PdfColors.grey700),
            )
          else
            pw.TableHelper.fromTextArray(
              headers: [
                'Time',
                'Parcel',
                'Recipient',
                'Payment',
                'COD',
                'Rider Fee',
              ],
              data: report.delivered
                  .map(
                    (p) => [
                      _timeLabel(p.deliveredAt),
                      p.parcelNumber,
                      p.recipient,
                      p.paymentMethod,
                      money(p.codAmount),
                      money(ParcelStore.earningPerDelivery),
                    ],
                  )
                  .toList(),
              headerStyle: pw.TextStyle(
                fontWeight: pw.FontWeight.bold,
                color: PdfColors.white,
                fontSize: 9,
              ),
              headerDecoration: pw.BoxDecoration(color: accentColor),
              cellStyle: const pw.TextStyle(fontSize: 8),
              cellAlignments: {
                0: pw.Alignment.centerLeft,
                1: pw.Alignment.centerLeft,
                2: pw.Alignment.centerLeft,
                3: pw.Alignment.centerLeft,
                4: pw.Alignment.centerRight,
                5: pw.Alignment.centerRight,
              },
              headerAlignments: {
                4: pw.Alignment.centerRight,
                5: pw.Alignment.centerRight,
              },
              cellPadding: const pw.EdgeInsets.all(6),
              border: pw.TableBorder.all(color: PdfColors.grey300),
            ),

          // ---------- FAILED ----------
          if (report.failed.isNotEmpty) ...[
            pw.SizedBox(height: 22),
            pw.Text(
              'Failed Delivery Attempts',
              style: pw.TextStyle(fontSize: 16, fontWeight: pw.FontWeight.bold),
            ),
            pw.SizedBox(height: 8),
            pw.TableHelper.fromTextArray(
              headers: [
                'Time',
                'Parcel',
                'Recipient',
                'Payment',
                'COD (not collected)',
              ],
              data: report.failed
                  .map(
                    (p) => [
                      _timeLabel(p.statusUpdatedAt),
                      p.parcelNumber,
                      p.recipient,
                      p.paymentMethod,
                      money(p.codAmount),
                    ],
                  )
                  .toList(),
              headerStyle: pw.TextStyle(
                fontWeight: pw.FontWeight.bold,
                color: PdfColors.white,
                fontSize: 9,
              ),
              headerDecoration: pw.BoxDecoration(color: accentColor),
              cellStyle: const pw.TextStyle(fontSize: 8),
              cellAlignments: {
                0: pw.Alignment.centerLeft,
                1: pw.Alignment.centerLeft,
                2: pw.Alignment.centerLeft,
                3: pw.Alignment.centerLeft,
                4: pw.Alignment.centerRight,
              },
              headerAlignments: {4: pw.Alignment.centerRight},
              cellPadding: const pw.EdgeInsets.all(6),
              border: pw.TableBorder.all(color: PdfColors.grey300),
            ),
          ],

          pw.SizedBox(height: 20),

          // ---------- NOTES ----------
          pw.Text(
            'Notes',
            style: pw.TextStyle(fontSize: 13, fontWeight: pw.FontWeight.bold),
          ),
          pw.SizedBox(height: 5),
          pw.Text(
            'COD collected is reported separately from rider earnings. Rider '
            'earnings and logistics share are calculated only for successful '
            'deliveries. Failed attempts do not earn a delivery fee. This '
            'report uses the app\'s current local parcel records.',
            style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700),
          ),

          pw.SizedBox(height: 36),

          // ---------- SIGNATURES ----------
          pw.Row(
            mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              _signatureLine('Rider signature', rider),
              _signatureLine('Received by (Hub / Supervisor)', ''),
            ],
          ),
        ],
      ),
    );

    return pdf;
  }

  pw.Widget _signatureLine(String label, String name) {
    return pw.SizedBox(
      width: 210,
      child: pw.Column(
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          pw.Container(height: 1, color: PdfColors.grey600),
          pw.SizedBox(height: 4),
          pw.Text(label, style: const pw.TextStyle(fontSize: 8)),
          if (name.isNotEmpty)
            pw.Text(
              name,
              style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700),
            ),
        ],
      ),
    );
  }

  pw.TableRow _pdfRow(String label, String value) => pw.TableRow(
    children: [
      pw.Padding(
        padding: const pw.EdgeInsets.all(8),
        child: pw.Text(label, style: const pw.TextStyle(fontSize: 9)),
      ),
      pw.Padding(
        padding: const pw.EdgeInsets.all(8),
        child: pw.Text(
          value,
          textAlign: pw.TextAlign.right,
          style: pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold),
        ),
      ),
    ],
  );

  // ============================================
  // PDF ACTIONS
  // ============================================

  Future<void> _sharePdf() async {
    if (_isBusy) return;
    setState(() => _isSharing = true);

    try {
      final report = _DayReport.forDay(_selectedDate);
      final pdf = await _buildPdf(report);
      final bytes = await pdf.save();

      await Printing.sharePdf(
        bytes: bytes,
        filename: 'lumear_rider_report_${_fileDate(_selectedDate)}.pdf',
      );
    } catch (error) {
      _showError('Could not generate PDF. Please try again.', error);
    } finally {
      if (mounted) setState(() => _isSharing = false);
    }
  }

  Future<void> _previewPdf() async {
    if (_isBusy) return;
    setState(() => _isPreviewing = true);

    try {
      final report = _DayReport.forDay(_selectedDate);
      final pdf = await _buildPdf(report);

      // Clear the spinner before the preview opens.
      if (mounted) setState(() => _isPreviewing = false);

      await Printing.layoutPdf(
        name: 'lumear_rider_report_${_fileDate(_selectedDate)}',
        onLayout: (_) => pdf.save(),
      );
    } catch (error) {
      _showError('Could not open PDF preview. Please try again.', error);
    } finally {
      if (mounted && _isPreviewing) setState(() => _isPreviewing = false);
    }
  }

  void _showError(String message, Object error) {
    debugPrint('Daily report error: $error');
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: danger,
          content: Text(message),
        ),
      );
  }

  // ============================================
  // BUILD
  // ============================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        title: const Text('Daily Rider Report'),
      ),
      body: AnimatedBuilder(
        animation: Listenable.merge([
          ParcelStore.parcels,
          ParcelStore.earnedParcels,
        ]),
        builder: (context, _) {
          final report = _DayReport.forDay(_selectedDate);

          return ListView(
            padding: const EdgeInsets.all(20),
            children: [
              _dateSelector(),

              const SizedBox(height: 14),

              _heroCard(report),

              const SizedBox(height: 18),

              Row(
                children: [
                  Expanded(
                    child: _metric(
                      'Delivered',
                      '${report.delivered.length}',
                      Icons.check_circle_outline,
                      color: success,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _metric(
                      'Failed',
                      '${report.failed.length}',
                      Icons.error_outline,
                      color: report.failed.isEmpty ? darkMaroon : danger,
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 12),

              Row(
                children: [
                  Expanded(
                    child: _metric(
                      'COD collected',
                      _money(report.codCollected),
                      Icons.payments_outlined,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _metric(
                      'Logistics share',
                      _money(report.logisticsShare),
                      Icons.account_balance_outlined,
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 24),

              _sectionTitle(
                _isToday
                    ? 'Successful deliveries today'
                    : 'Successful deliveries',
                report.delivered.length,
              ),

              const SizedBox(height: 10),

              if (report.delivered.isEmpty)
                _emptyCard(
                  _isToday
                      ? 'No successful deliveries recorded today.'
                      : 'No successful deliveries recorded on this date.',
                )
              else
                ...report.delivered.map(
                  (p) => _parcelTile(
                    parcel: p,
                    time: p.deliveredAt,
                    trailingTop: _money(ParcelStore.earningPerDelivery),
                    trailingBottom: p.codAmount > 0
                        ? 'COD ${_money(p.codAmount)}'
                        : p.paymentMethod,
                    accent: darkMaroon,
                    icon: Icons.inventory_2_outlined,
                  ),
                ),

              if (report.failed.isNotEmpty) ...[
                const SizedBox(height: 14),

                _sectionTitle('Failed attempts', report.failed.length),

                const SizedBox(height: 10),

                ...report.failed.map(
                  (p) => _parcelTile(
                    parcel: p,
                    time: p.statusUpdatedAt,
                    trailingTop: 'No fee',
                    trailingBottom: p.codAmount > 0
                        ? 'COD ${_money(p.codAmount)}'
                        : p.paymentMethod,
                    accent: danger,
                    icon: Icons.report_gmailerrorred_rounded,
                  ),
                ),
              ],

              const SizedBox(height: 20),

              SizedBox(
                height: 52,
                child: ElevatedButton.icon(
                  onPressed: _isBusy ? null : _previewPdf,
                  icon: _isPreviewing
                      ? _buttonSpinner(Colors.white)
                      : const Icon(Icons.picture_as_pdf_rounded),
                  label: const Text(
                    'Preview / Print PDF',
                    style: TextStyle(fontWeight: FontWeight.w800),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: darkMaroon,
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: darkMaroon.withValues(alpha: 0.6),
                    disabledForegroundColor: Colors.white,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 10),

              SizedBox(
                height: 52,
                child: OutlinedButton.icon(
                  onPressed: _isBusy ? null : _sharePdf,
                  icon: _isSharing
                      ? _buttonSpinner(darkMaroon)
                      : const Icon(Icons.share_rounded),
                  label: const Text(
                    'Generate & Share PDF',
                    style: TextStyle(fontWeight: FontWeight.w800),
                  ),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: darkMaroon,
                    side: BorderSide(
                      color: _isBusy
                          ? darkMaroon.withValues(alpha: 0.3)
                          : darkMaroon,
                    ),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 14),

              const Text(
                'Report figures are based on this device\'s current '
                'ParcelStore records. They are not yet synced to a server.',
                style: TextStyle(color: muted, fontSize: 11, height: 1.4),
              ),
            ],
          );
        },
      ),
    );
  }

  // ============================================
  // WIDGETS
  // ============================================

  Widget _buttonSpinner(Color color) => SizedBox(
    width: 20,
    height: 20,
    child: CircularProgressIndicator(strokeWidth: 2.2, color: color),
  );

  Widget _dateSelector() {
    final bool canGoNext = !_isToday;

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: darkMaroon.withValues(alpha: 0.06)),
      ),
      child: Row(
        children: [
          IconButton(
            tooltip: 'Previous day',
            onPressed: () => _shiftDay(-1),
            icon: const Icon(Icons.chevron_left_rounded, color: darkMaroon),
          ),
          Expanded(
            child: InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: _pickDate,
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(
                      Icons.calendar_month_rounded,
                      color: darkMaroon,
                      size: 18,
                    ),
                    const SizedBox(width: 8),
                    Flexible(
                      child: Text(
                        _isToday
                            ? 'Today • ${_longDateLabel(_selectedDate)}'
                            : _longDateLabel(_selectedDate),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: darkMaroon,
                          fontWeight: FontWeight.w800,
                          fontSize: 13.5,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          IconButton(
            tooltip: 'Next day',
            onPressed: canGoNext ? () => _shiftDay(1) : null,
            icon: Icon(
              Icons.chevron_right_rounded,
              color: canGoNext ? darkMaroon : muted.withValues(alpha: 0.35),
            ),
          ),
        ],
      ),
    );
  }

  Widget _heroCard(_DayReport report) {
    return Container(
      width: double.infinity,
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
            color: darkMaroon.withValues(alpha: 0.22),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            _isToday ? 'TODAY\'S PERFORMANCE' : 'DAILY PERFORMANCE',
            style: const TextStyle(
              color: Colors.white70,
              fontSize: 11,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.6,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            _dateLabel(_selectedDate),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 21,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 18),
          const Text('Rider earnings', style: TextStyle(color: Colors.white70)),
          Text(
            _money(report.riderEarnings),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 32,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            _deliveriesText(report.delivered.length),
            style: const TextStyle(color: Colors.white70),
          ),

          if (!report.isEmpty) ...[
            const SizedBox(height: 16),
            Row(
              children: [
                Text(
                  'Success rate',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.8),
                    fontSize: 12,
                  ),
                ),
                const Spacer(),
                Text(
                  _percent(report.successRate),
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    fontSize: 12.5,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            ClipRRect(
              borderRadius: BorderRadius.circular(10),
              child: LinearProgressIndicator(
                value: report.successRate,
                minHeight: 6,
                backgroundColor: Colors.white.withValues(alpha: 0.18),
                valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _sectionTitle(String title, int count) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 17,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(
            color: darkMaroon.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Text(
            '$count',
            style: const TextStyle(
              color: darkMaroon,
              fontWeight: FontWeight.w800,
              fontSize: 12,
            ),
          ),
        ),
      ],
    );
  }

  Widget _emptyCard(String message) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          Icon(Icons.inbox_outlined, color: muted.withValues(alpha: 0.7)),
          const SizedBox(width: 12),
          Expanded(
            child: Text(message, style: const TextStyle(color: muted)),
          ),
        ],
      ),
    );
  }

  Widget _parcelTile({
    required ParcelData parcel,
    required DateTime? time,
    required String trailingTop,
    required String trailingBottom,
    required Color accent,
    required IconData icon,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: darkMaroon.withValues(alpha: 0.05)),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: accent, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  parcel.parcelNumber,
                  style: TextStyle(fontWeight: FontWeight.w800, color: accent),
                ),
                const SizedBox(height: 2),
                Text(
                  parcel.recipient,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: muted, fontSize: 12),
                ),
                const SizedBox(height: 2),
                Text(
                  _timeLabel(time),
                  style: TextStyle(
                    color: muted.withValues(alpha: 0.8),
                    fontSize: 10.5,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                trailingTop,
                style: TextStyle(fontWeight: FontWeight.w800, color: accent),
              ),
              const SizedBox(height: 2),
              Text(
                trailingBottom,
                style: const TextStyle(color: muted, fontSize: 10.5),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _metric(
    String label,
    String value,
    IconData icon, {
    Color color = darkMaroon,
  }) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(15),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: darkMaroon.withValues(alpha: 0.06)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: color, size: 22),
        const SizedBox(height: 12),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Text(
            value,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 20,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        const SizedBox(height: 4),
        Text(label, style: const TextStyle(color: muted, fontSize: 11)),
      ],
    ),
  );
}

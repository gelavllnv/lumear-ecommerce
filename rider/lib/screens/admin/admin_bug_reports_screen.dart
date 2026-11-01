import 'dart:io';
import 'dart:math' show min;

import 'package:flutter/material.dart';

import '../../models/bug_report.dart';
import '../../services/bug_report_service.dart';

class AdminBugReportsScreen extends StatefulWidget {
  const AdminBugReportsScreen({super.key});

  @override
  State<AdminBugReportsScreen> createState() => _AdminBugReportsScreenState();
}

class _AdminBugReportsScreenState extends State<AdminBugReportsScreen> {
  // ============================================================
  // DESIGN TOKENS
  // ============================================================

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color cream = Color(0xFFE0CFC2);
  static const Color tabCream = Color(0xFFF3E9E1);
  static const Color background = Color(0xFFF7F3F1);
  static const Color borderColor = Color(0xFFE8DDDA);
  static const Color darkText = Color(0xFF3F2927);
  static const Color mutedText = Color(0xFF8B6F63);

  static const Color pendingColor = Color(0xFF873632);
  static const Color reviewColor = Color(0xFFB7791F);
  static const Color resolvedColor = Color(0xFF2E7D32);

  static const double _tabsHeight = 66;

  // Room for the floating nav bar at the bottom of the dashboard.
  static const double _navClearance = 130;

  // (status value, tab label)
  static const List<(String, String)> _tabs = [
    ('All', 'All'),
    ('Pending', 'Pending'),
    ('Under Review', 'In Review'),
    ('Resolved', 'Resolved'),
  ];

  final BugReportService _service = BugReportService.instance;

  String _filter = 'All';

  // ============================================================
  // HELPERS
  // ============================================================

  Color _statusColor(String status) {
    switch (status) {
      case 'Resolved':
        return resolvedColor;
      case 'Under Review':
        return reviewColor;
      default:
        return pendingColor;
    }
  }

  String _responseOf(BugReport report) =>
      (report.adminResponse as String?) ?? '';

  String _initialOf(String name) {
    final trimmed = name.trim();
    return trimmed.isEmpty ? '?' : trimmed.substring(0, 1).toUpperCase();
  }

  String _timeAgo(DateTime date) {
    final diff = DateTime.now().difference(date);

    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes} min ago';
    if (diff.inHours < 24) return '${diff.inHours} hr ago';
    if (diff.inDays < 7) return '${diff.inDays}d ago';

    return _formatDate(date);
  }

  String _formatDate(DateTime date) {
    return '${date.month}/${date.day}/${date.year}';
  }

  String _formatDateTime(DateTime date) {
    int hour = date.hour;
    final String period = hour >= 12 ? 'PM' : 'AM';

    if (hour == 0) {
      hour = 12;
    } else if (hour > 12) {
      hour -= 12;
    }

    final String minute = date.minute.toString().padLeft(2, '0');

    return '${_formatDate(date)} $hour:$minute $period';
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _service,
      builder: (context, _) {
        final List<BugReport> all = _service.reports.toList();

        int countOf(String status) =>
            all.where((report) => report.status == status).length;

        final int pending = countOf('Pending');
        final int inReview = countOf('Under Review');
        final int resolved = countOf('Resolved');

        final Map<String, int> counts = {
          'All': all.length,
          'Pending': pending,
          'Under Review': inReview,
          'Resolved': resolved,
        };

        final List<BugReport> reports =
            all.where((r) => _filter == 'All' || r.status == _filter).toList()
              ..sort((a, b) => b.reportedAt.compareTo(a.reportedAt));

        return Container(
          color: background,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _buildSummary(
                  open: pending + inReview,
                  total: all.length,
                  resolved: resolved,
                ),
                const SizedBox(height: 14),
                Expanded(
                  child: Stack(
                    clipBehavior: Clip.none,
                    children: [
                      Positioned.fill(
                        child: reports.isEmpty
                            ? _emptyState()
                            : ListView.separated(
                                padding: const EdgeInsets.only(
                                  top: _tabsHeight + 4,
                                  bottom: _navClearance,
                                ),
                                itemCount: reports.length,
                                separatorBuilder: (_, __) =>
                                    const SizedBox(height: 12),
                                itemBuilder: (context, index) {
                                  return _reportCard(reports[index]);
                                },
                              ),
                      ),
                      Positioned(
                        top: 0,
                        left: 0,
                        right: 0,
                        child: _buildFloatingTabs(counts),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  // ============================================================
  // SUMMARY HEADER
  // ============================================================

  Widget _buildSummary({
    required int open,
    required int total,
    required int resolved,
  }) {
    final int rate = total == 0 ? 0 : ((resolved / total) * 100).round();

    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                open == 0
                    ? 'All caught up'
                    : '$open open ${open == 1 ? 'report' : 'reports'}',
                style: const TextStyle(
                  color: darkMaroon,
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 3),
              const Text(
                'Review and respond to issues reported by riders.',
                style: TextStyle(color: mutedText, fontSize: 12),
              ),
            ],
          ),
        ),
        if (total > 0) ...[
          const SizedBox(width: 12),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
            decoration: BoxDecoration(
              color: resolvedColor.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              children: [
                Text(
                  '$rate%',
                  style: const TextStyle(
                    color: resolvedColor,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                    height: 1.1,
                  ),
                ),
                const SizedBox(height: 1),
                const Text(
                  'Resolved',
                  style: TextStyle(
                    color: resolvedColor,
                    fontSize: 9.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  // ============================================================
  // FLOATING TABS
  // ============================================================

  Widget _buildFloatingTabs(Map<String, int> counts) {
    return Container(
      height: 54,
      padding: const EdgeInsets.all(6),
      decoration: BoxDecoration(
        color: darkMaroon,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: Colors.white.withValues(alpha: 0.28)),
        boxShadow: [
          BoxShadow(
            color: darkMaroon.withValues(alpha: 0.32),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.10),
            blurRadius: 5,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: [
          for (int i = 0; i < _tabs.length; i++) ...[
            if (i > 0) const SizedBox(width: 4),
            _tabButton(_tabs[i].$1, _tabs[i].$2, counts[_tabs[i].$1] ?? 0),
          ],
        ],
      ),
    );
  }

  Widget _tabButton(String value, String label, int count) {
    final bool selected = _filter == value;
    final Color fg = selected
        ? darkMaroon
        : Colors.white.withValues(alpha: 0.85);

    return Expanded(
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () => setState(() => _filter = value),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOut,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? tabCream : Colors.transparent,
            borderRadius: BorderRadius.circular(14),
          ),
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  label,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: fg,
                  ),
                ),
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 6,
                    vertical: 1,
                  ),
                  decoration: BoxDecoration(
                    color: selected
                        ? darkMaroon.withValues(alpha: 0.12)
                        : Colors.white.withValues(alpha: 0.2),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    '$count',
                    style: TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w800,
                      color: fg,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // EMPTY STATE
  // ============================================================

  Widget _emptyState() {
    final bool filtered = _filter != 'All';

    return Padding(
      padding: const EdgeInsets.only(top: _tabsHeight, bottom: 110),
      child: Center(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  color: cream.withValues(alpha: 0.6),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.bug_report_outlined,
                  color: darkMaroon,
                  size: 36,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                filtered ? 'Nothing here' : 'No Bug Reports',
                style: const TextStyle(
                  color: darkMaroon,
                  fontSize: 17,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                filtered
                    ? 'There are no reports with this status.'
                    : 'Bug reports submitted by riders will appear here.',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: mutedText,
                  fontSize: 12.5,
                  height: 1.4,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // REPORT CARD
  // ============================================================

  Widget _reportCard(BugReport report) {
    final Color color = _statusColor(report.status);
    final bool hasResponse = _responseOf(report).trim().isNotEmpty;
    final int evidenceCount = report.evidencePaths.length;

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: darkMaroon.withValues(alpha: 0.06),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Material(
        color: Colors.white,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: const BorderSide(color: borderColor),
        ),
        child: InkWell(
          onTap: () => _openReport(report),
          child: IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Container(width: 5, color: color),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            CircleAvatar(
                              radius: 19,
                              backgroundColor: cream,
                              child: Text(
                                _initialOf(report.riderName),
                                style: const TextStyle(
                                  color: darkMaroon,
                                  fontSize: 14,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            const SizedBox(width: 11),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    report.riderName,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: darkMaroon,
                                      fontSize: 13.5,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    '${report.id}  \u2022  '
                                    '${_timeAgo(report.reportedAt)}',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: mutedText,
                                      fontSize: 10.5,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 8),
                            _statusChip(report.status),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Text(
                          report.description,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: darkText,
                            fontSize: 12.5,
                            height: 1.45,
                          ),
                        ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            _metaChip(
                              Icons.smartphone_rounded,
                              report.affectedScreen,
                            ),
                            if (evidenceCount > 0)
                              _metaChip(
                                Icons.image_outlined,
                                '$evidenceCount '
                                '${evidenceCount == 1 ? 'photo' : 'photos'}',
                              ),
                            if (hasResponse)
                              _metaChip(
                                Icons.reply_rounded,
                                'Responded',
                                color: resolvedColor,
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _statusChip(String status) {
    final Color color = _statusColor(status);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Text(
            status,
            style: TextStyle(
              color: color,
              fontSize: 10,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  Widget _metaChip(IconData icon, String text, {Color color = mutedText}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: color == mutedText ? background : color.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: color),
          const SizedBox(width: 5),
          ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 170),
            child: Text(
              text,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: color,
                fontSize: 10.5,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // REPORT DETAILS SHEET
  // ============================================================

  void _openReport(BugReport report) {
    final ScaffoldMessengerState messenger = ScaffoldMessenger.of(context);

    final TextEditingController responseController = TextEditingController(
      text: _responseOf(report),
    );

    String selectedStatus = report.status;

    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            final MediaQueryData media = MediaQuery.of(context);
            final double keyboard = media.viewInsets.bottom;

            final double available =
                media.size.height - keyboard - media.padding.top - 8;
            final double height = min(media.size.height * 0.92, available);

            return Padding(
              padding: EdgeInsets.only(bottom: keyboard),
              child: Container(
                height: height,
                decoration: const BoxDecoration(
                  color: background,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                ),
                child: Column(
                  children: [
                    Container(
                      width: 42,
                      height: 4,
                      margin: const EdgeInsets.only(top: 10, bottom: 6),
                      decoration: BoxDecoration(
                        color: Colors.grey.shade300,
                        borderRadius: BorderRadius.circular(20),
                      ),
                    ),

                    // Header
                    Padding(
                      padding: const EdgeInsets.fromLTRB(18, 8, 8, 14),
                      child: Row(
                        children: [
                          Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                              color: cream.withValues(alpha: 0.7),
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: const Icon(
                              Icons.bug_report_rounded,
                              color: darkMaroon,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  report.id,
                                  style: const TextStyle(
                                    color: darkMaroon,
                                    fontSize: 17,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(height: 3),
                                _statusChip(report.status),
                              ],
                            ),
                          ),
                          IconButton(
                            onPressed: () => Navigator.pop(sheetContext),
                            icon: const Icon(Icons.close_rounded),
                          ),
                        ],
                      ),
                    ),

                    const Divider(height: 1, color: borderColor),

                    Expanded(
                      child: ListView(
                        padding: const EdgeInsets.all(18),
                        children: [
                          _detailsSection('Rider Information', [
                            _detailRow('Rider ID', report.riderId),
                            _detailRow('Name', report.riderName),
                            _detailRow('Email', report.riderEmail),
                          ]),

                          const SizedBox(height: 18),

                          _detailsSection('System Information', [
                            _detailRow('Screen', report.affectedScreen),
                            _detailRow('App Version', report.appVersion),
                            _detailRow('Device', report.deviceInfo),
                            _detailRow(
                              'Reported',
                              _formatDateTime(report.reportedAt),
                            ),
                          ]),

                          const SizedBox(height: 18),

                          _textSection(
                            title: 'What happened?',
                            text: report.description,
                          ),

                          const SizedBox(height: 14),

                          _textSection(
                            title: 'Expected result',
                            text: report.expectedResult,
                          ),

                          const SizedBox(height: 18),

                          _sectionTitle('Evidence'),
                          const SizedBox(height: 9),
                          _buildEvidence(report.evidencePaths),

                          const SizedBox(height: 22),

                          _sectionTitle('Report Status'),
                          const SizedBox(height: 9),
                          _statusSelector(selectedStatus, (value) {
                            setModalState(() => selectedStatus = value);
                          }),

                          const SizedBox(height: 18),

                          _sectionTitle('Admin Response'),
                          const SizedBox(height: 9),
                          TextField(
                            controller: responseController,
                            minLines: 3,
                            maxLines: 5,
                            style: const TextStyle(fontSize: 12.5),
                            decoration: InputDecoration(
                              hintText:
                                  'Add notes or a response for the rider...',
                              hintStyle: const TextStyle(
                                fontSize: 12,
                                color: Colors.black38,
                              ),
                              filled: true,
                              fillColor: Colors.white,
                              contentPadding: const EdgeInsets.all(14),
                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(14),
                                borderSide: const BorderSide(
                                  color: borderColor,
                                ),
                              ),
                              focusedBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(14),
                                borderSide: const BorderSide(
                                  color: maroon,
                                  width: 1.6,
                                ),
                              ),
                            ),
                          ),

                          const SizedBox(height: 12),
                        ],
                      ),
                    ),

                    // Sticky save bar
                    Container(
                      padding: EdgeInsets.fromLTRB(
                        18,
                        12,
                        18,
                        12 + (keyboard > 0 ? 0 : media.padding.bottom),
                      ),
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        border: Border(top: BorderSide(color: borderColor)),
                      ),
                      child: SizedBox(
                        width: double.infinity,
                        height: 50,
                        child: ElevatedButton.icon(
                          onPressed: () {
                            _service.updateStatus(
                              reportId: report.id,
                              status: selectedStatus,
                            );

                            _service.updateAdminResponse(
                              reportId: report.id,
                              response: responseController.text,
                            );

                            Navigator.pop(sheetContext);

                            messenger.showSnackBar(
                              SnackBar(
                                behavior: SnackBarBehavior.floating,
                                margin: const EdgeInsets.fromLTRB(
                                  16,
                                  0,
                                  16,
                                  110,
                                ),
                                content: Text(
                                  '${report.id} updated successfully.',
                                ),
                              ),
                            );
                          },
                          icon: const Icon(Icons.check_rounded, size: 20),
                          label: const Text(
                            'Save Changes',
                            style: TextStyle(fontWeight: FontWeight.w800),
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
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    ).whenComplete(responseController.dispose);
  }

  Widget _sectionTitle(String title) {
    return Text(
      title,
      style: const TextStyle(
        color: darkMaroon,
        fontSize: 13,
        fontWeight: FontWeight.w800,
      ),
    );
  }

  Widget _statusSelector(String selected, ValueChanged<String> onChanged) {
    const List<String> statuses = ['Pending', 'Under Review', 'Resolved'];

    return Row(
      children: [
        for (int i = 0; i < statuses.length; i++) ...[
          if (i > 0) const SizedBox(width: 8),
          Expanded(
            child: _statusOption(
              statuses[i],
              selected == statuses[i],
              () => onChanged(statuses[i]),
            ),
          ),
        ],
      ],
    );
  }

  Widget _statusOption(String status, bool selected, VoidCallback onTap) {
    final Color color = _statusColor(status);

    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(vertical: 13, horizontal: 6),
        decoration: BoxDecoration(
          color: selected ? color.withValues(alpha: 0.10) : Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? color : borderColor,
            width: selected ? 1.6 : 1,
          ),
        ),
        child: FittedBox(
          fit: BoxFit.scaleDown,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 7,
                height: 7,
                decoration: BoxDecoration(
                  color: selected ? color : Colors.black26,
                  shape: BoxShape.circle,
                ),
              ),
              const SizedBox(width: 6),
              Text(
                status,
                style: TextStyle(
                  color: selected ? color : mutedText,
                  fontSize: 11.5,
                  fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildEvidence(List<String> paths) {
    if (paths.isEmpty) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(vertical: 22),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: borderColor),
        ),
        child: const Column(
          children: [
            Icon(Icons.image_not_supported_outlined, color: brownBeige),
            SizedBox(height: 6),
            Text(
              'No evidence attached.',
              style: TextStyle(color: mutedText, fontSize: 12),
            ),
          ],
        ),
      );
    }

    return SizedBox(
      height: 150,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: paths.length,
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemBuilder: (context, index) {
          return GestureDetector(
            onTap: () => _viewEvidence(paths[index]),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: Image.file(
                File(paths[index]),
                width: 180,
                height: 150,
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) => Container(
                  width: 180,
                  height: 150,
                  color: cream.withValues(alpha: 0.5),
                  child: const Icon(
                    Icons.broken_image_outlined,
                    color: brownBeige,
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  // Full-screen, pinch-to-zoom evidence viewer.
  void _viewEvidence(String path) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) {
        return Dialog.fullscreen(
          backgroundColor: Colors.black,
          child: Stack(
            children: [
              Positioned.fill(
                child: InteractiveViewer(
                  minScale: 1,
                  maxScale: 5,
                  child: Center(
                    child: Image.file(
                      File(path),
                      fit: BoxFit.contain,
                      errorBuilder: (context, error, stackTrace) => const Icon(
                        Icons.broken_image_outlined,
                        color: Colors.white54,
                        size: 48,
                      ),
                    ),
                  ),
                ),
              ),
              Positioned(
                top: 0,
                right: 0,
                child: SafeArea(
                  child: IconButton(
                    onPressed: () => Navigator.pop(dialogContext),
                    icon: const Icon(Icons.close_rounded, color: Colors.white),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _detailsSection(String title, List<Widget> children) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _sectionTitle(title),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: borderColor),
          ),
          child: Column(
            children: [
              for (int i = 0; i < children.length; i++) ...[
                children[i],
                if (i != children.length - 1)
                  const Divider(height: 1, color: Color(0xFFF0E8E4)),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _detailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 11),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 95,
            child: Text(
              label,
              style: const TextStyle(color: mutedText, fontSize: 11),
            ),
          ),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(
                color: darkText,
                fontSize: 11.5,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _textSection({required String title, required String text}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _sectionTitle(title),
        const SizedBox(height: 8),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: borderColor),
          ),
          child: Text(
            text,
            style: const TextStyle(
              color: darkText,
              fontSize: 12.5,
              height: 1.5,
            ),
          ),
        ),
      ],
    );
  }
}

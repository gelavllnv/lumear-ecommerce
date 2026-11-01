import 'package:flutter/material.dart';

import '../../models/van_pickup_request.dart';
import 'van_pickup_details_screen.dart';
import 'van_pickup_widgets.dart';

// ============================================================
// DESIGN SYSTEM — same palette and tokens as the Parcels screen
// ============================================================

const Color _darkMaroon = Color(0xFF62191C);
const Color _maroon = Color(0xFF873632);
const Color _brownBeige = Color(0xFF9E7161);
const Color _lightBeige = Color(0xFFCAAE9F);
const Color _cream = Color(0xFFF3E9E1);
const Color _surface = Color(0xFFFFFFFF);
const Color _textMuted = Color(0xFF8B6F63);
const Color _success = Color(0xFF2E7D32);
const Color _danger = Color(0xFFB3261E);

const double _radiusSm = 14;
const double _radiusMd = 18;
const double _radiusLg = 22;

final List<BoxShadow> _shadowSoft = [
  BoxShadow(
    color: _darkMaroon.withValues(alpha: 0.06),
    blurRadius: 18,
    offset: const Offset(0, 6),
  ),
];

final BoxDecoration _cardDecoration = BoxDecoration(
  color: _surface,
  borderRadius: BorderRadius.circular(_radiusLg),
  border: Border.all(color: _darkMaroon.withValues(alpha: 0.06)),
  boxShadow: _shadowSoft,
);

enum _PickupTab { active, successful, failed }

class VanSellerPickupsScreen extends StatefulWidget {
  final List<VanPickupRequest> pickups;
  final VoidCallback onChanged;

  const VanSellerPickupsScreen({
    super.key,
    required this.pickups,
    required this.onChanged,
  });

  @override
  State<VanSellerPickupsScreen> createState() => _VanSellerPickupsScreenState();
}

class _VanSellerPickupsScreenState extends State<VanSellerPickupsScreen> {
  static const double _tabsHeight = 66;

  static const EdgeInsets _listPadding = EdgeInsets.only(
    top: _tabsHeight + 4,
    bottom: 130, // room for the floating nav bar
  );

  _PickupTab _tab = _PickupTab.active;

  // Date used by the Successful and Failed tabs. null = all dates.
  DateTime? _selectedDate = vanDateOnly(DateTime.now());

  // ============================================================
  // Actions
  // ============================================================

  Future<void> _openDetails(VanPickupRequest pickup) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) =>
            VanPickupDetailsScreen(pickup: pickup, onChanged: widget.onChanged),
      ),
    );

    widget.onChanged();
    if (mounted) setState(() {});
  }

  Future<void> _openHistory() async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => VanPickupHistoryScreen(
          pickups: widget.pickups,
          onChanged: widget.onChanged,
        ),
      ),
    );

    if (mounted) setState(() {});
  }

  Future<void> _pickDate() async {
    final today = vanDateOnly(DateTime.now());

    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate ?? today,
      firstDate: DateTime(today.year - 1),
      lastDate: today,
      helpText: 'Select pickup date',
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: _darkMaroon,
              onPrimary: Colors.white,
              surface: _cream,
              onSurface: _darkMaroon,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked == null || !mounted) return;

    setState(() => _selectedDate = vanDateOnly(picked));
  }

  // Newest first; pickups without a timestamp go last.
  int _newestFirst(DateTime? a, DateTime? b) {
    if (a == null && b == null) return 0;
    if (a == null) return 1;
    if (b == null) return -1;
    return b.compareTo(a);
  }

  // ============================================================
  // Build
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final pickups = widget.pickups;

    final active = pickups
        .where(
          (pickup) =>
              pickup.status == VanPickupStatus.assigned ||
              pickup.status == VanPickupStatus.inProgress ||
              pickup.status == VanPickupStatus.arrived,
        )
        .toList();

    final successful =
        pickups
            .where(
              (pickup) =>
                  pickup.status == VanPickupStatus.handedOver &&
                  vanIsSameDay(pickup.handedOverAt, _selectedDate),
            )
            .toList()
          ..sort((a, b) => _newestFirst(a.handedOverAt, b.handedOverAt));

    final failed =
        pickups
            .where(
              (pickup) =>
                  pickup.status == VanPickupStatus.failed &&
                  vanIsSameDay(pickup.failedAt, _selectedDate),
            )
            .toList()
          ..sort((a, b) => _newestFirst(a.failedAt, b.failedAt));

    return Scaffold(
      backgroundColor: _cream,
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Seller Pickups',
                      style: TextStyle(
                        fontSize: 27,
                        fontWeight: FontWeight.w800,
                        color: _darkMaroon,
                        letterSpacing: -0.4,
                      ),
                    ),
                  ),
                  Tooltip(
                    message: 'History',
                    child: InkWell(
                      borderRadius: BorderRadius.circular(_radiusSm),
                      onTap: _openHistory,
                      child: Container(
                        width: 42,
                        height: 42,
                        decoration: BoxDecoration(
                          color: _surface,
                          borderRadius: BorderRadius.circular(_radiusSm),
                          border: Border.all(
                            color: _darkMaroon.withValues(alpha: 0.08),
                          ),
                          boxShadow: _shadowSoft,
                        ),
                        child: const Icon(
                          Icons.history_rounded,
                          color: _darkMaroon,
                          size: 22,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              const Text(
                'Track active, successful, and failed seller pickups.',
                style: TextStyle(fontSize: 12.5, color: _textMuted),
              ),
              const SizedBox(height: 14),
              Expanded(
                child: Stack(
                  clipBehavior: Clip.none,
                  children: [
                    Positioned.fill(
                      child: switch (_tab) {
                        _PickupTab.active => _buildActiveTab(active),
                        _PickupTab.successful => _buildSuccessfulTab(
                          successful,
                        ),
                        _PickupTab.failed => _buildFailedTab(failed),
                      },
                    ),
                    Positioned(
                      top: 0,
                      left: 0,
                      right: 0,
                      child: _buildFloatingTabs(
                        active.length,
                        successful.length,
                        failed.length,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // Floating tabs (Active / Successful / Failed)
  // ============================================================

  Widget _buildFloatingTabs(int active, int successful, int failed) {
    return Container(
      height: 54,
      padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 6),
      decoration: BoxDecoration(
        color: _darkMaroon,
        borderRadius: BorderRadius.circular(_radiusLg),
        border: Border.all(color: Colors.white.withValues(alpha: 0.28)),
        boxShadow: [
          BoxShadow(
            color: _darkMaroon.withValues(alpha: 0.32),
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
          _tabButton(
            _PickupTab.active,
            'Active',
            Icons.local_shipping_outlined,
            active,
          ),
          const SizedBox(width: 6),
          _tabButton(
            _PickupTab.successful,
            'Successful',
            Icons.check_circle_outline_rounded,
            successful,
          ),
          const SizedBox(width: 6),
          _tabButton(
            _PickupTab.failed,
            'Failed',
            Icons.cancel_outlined,
            failed,
          ),
        ],
      ),
    );
  }

  Widget _tabButton(_PickupTab tab, String label, IconData icon, int count) {
    final bool selected = _tab == tab;
    final Color fg = selected
        ? _darkMaroon
        : Colors.white.withValues(alpha: 0.85);

    return Expanded(
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () => setState(() => _tab = tab),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOut,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? _cream : Colors.transparent,
            borderRadius: BorderRadius.circular(_radiusSm),
          ),
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(icon, size: 15, color: fg),
                const SizedBox(width: 6),
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
                        ? _darkMaroon.withValues(alpha: 0.12)
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
  // Active tab
  // ============================================================

  Widget _buildActiveTab(List<VanPickupRequest> active) {
    if (active.isEmpty) {
      return _emptyState(
        icon: Icons.inventory_2_outlined,
        title: 'No Active Pickups',
        message: 'New pickup requests will appear here.',
      );
    }

    return RefreshIndicator(
      edgeOffset: _tabsHeight,
      onRefresh: () async {
        widget.onChanged();
      },
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(
          parent: BouncingScrollPhysics(),
        ),
        padding: _listPadding,
        children: active
            .map(
              (pickup) => VanPickupCard(
                pickup: pickup,
                onTap: () => _openDetails(pickup),
              ),
            )
            .toList(),
      ),
    );
  }

  // ============================================================
  // Successful tab
  // ============================================================

  Widget _buildSuccessfulTab(List<VanPickupRequest> successful) {
    final int parcels = successful.fold<int>(
      0,
      (sum, p) => sum + p.parcelCount,
    );

    return _buildDateTab(
      count:
          '${successful.length} successful '
          '${successful.length == 1 ? 'pickup' : 'pickups'} '
          '\u00B7 $parcels ${parcels == 1 ? 'parcel' : 'parcels'} '
          '(${vanDayLabel(_selectedDate)})',
      isEmpty: successful.isEmpty,
      emptyIcon: Icons.check_circle_outline_rounded,
      emptyMessage: 'No successful pickups\nfor this date.',
      itemCount: successful.length,
      itemBuilder: (index) => _SuccessfulPickupCard(
        pickup: successful[index],
        onTap: () => _openDetails(successful[index]),
      ),
    );
  }

  // ============================================================
  // Failed tab
  // ============================================================

  Widget _buildFailedTab(List<VanPickupRequest> failed) {
    return _buildDateTab(
      count:
          '${failed.length} failed '
          '${failed.length == 1 ? 'pickup' : 'pickups'} '
          '(${vanDayLabel(_selectedDate)})',
      isEmpty: failed.isEmpty,
      emptyIcon: Icons.cancel_outlined,
      emptyMessage: 'No failed pickups\nfor this date.',
      itemCount: failed.length,
      itemBuilder: (index) => _FailedPickupCard(
        pickup: failed[index],
        onTap: () => _openDetails(failed[index]),
      ),
    );
  }

  // ============================================================
  // Shared layout for tabs that are filtered by date
  // ============================================================

  Widget _buildDateTab({
    required String count,
    required bool isEmpty,
    required IconData emptyIcon,
    required String emptyMessage,
    required int itemCount,
    required Widget Function(int index) itemBuilder,
  }) {
    return Padding(
      padding: const EdgeInsets.only(top: _tabsHeight + 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildDateCard(),
          const SizedBox(height: 18),
          Text(
            count,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: _darkMaroon,
              letterSpacing: -0.2,
            ),
          ),
          const SizedBox(height: 12),
          Expanded(
            child: isEmpty
                ? Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 72,
                          height: 72,
                          decoration: BoxDecoration(
                            color: _lightBeige.withValues(alpha: 0.4),
                            shape: BoxShape.circle,
                          ),
                          child: Icon(emptyIcon, size: 30, color: _darkMaroon),
                        ),
                        const SizedBox(height: 14),
                        Text(
                          emptyMessage,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: _textMuted,
                            fontSize: 12.5,
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  )
                : RefreshIndicator(
                    onRefresh: () async {
                      widget.onChanged();
                    },
                    child: ListView.separated(
                      physics: const AlwaysScrollableScrollPhysics(
                        parent: BouncingScrollPhysics(),
                      ),
                      padding: const EdgeInsets.only(bottom: 130),
                      itemCount: itemCount,
                      separatorBuilder: (_, __) => const SizedBox(height: 14),
                      itemBuilder: (context, index) => itemBuilder(index),
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  // Tap to open the calendar. "All dates" clears the filter.
  Widget _buildDateCard() {
    final selected = _selectedDate;

    return InkWell(
      borderRadius: BorderRadius.circular(_radiusMd),
      onTap: _pickDate,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: _cardDecoration,
        child: Row(
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                color: _lightBeige.withValues(alpha: 0.45),
                borderRadius: BorderRadius.circular(14),
              ),
              child: const Icon(
                Icons.calendar_month_rounded,
                color: _darkMaroon,
                size: 21,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Select Pickup Date',
                    style: TextStyle(
                      fontSize: 10,
                      color: _textMuted,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    vanDayLabel(selected, withDate: true),
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: _darkMaroon,
                    ),
                  ),
                ],
              ),
            ),
            if (selected != null) ...[
              GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () => setState(() => _selectedDate = null),
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: _maroon.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: const Text(
                    'All dates',
                    style: TextStyle(
                      color: _maroon,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
            ],
            Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: _cream,
                borderRadius: BorderRadius.circular(9),
              ),
              child: const Icon(
                Icons.keyboard_arrow_down_rounded,
                color: _darkMaroon,
                size: 20,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _emptyState({
    required IconData icon,
    required String title,
    required String message,
  }) {
    return Padding(
      padding: const EdgeInsets.only(top: _tabsHeight, bottom: 100),
      child: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 84,
              height: 84,
              decoration: BoxDecoration(
                color: _lightBeige.withValues(alpha: 0.4),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 36, color: _darkMaroon),
            ),
            const SizedBox(height: 18),
            Text(
              title,
              style: const TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: _darkMaroon,
              ),
            ),
            const SizedBox(height: 6),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 34),
              child: Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 12.5,
                  color: _textMuted,
                  height: 1.4,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ============================================================
// History screen
// Every pickup the van rider already finished (handed over or failed),
// newest first. Pick a date to see only what was finished on that day.
// ============================================================

enum _HistoryFilter { all, successful, failed }

int _compareNewest(DateTime? a, DateTime? b) {
  if (a == null && b == null) return 0;
  if (a == null) return 1;
  if (b == null) return -1;
  return b.compareTo(a);
}

class VanPickupHistoryScreen extends StatefulWidget {
  final List<VanPickupRequest> pickups;
  final VoidCallback onChanged;

  const VanPickupHistoryScreen({
    super.key,
    required this.pickups,
    required this.onChanged,
  });

  @override
  State<VanPickupHistoryScreen> createState() => _VanPickupHistoryScreenState();
}

class _VanPickupHistoryScreenState extends State<VanPickupHistoryScreen> {
  _HistoryFilter _filter = _HistoryFilter.all;

  // Date filter for the history. null = all dates.
  DateTime? _selectedDate;

  // The time the rider finished the pickup: handed over or failed.
  DateTime? _timestamp(VanPickupRequest p) =>
      p.status == VanPickupStatus.handedOver ? p.handedOverAt : p.failedAt;

  Future<void> _pickDate() async {
    final today = vanDateOnly(DateTime.now());

    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate ?? today,
      firstDate: DateTime(today.year - 1),
      lastDate: today,
      helpText: 'Select history date',
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: _darkMaroon,
              onPrimary: Colors.white,
              surface: _cream,
              onSurface: _darkMaroon,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked == null || !mounted) return;

    setState(() => _selectedDate = vanDateOnly(picked));
  }

  Future<void> _openDetails(VanPickupRequest pickup) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) =>
            VanPickupDetailsScreen(pickup: pickup, onChanged: widget.onChanged),
      ),
    );

    widget.onChanged();
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    // Only finished pickups, and only the ones on the selected date.
    final inDate = widget.pickups
        .where(
          (p) =>
              (p.status == VanPickupStatus.handedOver ||
                  p.status == VanPickupStatus.failed) &&
              vanIsSameDay(_timestamp(p), _selectedDate),
        )
        .toList();

    final successful = inDate
        .where((p) => p.status == VanPickupStatus.handedOver)
        .toList();
    final failed = inDate
        .where((p) => p.status == VanPickupStatus.failed)
        .toList();

    final shown = List<VanPickupRequest>.of(switch (_filter) {
      _HistoryFilter.all => inDate,
      _HistoryFilter.successful => successful,
      _HistoryFilter.failed => failed,
    })..sort((a, b) => _compareNewest(_timestamp(a), _timestamp(b)));

    return Scaffold(
      backgroundColor: _cream,
      appBar: AppBar(
        backgroundColor: _darkMaroon,
        foregroundColor: Colors.white,
        title: const Text(
          'Pickup History',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
            child: _buildDateCard(),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  _filterChip(_HistoryFilter.all, 'All', inDate.length),
                  const SizedBox(width: 8),
                  _filterChip(
                    _HistoryFilter.successful,
                    'Successful',
                    successful.length,
                  ),
                  const SizedBox(width: 8),
                  _filterChip(_HistoryFilter.failed, 'Failed', failed.length),
                ],
              ),
            ),
          ),
          const SizedBox(height: 14),
          Expanded(
            child: shown.isEmpty
                ? _emptyHistory()
                : RefreshIndicator(
                    onRefresh: () async => widget.onChanged(),
                    child: ListView(
                      physics: const AlwaysScrollableScrollPhysics(
                        parent: BouncingScrollPhysics(),
                      ),
                      padding: const EdgeInsets.fromLTRB(20, 0, 20, 40),
                      children: _buildGroupedList(shown),
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  // Tap to open the calendar. "All dates" clears the filter.
  Widget _buildDateCard() {
    final selected = _selectedDate;

    return InkWell(
      borderRadius: BorderRadius.circular(_radiusMd),
      onTap: _pickDate,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        decoration: _cardDecoration,
        child: Row(
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                color: _lightBeige.withValues(alpha: 0.45),
                borderRadius: BorderRadius.circular(14),
              ),
              child: const Icon(
                Icons.calendar_month_rounded,
                color: _darkMaroon,
                size: 21,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Select History Date',
                    style: TextStyle(
                      fontSize: 10,
                      color: _textMuted,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    vanDayLabel(selected, withDate: true),
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: _darkMaroon,
                    ),
                  ),
                ],
              ),
            ),
            if (selected != null) ...[
              GestureDetector(
                behavior: HitTestBehavior.opaque,
                onTap: () => setState(() => _selectedDate = null),
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: _maroon.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: const Text(
                    'All dates',
                    style: TextStyle(
                      color: _maroon,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
            ],
            Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: _cream,
                borderRadius: BorderRadius.circular(9),
              ),
              child: const Icon(
                Icons.keyboard_arrow_down_rounded,
                color: _darkMaroon,
                size: 20,
              ),
            ),
          ],
        ),
      ),
    );
  }

  List<Widget> _buildGroupedList(List<VanPickupRequest> shown) {
    final widgets = <Widget>[];
    DateTime? lastDay;
    bool first = true;

    for (final pickup in shown) {
      final ts = _timestamp(pickup);
      final day = ts == null ? null : vanDateOnly(ts);

      // With a date selected, the date card already says which day it is,
      // so the per-day heading is only needed when showing all dates.
      if (_selectedDate == null && (first || day != lastDay)) {
        widgets.add(
          Padding(
            padding: EdgeInsets.only(top: first ? 0 : 8, bottom: 12),
            child: Text(
              day == null ? 'No date' : vanDayLabel(day, withDate: true),
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: _textMuted,
                letterSpacing: 0.2,
              ),
            ),
          ),
        );
        lastDay = day;
        first = false;
      }

      widgets.add(
        Padding(
          padding: const EdgeInsets.only(bottom: 14),
          child: pickup.status == VanPickupStatus.handedOver
              ? _SuccessfulPickupCard(
                  pickup: pickup,
                  onTap: () => _openDetails(pickup),
                )
              : _FailedPickupCard(
                  pickup: pickup,
                  onTap: () => _openDetails(pickup),
                ),
        ),
      );
    }

    return widgets;
  }

  Widget _filterChip(_HistoryFilter value, String label, int count) {
    final selected = _filter == value;

    return GestureDetector(
      onTap: () => setState(() => _filter = value),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
        decoration: BoxDecoration(
          color: selected ? _darkMaroon : _surface,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: _darkMaroon.withValues(alpha: 0.12)),
        ),
        child: Text(
          '$label \u00B7 $count',
          style: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w700,
            color: selected ? Colors.white : _darkMaroon,
          ),
        ),
      ),
    );
  }

  Widget _emptyHistory() {
    final dateSelected = _selectedDate != null;

    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 84,
            height: 84,
            decoration: BoxDecoration(
              color: _lightBeige.withValues(alpha: 0.4),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.history_rounded,
              size: 36,
              color: _darkMaroon,
            ),
          ),
          const SizedBox(height: 18),
          Text(
            dateSelected ? 'No Pickups on This Date' : 'No History Yet',
            style: const TextStyle(
              fontSize: 17,
              fontWeight: FontWeight.w800,
              color: _darkMaroon,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            dateSelected
                ? 'Try another date or tap "All dates".'
                : 'Completed and failed pickups will appear here.',
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 12.5, color: _textMuted),
          ),
        ],
      ),
    );
  }
}

// ============================================================
// Successful pickup card
// ============================================================

class _SuccessfulPickupCard extends StatelessWidget {
  final VanPickupRequest pickup;
  final VoidCallback onTap;

  const _SuccessfulPickupCard({required this.pickup, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final handedOverAt = pickup.handedOverAt;
    final count = pickup.parcelCount;

    return InkWell(
      borderRadius: BorderRadius.circular(_radiusLg),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: _cardDecoration,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: _lightBeige.withValues(alpha: 0.45),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Icon(
                    Icons.storefront_rounded,
                    color: _darkMaroon,
                    size: 21,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        pickup.sellerName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 15.5,
                          fontWeight: FontWeight.w800,
                          color: _darkMaroon,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Pickup ${pickup.id}',
                        style: const TextStyle(
                          fontSize: 10.5,
                          color: _textMuted,
                        ),
                      ),
                    ],
                  ),
                ),
                const _StatusChip(label: 'Handed Over', color: _success),
              ],
            ),
            const SizedBox(height: 16),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(13),
              decoration: BoxDecoration(
                color: _cream.withValues(alpha: 0.6),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _CompactInfo(
                    icon: Icons.location_on_rounded,
                    text: '${pickup.barangay}, ${pickup.municipality}',
                  ),
                  const SizedBox(height: 9),
                  _CompactInfo(
                    icon: Icons.inventory_2_rounded,
                    text: '$count ${count == 1 ? 'parcel' : 'parcels'}',
                  ),
                  if (pickup.sellerPhone.trim().isNotEmpty) ...[
                    const SizedBox(height: 9),
                    _CompactInfo(
                      icon: Icons.phone_outlined,
                      text: pickup.sellerPhone,
                    ),
                  ],
                ],
              ),
            ),
            if (handedOverAt != null) ...[
              const SizedBox(height: 10),
              _DateInfoBox(
                icon: Icons.event_available_rounded,
                title: 'Handed over on',
                value:
                    '${vanFormatDate(handedOverAt)} \u2022 ${vanFormatTime(handedOverAt)}',
                accent: _success,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

// ============================================================
// Failed pickup card
// ============================================================

class _FailedPickupCard extends StatelessWidget {
  final VanPickupRequest pickup;
  final VoidCallback onTap;

  const _FailedPickupCard({required this.pickup, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final failedAt = pickup.failedAt;
    final reason = (pickup.failureReason ?? '').trim();
    final count = pickup.parcelCount;

    return InkWell(
      borderRadius: BorderRadius.circular(_radiusLg),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: _cardDecoration,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: _lightBeige.withValues(alpha: 0.45),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Icon(
                    Icons.storefront_rounded,
                    color: _darkMaroon,
                    size: 21,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        pickup.sellerName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          fontSize: 15.5,
                          fontWeight: FontWeight.w800,
                          color: _darkMaroon,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Pickup ${pickup.id}',
                        style: const TextStyle(
                          fontSize: 10.5,
                          color: _textMuted,
                        ),
                      ),
                    ],
                  ),
                ),
                const _StatusChip(label: 'Failed', color: _danger),
              ],
            ),
            const SizedBox(height: 16),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(13),
              decoration: BoxDecoration(
                color: _cream.withValues(alpha: 0.6),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _CompactInfo(
                    icon: Icons.location_on_rounded,
                    text: '${pickup.barangay}, ${pickup.municipality}',
                  ),
                  const SizedBox(height: 9),
                  _CompactInfo(
                    icon: Icons.inventory_2_rounded,
                    text: '$count ${count == 1 ? 'parcel' : 'parcels'}',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 10),
            _DateInfoBox(
              icon: Icons.report_problem_outlined,
              title: 'Reason',
              value: reason.isEmpty ? 'No reason provided.' : reason,
              accent: _danger,
            ),
            if (failedAt != null) ...[
              const SizedBox(height: 8),
              _DateInfoBox(
                icon: Icons.schedule_rounded,
                title: 'Reported on',
                value:
                    '${vanFormatDate(failedAt)} \u2022 ${vanFormatTime(failedAt)}',
              ),
            ],
          ],
        ),
      ),
    );
  }
}

// ============================================================
// Small reusable pieces
// ============================================================

class _StatusChip extends StatelessWidget {
  final String label;
  final Color color;

  const _StatusChip({required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
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
            label,
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w700,
              color: color,
            ),
          ),
        ],
      ),
    );
  }
}

class _CompactInfo extends StatelessWidget {
  final IconData icon;
  final String text;

  const _CompactInfo({required this.icon, required this.text});

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: _darkMaroon, size: 16),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              color: _darkMaroon,
              fontSize: 12,
              fontWeight: FontWeight.w500,
              height: 1.35,
            ),
          ),
        ),
      ],
    );
  }
}

class _DateInfoBox extends StatelessWidget {
  final IconData icon;
  final String title;
  final String value;
  final Color accent;

  const _DateInfoBox({
    required this.icon,
    required this.title,
    required this.value,
    this.accent = _darkMaroon,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: accent.withValues(alpha: 0.14)),
      ),
      child: Row(
        children: [
          Icon(icon, color: accent, size: 19),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 10,
                    color: _textMuted,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: accent,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ============================================================
// Date helpers
// ============================================================

const List<String> _months = [
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
];

DateTime vanDateOnly(DateTime date) =>
    DateTime(date.year, date.month, date.day);

/// A null [selected] means "all dates". A pickup with no timestamp only shows
/// when all dates are selected.
bool vanIsSameDay(DateTime? date, DateTime? selected) {
  if (selected == null) return true;
  if (date == null) return false;

  return vanDateOnly(date) == vanDateOnly(selected);
}

String vanFormatDate(DateTime date) =>
    '${_months[date.month - 1]} ${date.day}, ${date.year}';

String vanFormatTime(DateTime date) {
  final hour = date.hour % 12 == 0 ? 12 : date.hour % 12;
  final minute = date.minute.toString().padLeft(2, '0');
  final suffix = date.hour >= 12 ? 'PM' : 'AM';

  return '$hour:$minute $suffix';
}

/// "Today", "Yesterday", "All dates" or a formatted date.
/// With [withDate], Today/Yesterday also include the full date.
String vanDayLabel(DateTime? date, {bool withDate = false}) {
  if (date == null) return 'All dates';

  final today = vanDateOnly(DateTime.now());
  final day = vanDateOnly(date);
  final yesterday = DateTime(today.year, today.month, today.day - 1);

  final String? name = day == today
      ? 'Today'
      : day == yesterday
      ? 'Yesterday'
      : null;

  if (name == null) return vanFormatDate(day);

  return withDate ? '$name \u00B7 ${vanFormatDate(day)}' : name;
}

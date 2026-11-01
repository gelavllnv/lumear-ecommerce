import 'package:flutter/material.dart';

import '../../models/van_pickup_request.dart';
import 'van_pickup_details_screen.dart';
import 'van_pickup_widgets.dart';

// ============================================================
// DESIGN SYSTEM — same palette and tokens as the Seller Pickups screen
// ============================================================

const Color _darkMaroon = Color(0xFF62191C);
const Color _lightBeige = Color(0xFFCAAE9F);
const Color _cream = Color(0xFFF3E9E1);
const Color _textMuted = Color(0xFF8B6F63);

const double _radiusSm = 14;
const double _radiusLg = 22;

enum _SortingTab { waiting, awaiting, handedOver }

/// "Sorting" tab of the van rider app.
/// Tabs: pickups waiting for the seller, pickups ready for handover, and
/// pickups that were already handed over to the sorting center.
class VanSortingCenterScreen extends StatefulWidget {
  final List<VanPickupRequest> pickups;
  final VoidCallback onChanged;

  const VanSortingCenterScreen({
    super.key,
    required this.pickups,
    required this.onChanged,
  });

  @override
  State<VanSortingCenterScreen> createState() => _VanSortingCenterScreenState();
}

class _VanSortingCenterScreenState extends State<VanSortingCenterScreen> {
  static const double _tabsHeight = 66;

  static const EdgeInsets _listPadding = EdgeInsets.only(
    top: _tabsHeight + 4,
    bottom: 130, // room for the floating nav bar
  );

  _SortingTab _tab = _SortingTab.waiting;

  // ============================================================
  // Actions
  // ============================================================

  Future<void> _openPickupDetails(VanPickupRequest pickup) async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (_) =>
            VanPickupDetailsScreen(pickup: pickup, onChanged: widget.onChanged),
      ),
    );

    widget.onChanged();
    if (mounted) setState(() {});
  }

  Future<void> _showHandoverDialog(VanPickupRequest pickup) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Confirm Handover'),
        content: Text(
          'Confirm that ${pickup.parcelCount} parcels from '
          '${pickup.sellerName} have been handed over to the sorting center?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: vanDarkMaroon,
              foregroundColor: Colors.white,
            ),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );

    if (confirm == true && mounted) {
      setState(pickup.confirmHandover);
      widget.onChanged();

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Sorting center handover confirmed.')),
      );
    }
  }

  // ============================================================
  // Build
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final pickups = widget.pickups;

    final waiting = pickups
        .where((p) => p.status == VanPickupStatus.collected)
        .toList();

    final awaiting = pickups
        .where((p) => p.status == VanPickupStatus.sellerConfirmed)
        .toList();

    final handedOver = pickups
        .where((p) => p.status == VanPickupStatus.handedOver)
        .toList();

    return Scaffold(
      backgroundColor: _cream,
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Sorting Center',
                style: TextStyle(
                  fontSize: 27,
                  fontWeight: FontWeight.w800,
                  color: _darkMaroon,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                'Track pickups waiting for sellers, ready for handover, and completed.',
                style: TextStyle(fontSize: 12.5, color: _textMuted),
              ),
              const SizedBox(height: 14),
              Expanded(
                child: Stack(
                  clipBehavior: Clip.none,
                  children: [
                    Positioned.fill(
                      child: switch (_tab) {
                        _SortingTab.waiting => _buildTab(
                          list: waiting,
                          heading: 'Waiting for Seller Confirmation',
                          emptyIcon: Icons.hourglass_empty_rounded,
                          emptyMessage: 'No pickups are waiting\nfor seller confirmation.',
                          onTap: _openPickupDetails,
                        ),
                        _SortingTab.awaiting => _buildTab(
                          list: awaiting,
                          heading: 'Awaiting Handover',
                          emptyIcon: Icons.local_shipping_outlined,
                          emptyMessage: 'No seller-approved pickups\nare awaiting handover.',
                          onTap: _showHandoverDialog,
                        ),
                        _SortingTab.handedOver => _buildTab(
                          list: handedOver,
                          heading: 'Already Handed Over',
                          emptyIcon: Icons.check_circle_outline_rounded,
                          emptyMessage: 'No handovers completed yet.',
                          onTap: (_) {},
                        ),
                      },
                    ),
                    Positioned(
                      top: 0,
                      left: 0,
                      right: 0,
                      child: _buildFloatingTabs(
                        waiting.length,
                        awaiting.length,
                        handedOver.length,
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
  // Floating tabs (Waiting / Handover / Handed Over)
  // ============================================================

  Widget _buildFloatingTabs(int waiting, int awaiting, int handedOver) {
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
            _SortingTab.waiting,
            'Waiting',
            Icons.hourglass_empty_rounded,
            waiting,
          ),
          const SizedBox(width: 6),
          _tabButton(
            _SortingTab.awaiting,
            'Handover',
            Icons.local_shipping_outlined,
            awaiting,
          ),
          const SizedBox(width: 6),
          _tabButton(
            _SortingTab.handedOver,
            'Handed Over',
            Icons.check_circle_outline_rounded,
            handedOver,
          ),
        ],
      ),
    );
  }

  Widget _tabButton(_SortingTab tab, String label, IconData icon, int count) {
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
  // Tab content
  // ============================================================

  Widget _buildTab({
    required List<VanPickupRequest> list,
    required String heading,
    required IconData emptyIcon,
    required String emptyMessage,
    required void Function(VanPickupRequest pickup) onTap,
  }) {
    if (list.isEmpty) {
      return _emptyState(
        icon: emptyIcon,
        title: heading,
        message: emptyMessage,
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
        children: [
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Text(
              '$heading \u00B7 ${list.length} '
              '${list.length == 1 ? 'pickup' : 'pickups'}',
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: _darkMaroon,
                letterSpacing: -0.2,
              ),
            ),
          ),
          ...list.map(
            (pickup) =>
                VanPickupCard(pickup: pickup, onTap: () => onTap(pickup)),
          ),
        ],
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
              textAlign: TextAlign.center,
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

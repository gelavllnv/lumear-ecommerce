// file: van_rider_dashboard_screen.dart
import 'package:flutter/material.dart';

import '../../data/van_pickup_demo_data.dart';
import '../../models/van_pickup_request.dart';
import 'van_pickup_widgets.dart';
import 'van_seller_pickups_screen.dart';
import 'van_sorting_center_screen.dart';
import 'van_rider_profile_screen.dart' show VanRiderProfileScreen;
import 'van_earnings_screen.dart';

class VanRiderDashboardScreen extends StatefulWidget {
  const VanRiderDashboardScreen({super.key});

  @override
  State<VanRiderDashboardScreen> createState() =>
      _VanRiderDashboardScreenState();
}

class _VanRiderDashboardScreenState extends State<VanRiderDashboardScreen> {
  // ============================================================
  // FLOATING NAV — same look as the parcel rider dashboard
  // ============================================================

  static const double _radiusXl = 28;

  static List<BoxShadow> get _shadowFloating => [
    BoxShadow(
      color: vanDarkMaroon.withValues(alpha: 0.36),
      blurRadius: 26,
      offset: const Offset(0, 12),
    ),
    BoxShadow(
      color: Colors.black.withValues(alpha: 0.12),
      blurRadius: 5,
      offset: const Offset(0, 3),
    ),
  ];

  static const List<_NavItem> _navItems = [
    _NavItem(Icons.home_outlined, Icons.home_rounded, 'Home'),
    _NavItem(Icons.storefront_outlined, Icons.storefront_rounded, 'Pickups'),
    _NavItem(Icons.warehouse_outlined, Icons.warehouse_rounded, 'Sorting'),
    _NavItem(Icons.payments_outlined, Icons.payments_rounded, 'Earnings'),
    _NavItem(Icons.person_outline, Icons.person_rounded, 'Profile'),
  ];

  int _selectedIndex = 0;
  bool _pageVisible = true;
  bool _isNavigating = false;

  List<VanPickupRequest> get _pickups => vanPickupDemoData;

  int get _assignedCount =>
      _pickups.where((p) => p.status == VanPickupStatus.assigned).length;

  int get _inProgressCount =>
      _pickups.where((p) => p.status == VanPickupStatus.inProgress).length;

  int get _collectedCount =>
      _pickups.where((p) => p.status == VanPickupStatus.collected).length;

  int get _readyForHandoverCount =>
      _pickups.where((p) => p.status == VanPickupStatus.sellerConfirmed).length;

  int get _handedOverCount =>
      _pickups.where((p) => p.status == VanPickupStatus.handedOver).length;

  int get _totalParcels =>
      _pickups.fold<int>(0, (total, pickup) => total + pickup.parcelCount);

  void _refreshDashboard() {
    if (mounted) setState(() {});
  }

  // Fade out -> switch tab -> fade in (same as the parcel rider dashboard).
  Future<void> _openTab(int index) async {
    if (index < 0 || index >= _navItems.length) return;
    if (_selectedIndex == index || _isNavigating) return;

    _isNavigating = true;

    setState(() => _pageVisible = false);
    await Future.delayed(const Duration(milliseconds: 160));
    if (!mounted) return;

    setState(() => _selectedIndex = index);
    await Future.delayed(const Duration(milliseconds: 100));
    if (!mounted) return;

    setState(() => _pageVisible = true);
    await Future.delayed(const Duration(milliseconds: 260));
    if (!mounted) return;

    _isNavigating = false;
  }

  // Push the history screen (used from Quick Actions).
  Future<void> _openHistoryScreen() async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(builder: (_) => _buildHistoryScreen()),
    );
    _refreshDashboard();
  }

  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      _buildHomeScreen(),
      VanSellerPickupsScreen(pickups: _pickups, onChanged: _refreshDashboard),
      VanSortingCenterScreen(pickups: _pickups, onChanged: _refreshDashboard),
      VanEarningsScreen(pickups: _pickups),
      VanRiderProfileScreen(totalParcels: _totalParcels),
    ];

    // Keep the selected index within the available screens.
    final safeIndex = _selectedIndex.clamp(0, pages.length - 1);

    return Scaffold(
      backgroundColor: vanCream,
      body: Stack(
        children: [
          // Page content. It scrolls underneath the floating bar, so every
          // tab keeps extra bottom padding (130 px at the bottom).
          Positioned.fill(
            child: AnimatedOpacity(
              opacity: _pageVisible ? 1.0 : 0.0,
              duration: Duration(milliseconds: _pageVisible ? 260 : 160),
              curve: _pageVisible ? Curves.easeOutCubic : Curves.easeInCubic,
              child: AnimatedScale(
                scale: _pageVisible ? 1.0 : 0.985,
                duration: Duration(milliseconds: _pageVisible ? 280 : 160),
                curve: _pageVisible ? Curves.easeOutCubic : Curves.easeInCubic,
                child: IndexedStack(index: safeIndex, children: pages),
              ),
            ),
          ),

          // Floating navigation, drawn on top of the page.
          Positioned(
            left: 22,
            right: 22,
            bottom: 22,
            child: SafeArea(
              top: false,
              child: _buildFloatingNavigation(safeIndex),
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // FLOATING NAVIGATION
  // ============================================================

  Widget _buildFloatingNavigation(int selectedIndex) {
    return Container(
      height: 70,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: vanDarkMaroon,
        borderRadius: BorderRadius.circular(_radiusXl),
        border: Border.all(color: Colors.white.withValues(alpha: 0.28)),
        boxShadow: _shadowFloating,
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          for (int i = 0; i < _navItems.length; i++)
            _bottomNavItem(
              index: i,
              item: _navItems[i],
              isSelected: selectedIndex == i,
            ),
        ],
      ),
    );
  }

  // Cream pops out on the old item and pops in on the selected item.
  Widget _bottomNavItem({
    required int index,
    required _NavItem item,
    required bool isSelected,
  }) {
    return Tooltip(
      message: item.label,
      child: InkWell(
        onTap: () => _openTab(index),
        borderRadius: BorderRadius.circular(18),
        child: SizedBox(
          width: 54,
          height: 54,
          child: Center(
            child: Stack(
              alignment: Alignment.center,
              children: [
                AnimatedScale(
                  scale: isSelected ? 1.0 : 0.35,
                  duration: const Duration(milliseconds: 280),
                  curve: Curves.easeOutBack,
                  child: AnimatedOpacity(
                    opacity: isSelected ? 1.0 : 0.0,
                    duration: const Duration(milliseconds: 170),
                    curve: Curves.easeOut,
                    child: Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        color: vanCream,
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.16),
                            blurRadius: 10,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
                AnimatedScale(
                  scale: isSelected ? 1.10 : 1.0,
                  duration: const Duration(milliseconds: 280),
                  curve: Curves.easeOutBack,
                  child: AnimatedSwitcher(
                    duration: const Duration(milliseconds: 180),
                    transitionBuilder: (child, animation) {
                      return ScaleTransition(
                        scale: animation,
                        child: FadeTransition(opacity: animation, child: child),
                      );
                    },
                    child: Icon(
                      isSelected ? item.activeIcon : item.icon,
                      key: ValueKey(isSelected),
                      color: isSelected
                          ? vanDarkMaroon
                          : Colors.white.withValues(alpha: 0.85),
                      size: 23,
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

  // HOME DASHBOARD

  Widget _buildHomeScreen() {
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () async => _refreshDashboard(),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 130),
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'LUMEAR LOGISTICS',
                        style: TextStyle(
                          fontSize: 11,
                          letterSpacing: 1.4,
                          color: vanBrownBeige,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 5),
                      const Text(
                        'Van Rider Dashboard',
                        style: TextStyle(
                          fontSize: 23,
                          color: vanDarkMaroon,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        _greeting(),
                        style: const TextStyle(
                          fontSize: 13,
                          color: Colors.black54,
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  height: 48,
                  width: 48,
                  decoration: BoxDecoration(
                    color: vanDarkMaroon,
                    borderRadius: BorderRadius.circular(15),
                  ),
                  child: const Icon(
                    Icons.local_shipping,
                    color: Colors.white,
                    size: 27,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),

            // VAN STATUS CARD
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [vanDarkMaroon, vanMaroon],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(22),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Expanded(
                        child: Text(
                          'Your delivery vehicle',
                          style: TextStyle(color: Colors.white70, fontSize: 13),
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Row(
                          children: [
                            Icon(
                              Icons.circle,
                              size: 8,
                              color: Color(0xFF8FE3A0),
                            ),
                            SizedBox(width: 6),
                            Text(
                              'Demo Mode',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 11,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Van Delivery',
                    style: TextStyle(
                      fontSize: 25,
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Seller pickup and sorting center handover',
                    style: TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                  const SizedBox(height: 20),
                  const Divider(color: Colors.white24),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      const Icon(
                        Icons.inventory_2_outlined,
                        color: Colors.white,
                        size: 23,
                      ),
                      const SizedBox(width: 10),
                      const Expanded(
                        child: Text(
                          'Total parcels assigned',
                          style: TextStyle(color: Colors.white),
                        ),
                      ),
                      Text(
                        '$_totalParcels',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 25,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),
            const Text(
              'Pickup Overview',
              style: TextStyle(
                color: vanDarkMaroon,
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 12),
            _buildOverviewSection(),

            const SizedBox(height: 24),
            const Text(
              'Quick Actions',
              style: TextStyle(
                color: vanDarkMaroon,
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 12),
            _buildQuickActions(),

            const SizedBox(height: 18),
            Container(
              padding: const EdgeInsets.all(15),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(15),
                border: Border.all(color: const Color(0xFFE8D6C3)),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.info_outline, color: vanBrownBeige),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Demo mode: sample pickup information is stored '
                      'in memory. Real seller assignments will require '
                      'backend integration.',
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.black54,
                        height: 1.5,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  // PICKUP OVERVIEW

  Widget _buildOverviewSection() {
    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: _buildStatCard(
                title: 'Assigned',
                count: _assignedCount,
                icon: Icons.assignment_outlined,
                color: const Color(0xFF9B7000),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildStatCard(
                title: 'In Progress',
                count: _inProgressCount,
                icon: Icons.local_shipping_outlined,
                color: const Color(0xFF245CA6),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _buildStatCard(
                title: 'Waiting for Seller',
                count: _collectedCount,
                icon: Icons.hourglass_empty_rounded,
                color: const Color(0xFF6548A5),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _buildStatCard(
                title: 'Ready for Handover',
                count: _readyForHandoverCount,
                icon: Icons.warehouse_outlined,
                color: const Color(0xFFB4570A),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        _buildHandedOverCard(),
      ],
    );
  }

  Widget _buildStatCard({
    required String title,
    required int count,
    required IconData icon,
    required Color color,
  }) {
    return Container(
      height: 100, // fixed height = every card is the same size
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: const Color(0xFFE8D6C3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(7),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, color: color, size: 18),
              ),
              const Spacer(),
              Text(
                '$count',
                style: TextStyle(
                  color: color,
                  fontSize: 26,
                  fontWeight: FontWeight.bold,
                  height: 1,
                ),
              ),
            ],
          ),
          Text(
            title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: vanDarkMaroon,
              fontWeight: FontWeight.w600,
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHandedOverCard() {
    const color = Color(0xFF287544);
    final total = _pickups.length;
    final progress = total == 0 ? 0.0 : _handedOverCount / total;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: const Color(0xFFE8D6C3)),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(9),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.check_circle_outline,
                  color: color,
                  size: 22,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Handed Over',
                      style: TextStyle(
                        color: vanDarkMaroon,
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '$_handedOverCount of $total pickups completed',
                      style: const TextStyle(
                        color: Colors.black54,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              Text(
                '$_handedOverCount',
                style: const TextStyle(
                  color: color,
                  fontSize: 26,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 7,
              backgroundColor: color.withValues(alpha: 0.12),
              valueColor: const AlwaysStoppedAnimation<Color>(color),
            ),
          ),
        ],
      ),
    );
  }

  // QUICK ACTIONS

  Widget _buildQuickActions() {
    final actions = <_QuickAction>[
      _QuickAction(
        icon: Icons.storefront_outlined,
        title: 'Seller Pickups',
        subtitle: 'Assigned sellers and parcel details',
        color: const Color(0xFF245CA6),
        onTap: () => _openTab(1),
      ),
      _QuickAction(
        icon: Icons.warehouse_outlined,
        title: 'Sorting Center',
        subtitle: 'Seller-approved pickups ready for handover',
        color: const Color(0xFFB4570A),
        badgeCount: _readyForHandoverCount,
        onTap: () => _openTab(2),
      ),
      _QuickAction(
        icon: Icons.account_balance_wallet_outlined,
        title: 'Earnings',
        subtitle: 'Delivery earnings and payment summary',
        color: const Color(0xFF287544),
        onTap: () => _openTab(3),
      ),
      _QuickAction(
        icon: Icons.history,
        title: 'Pickup History',
        subtitle: 'Review completed pickup requests',
        color: const Color(0xFF6548A5),
        onTap: _openHistoryScreen,
      ),
      _QuickAction(
        icon: Icons.person_outline,
        title: 'Rider Profile',
        subtitle: 'Your profile and vehicle information',
        color: vanDarkMaroon,
        onTap: () => _openTab(4),
      ),
    ];

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFE8D6C3)),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(18),
        child: Material(
          color: Colors.white,
          child: Column(
            children: [
              for (int i = 0; i < actions.length; i++) ...[
                _buildActionRow(actions[i]),
                if (i != actions.length - 1)
                  const Divider(
                    height: 1,
                    thickness: 1,
                    indent: 70,
                    color: Color(0xFFF1E6D9),
                  ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildActionRow(_QuickAction action) {
    return InkWell(
      onTap: action.onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: action.color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(action.icon, color: action.color, size: 22),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    action.title,
                    style: const TextStyle(
                      color: vanDarkMaroon,
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    action.subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.black54,
                      fontSize: 11.5,
                    ),
                  ),
                ],
              ),
            ),
            if (action.badgeCount > 0) ...[
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: action.color,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  '${action.badgeCount}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              const SizedBox(width: 8),
            ],
            const Icon(Icons.chevron_right, color: vanBrownBeige),
          ],
        ),
      ),
    );
  }

  String _greeting() {
    final hour = DateTime.now().hour;

    if (hour < 12) return 'Good morning, Rider!';
    if (hour < 17) return 'Good afternoon, Rider!';
    return 'Good evening, Rider!';
  }

  // PICKUP HISTORY (pushed screen opened from Quick Actions)
  Widget _buildHistoryScreen() {
    final completed = _pickups
        .where((p) => p.status == VanPickupStatus.handedOver)
        .toList();

    return Scaffold(
      backgroundColor: vanCream,
      appBar: AppBar(
        backgroundColor: vanDarkMaroon,
        foregroundColor: Colors.white,
        title: const Text('Pickup History'),
      ),
      body: completed.isEmpty
          ? _emptyMessage('Completed pickups will appear here.')
          : ListView(
              padding: const EdgeInsets.all(16),
              children: completed
                  .map(
                    (pickup) => VanPickupCard(
                      pickup: pickup,
                      onTap: () => _showHistoryDetails(pickup),
                    ),
                  )
                  .toList(),
            ),
    );
  }

  Future<void> _showHistoryDetails(VanPickupRequest pickup) async {
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(pickup.sellerName),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Pickup ID: ${pickup.id}'),
            const SizedBox(height: 8),
            Text('Parcels: ${pickup.parcelCount}'),
            const SizedBox(height: 8),
            Text('Status: ${pickup.statusLabel}'),
            const SizedBox(height: 8),
            Text('Collected: ${_formatDate(pickup.collectedAt)}'),
            const SizedBox(height: 8),
            Text('Handed over: ${_formatDate(pickup.handedOverAt)}'),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  Widget _emptyMessage(String message) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 35),
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          const Icon(
            Icons.inventory_2_outlined,
            size: 42,
            color: vanBrownBeige,
          ),
          const SizedBox(height: 12),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.black54),
          ),
        ],
      ),
    );
  }

  String _formatDate(DateTime? date) {
    if (date == null) return 'Not recorded';

    final local = date.toLocal();
    final month = local.month.toString().padLeft(2, '0');
    final day = local.day.toString().padLeft(2, '0');

    return '$month/$day/${local.year} '
        '${local.hour.toString().padLeft(2, '0')}:'
        '${local.minute.toString().padLeft(2, '0')}';
  }
}

// Data holder for one item in the floating nav bar.
class _NavItem {
  const _NavItem(this.icon, this.activeIcon, this.label);

  final IconData icon;
  final IconData activeIcon;
  final String label;
}

// Data holder for one row in the Quick Actions card.
class _QuickAction {
  const _QuickAction({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
    this.badgeCount = 0,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;
  final int badgeCount;
}

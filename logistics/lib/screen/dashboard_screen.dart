import 'dart:async';

import 'package:flutter/material.dart';

import '../logistics_api.dart';
import 'package:flutter_application_2/screen/login_screen.dart';
import 'package:flutter_application_2/screen/rider_reports_screen.dart';
import 'package:flutter_application_2/screen/riders_screen.dart';

// ============================================================
// LUMEAR LOGISTICS THEME
// ============================================================

const Color logisticsDarkMaroon = Color(0xFF671B1D);
const Color logisticsMaroon = Color(0xFF873632);
const Color logisticsBrown = Color(0xFF9E7161);
const Color logisticsCream = Color(0xFFFFF8E7);
const Color logisticsWhite = Color(0xFFFFFDF5);
const Color logisticsBeige = Color(0xFFE8D6C3);
const Color logisticsText = Color(0xFF382523);
const Color logisticsMuted = Color(0xFF8C7770);

const Color _statusGreen = Color(0xFF3D9875);
const Color _statusAmber = Color(0xFFD98A2B);
const Color _statusBlue = Color(0xFF4A7FBF);
const Color _statusPurple = Color(0xFF8A5BC2);

const double _cardRadius = 16;
const double _innerRadius = 12;

// ============================================================
// DASHBOARD SCREEN
// ============================================================

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  int _selectedIndex = 0;
  Map<String, dynamic> _workspace = {};
  List<Map<String, dynamic>> _applications = [];
  List<Map<String, dynamic>> _deliveries = [];
  bool _loading = true;
  String? _loadError;
  Timer? _refreshTimer;

  static const int _dailyTarget = 200;

  int get _pendingRiders => _applications
      .where((application) => application['status'] == 'Pending')
      .length;

  int get _activeRiders =>
      ((_workspace['riders'] as List?) ?? const [])
          .where(
            (rider) =>
                (rider['is_active'] == true || rider['is_active'] == 1) &&
                rider['management_status'] != 'Suspended',
          )
          .length;

  int get _deliveriesToday => _deliveries.where((delivery) {
    final date = DateTime.tryParse(delivery['date']?.toString() ?? '');
    final now = DateTime.now();
    return delivery['status'] == 'Delivered' &&
        date != null &&
        date.year == now.year &&
        date.month == now.month &&
        date.day == now.day;
  }).length;

  int get _inTransit => _deliveries
      .where((delivery) =>
          ['In Transit', 'Out for Delivery', 'Dispatched']
              .contains(delivery['status']))
      .length;

  int get _totalApplications => _applications.length;

  List<_LogisticsActivity> get _activities {
    final items = <_LogisticsActivity>[];
    for (final application in _applications.take(3)) {
      items.add(
        _LogisticsActivity(
          title: 'Rider application ${application['status']}',
          description:
              '${application['firstName']} ${application['lastName']} · ${application['jobType']}',
          time: application['submitted']?.toString() ?? 'Recent',
          icon: Icons.person_add_alt_1_rounded,
          color: _statusPurple,
        ),
      );
    }
    for (final delivery in _deliveries.take(4 - items.length)) {
      final status = delivery['status']?.toString() ?? 'Updated';
      items.add(
        _LogisticsActivity(
          title: 'Delivery $status',
          description: '${delivery['tracking']} · ${delivery['destination']}',
          time: delivery['date']?.toString() ?? 'Recent',
          icon: status == 'Delivered'
              ? Icons.check_circle_rounded
              : Icons.local_shipping_rounded,
          color: status == 'Delivered' ? _statusGreen : _statusBlue,
        ),
      );
    }
    return items;
  }

  final List<String> _navigationLabels = const [
    'Dashboard',
    'Reports',
    'Riders',
    'Settings',
  ];

  final List<IconData> _navigationIcons = const [
    Icons.dashboard_outlined,
    Icons.insert_chart_outlined_rounded,
    Icons.delivery_dining_outlined,
    Icons.settings_outlined,
  ];

  final List<IconData> _navigationSelectedIcons = const [
    Icons.dashboard_rounded,
    Icons.insert_chart_rounded,
    Icons.delivery_dining_rounded,
    Icons.settings_rounded,
  ];

  @override
  void initState() {
    super.initState();
    _refreshDashboard();
    _refreshTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted && !_loading) _refreshDashboard();
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    super.dispose();
  }

  Future<void> _refreshDashboard() async {
    if (_loading && _workspace.isNotEmpty) return;
    setState(() {
      _loading = true;
      _loadError = null;
    });
    try {
      final results = await Future.wait([
        LogisticsApi.request('GET', '/logistics/workspace'),
        LogisticsApi.request('GET', '/logistics/riders'),
        LogisticsApi.request('GET', '/logistics/deliveries'),
      ]);
      if (!mounted) return;
      setState(() {
        _workspace = results[0];
        _applications = (results[1]['applications'] as List? ?? [])
            .whereType<Map>()
            .map((row) => Map<String, dynamic>.from(row))
            .toList();
        _deliveries = (results[2]['deliveries'] as List? ?? [])
            .whereType<Map>()
            .map((row) => Map<String, dynamic>.from(row))
            .toList();
      });
    } catch (error) {
      if (mounted) setState(() => _loadError = error.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final pages = [
      _buildDashboardPage(),
      RiderReportsScreen(),
      RidersScreen(),
      _buildSettingsPage(),
    ];

    return Scaffold(
      backgroundColor: logisticsCream,
      extendBody: true,
      body: SafeArea(
        bottom: false,
        child: IndexedStack(index: _selectedIndex, children: pages),
      ),
      bottomNavigationBar: _buildFloatingNavigationBar(),
    );
  }

  // ==========================================================
  // SHARED WIDGETS
  // ==========================================================

  BoxDecoration _cardDecoration() {
    return BoxDecoration(
      color: logisticsWhite,
      borderRadius: BorderRadius.circular(_cardRadius),
      border: Border.all(color: logisticsBeige.withValues(alpha: 0.7)),
    );
  }

  Widget _buildSectionHeading(
    String title, {
    String? actionLabel,
    VoidCallback? onAction,
  }) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              color: logisticsText,
              fontSize: 17,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.2,
            ),
          ),
        ),
        if (actionLabel != null)
          InkWell(
            onTap: onAction,
            borderRadius: BorderRadius.circular(8),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
              child: Text(
                actionLabel,
                style: const TextStyle(
                  color: logisticsMaroon,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
      ],
    );
  }

  // ==========================================================
  // DASHBOARD PAGE
  // ==========================================================

  Widget _buildDashboardPage() {
    return RefreshIndicator(
      onRefresh: _refreshDashboard,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(
          parent: BouncingScrollPhysics(),
        ),
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 110),
        children: [
          _buildHeader(),
          if (_loading) ...[
            const SizedBox(height: 10),
            const LinearProgressIndicator(color: logisticsMaroon),
          ],
          if (_loadError != null) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF8E7E3),
                borderRadius: BorderRadius.circular(_innerRadius),
              ),
              child: Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Could not refresh live logistics data. Check the server connection and pull to retry.',
                    ),
                  ),
                  IconButton(
                    onPressed: _loading ? null : _refreshDashboard,
                    icon: const Icon(Icons.refresh_rounded),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 22),
          if (_pendingRiders > 0) ...[
            _buildAttentionCard(),
            const SizedBox(height: 24),
          ],
          _buildSectionHeading('Today at a glance'),
          const SizedBox(height: 12),
          _buildStatisticsGrid(),
          const SizedBox(height: 12),
          _buildDeliveryProgressCard(),
          const SizedBox(height: 24),
          _buildSectionHeading('Shortcuts'),
          const SizedBox(height: 12),
          _buildShortcuts(),
          const SizedBox(height: 24),
          _buildSectionHeading(
            'Recent activity',
            actionLabel: 'View all',
            onAction: _showNotifications,
          ),
          const SizedBox(height: 12),
          _buildRecentActivities(),
        ],
      ),
    );
  }

  // ==========================================================
  // HEADER
  // ==========================================================

  String _greeting() {
    final hour = DateTime.now().hour;

    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';

    return 'Good evening';
  }

  String _formattedDate() {
    const weekdays = [
      'Monday',
      'Tuesday',
      'Wednesday',
      'Thursday',
      'Friday',
      'Saturday',
      'Sunday',
    ];

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

    final now = DateTime.now();

    return '${weekdays[now.weekday - 1]}, '
        '${months[now.month - 1]} ${now.day}';
  }

  Widget _buildHeader() {
    return Row(
      children: [
        Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(
            color: logisticsDarkMaroon,
            borderRadius: BorderRadius.circular(_innerRadius + 2),
          ),
          child: const Icon(
            Icons.local_shipping_rounded,
            color: logisticsCream,
            size: 24,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                _greeting(),
                style: const TextStyle(
                  color: logisticsDarkMaroon,
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.4,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                _formattedDate(),
                style: const TextStyle(color: logisticsMuted, fontSize: 12),
              ),
            ],
          ),
        ),
        _buildHeaderIconButton(
          icon: Icons.refresh_rounded,
          tooltip: 'Refresh live data',
          onTap: _refreshDashboard,
        ),
        const SizedBox(width: 8),
        _buildHeaderIconButton(
          icon: Icons.notifications_none_rounded,
          tooltip: 'Notifications',
          onTap: _showNotifications,
          badgeCount: _pendingRiders,
        ),
        const SizedBox(width: 8),
        _buildHeaderIconButton(
          icon: Icons.person_outline_rounded,
          tooltip: 'Profile',
          onTap: () => setState(() => _selectedIndex = 3),
        ),
      ],
    );
  }

  Widget _buildHeaderIconButton({
    required IconData icon,
    required String tooltip,
    required VoidCallback onTap,
    int badgeCount = 0,
  }) {
    return Tooltip(
      message: tooltip,
      child: Material(
        color: logisticsWhite,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(_innerRadius),
          side: BorderSide(color: logisticsBeige.withValues(alpha: 0.7)),
        ),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(_innerRadius),
          child: SizedBox(
            width: 42,
            height: 42,
            child: Stack(
              alignment: Alignment.center,
              children: [
                Icon(icon, color: logisticsDarkMaroon, size: 22),
                if (badgeCount > 0)
                  Positioned(
                    top: 6,
                    right: 6,
                    child: Container(
                      constraints: const BoxConstraints(
                        minWidth: 16,
                        minHeight: 16,
                      ),
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFFD64541),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: logisticsWhite, width: 1.5),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        '$badgeCount',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 9,
                          fontWeight: FontWeight.w800,
                          height: 1.1,
                        ),
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

  // ==========================================================
  // NEEDS ATTENTION
  // ==========================================================

  Widget _buildAttentionCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: logisticsDarkMaroon,
        borderRadius: BorderRadius.circular(_cardRadius + 4),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(_innerRadius - 2),
                ),
                child: const Icon(
                  Icons.person_search_rounded,
                  color: logisticsCream,
                  size: 21,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  'Needs your review',
                  style: TextStyle(
                    color: logisticsCream.withValues(alpha: 0.8),
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            '$_pendingRiders rider applications are waiting',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 21,
              height: 1.2,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.4,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Check documents and approve riders so they can start taking jobs.',
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.75),
              fontSize: 12,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 16),
          FilledButton(
            onPressed: () => setState(() => _selectedIndex = 2),
            style: FilledButton.styleFrom(
              backgroundColor: logisticsCream,
              foregroundColor: logisticsDarkMaroon,
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(_innerRadius),
              ),
            ),
            child: const Text(
              'Review applications',
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 12),
            ),
          ),
        ],
      ),
    );
  }

  // ==========================================================
  // STATISTICS
  // ==========================================================

  Widget _buildStatisticsGrid() {
    final statistics = [
      _LogisticsStatistic(
        title: 'Active riders',
        value: '$_activeRiders',
        caption: 'On duty now',
        icon: Icons.delivery_dining_rounded,
        color: _statusGreen,
      ),
      _LogisticsStatistic(
        title: 'Pending riders',
        value: '$_pendingRiders',
        caption: 'Awaiting review',
        icon: Icons.hourglass_top_rounded,
        color: _statusAmber,
      ),
      _LogisticsStatistic(
        title: 'Deliveries',
        value: '$_deliveriesToday',
        caption: 'Completed today',
        icon: Icons.inventory_2_rounded,
        color: _statusBlue,
      ),
      _LogisticsStatistic(
        title: 'Total applications',
        value: '$_totalApplications',
        caption: 'Rider applications received',
        icon: Icons.assignment_ind_rounded,
        color: _statusPurple,
      ),
    ];

    return Column(
      children: [
        for (int row = 0; row < statistics.length; row += 2) ...[
          if (row > 0) const SizedBox(height: 12),
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(child: _buildStatisticCard(statistics[row])),
                const SizedBox(width: 12),
                Expanded(child: _buildStatisticCard(statistics[row + 1])),
              ],
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildStatisticCard(_LogisticsStatistic statistic) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: statistic.color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(_innerRadius - 2),
            ),
            child: Icon(statistic.icon, color: statistic.color, size: 19),
          ),
          const SizedBox(height: 14),
          Text(
            statistic.value,
            style: const TextStyle(
              color: logisticsText,
              fontSize: 28,
              height: 1,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.8,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            statistic.title,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: logisticsText,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            statistic.caption,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: logisticsMuted, fontSize: 11),
          ),
        ],
      ),
    );
  }

  // ==========================================================
  // DELIVERY PROGRESS
  // ==========================================================

  Widget _buildDeliveryProgressCard() {
    final double progress = (_deliveriesToday / _dailyTarget).clamp(0.0, 1.0);
    final int percent = (progress * 100).round();
    final int remaining = (_dailyTarget - _deliveriesToday).clamp(
      0,
      _dailyTarget,
    );

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _cardDecoration(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              const Expanded(
                child: Text(
                  'Daily delivery target',
                  style: TextStyle(
                    color: logisticsText,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Text(
                '$percent%',
                style: const TextStyle(
                  color: logisticsDarkMaroon,
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.4,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 8,
              backgroundColor: logisticsBeige.withValues(alpha: 0.6),
              valueColor: const AlwaysStoppedAnimation<Color>(logisticsMaroon),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              _buildProgressFigure('Completed', '$_deliveriesToday'),
              _buildProgressDivider(),
              _buildProgressFigure('In transit', '$_inTransit'),
              _buildProgressDivider(),
              _buildProgressFigure('Remaining', '$remaining'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildProgressFigure(String label, String value) {
    return Expanded(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: const TextStyle(
              color: logisticsText,
              fontSize: 17,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: const TextStyle(color: logisticsMuted, fontSize: 11),
          ),
        ],
      ),
    );
  }

  Widget _buildProgressDivider() {
    return Container(
      width: 1,
      height: 30,
      margin: const EdgeInsets.symmetric(horizontal: 12),
      color: logisticsBeige.withValues(alpha: 0.8),
    );
  }

  // ==========================================================
  // SHORTCUTS
  // ==========================================================

  Widget _buildShortcuts() {
    return Container(
      decoration: _cardDecoration(),
      child: Column(
        children: [
          _buildShortcutRow(
            title: 'Review riders',
            subtitle: 'Approve or decline new applications',
            icon: Icons.verified_user_rounded,
            color: _statusAmber,
            onTap: () => setState(() => _selectedIndex = 2),
          ),
          Divider(height: 1, color: logisticsBeige.withValues(alpha: 0.6)),
          _buildShortcutRow(
            title: 'View reports',
            subtitle: 'Delivery records and rider performance',
            icon: Icons.analytics_rounded,
            color: _statusBlue,
            onTap: () => setState(() => _selectedIndex = 1),
          ),
        ],
      ),
    );
  }

  Widget _buildShortcutRow({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(_cardRadius),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(_innerRadius - 1),
              ),
              child: Icon(icon, color: color, size: 21),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: logisticsText,
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: const TextStyle(color: logisticsMuted, fontSize: 11),
                  ),
                ],
              ),
            ),
            const Icon(
              Icons.chevron_right_rounded,
              color: logisticsMuted,
              size: 22,
            ),
          ],
        ),
      ),
    );
  }

  // ==========================================================
  // RECENT ACTIVITY
  // ==========================================================

  Widget _buildRecentActivities() {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
      decoration: _cardDecoration(),
      child: Column(
        children: List.generate(_activities.length, (index) {
          final activity = _activities[index];
          final bool isLast = index == _activities.length - 1;

          return IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(
                  width: 34,
                  child: Column(
                    children: [
                      Container(
                        width: 34,
                        height: 34,
                        decoration: BoxDecoration(
                          color: activity.color.withValues(alpha: 0.12),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          activity.icon,
                          color: activity.color,
                          size: 17,
                        ),
                      ),
                      if (!isLast)
                        Expanded(
                          child: Container(
                            width: 1.5,
                            margin: const EdgeInsets.symmetric(vertical: 4),
                            color: logisticsBeige,
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Padding(
                    padding: EdgeInsets.only(bottom: isLast ? 12 : 18, top: 2),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: Text(
                                activity.title,
                                style: const TextStyle(
                                  color: logisticsText,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Text(
                              activity.time,
                              style: const TextStyle(
                                color: logisticsMuted,
                                fontSize: 11,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 3),
                        Text(
                          activity.description,
                          style: const TextStyle(
                            color: logisticsMuted,
                            fontSize: 11,
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          );
        }),
      ),
    );
  }

  // ==========================================================
  // BOTTOM NAVIGATION
  // ==========================================================

  Widget _buildFloatingNavigationBar() {
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        child: Container(
          height: 68,
          padding: const EdgeInsets.all(6),
          decoration: BoxDecoration(
            color: logisticsWhite,
            borderRadius: BorderRadius.circular(24),
            border: Border.all(color: logisticsBeige.withValues(alpha: 0.8)),
            boxShadow: [
              BoxShadow(
                color: logisticsDarkMaroon.withValues(alpha: 0.12),
                blurRadius: 22,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Row(
            children: List.generate(
              _navigationLabels.length,
              (index) => Expanded(child: _buildNavigationItem(index)),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildNavigationItem(int index) {
    final isSelected = _selectedIndex == index;

    return Semantics(
      button: true,
      selected: isSelected,
      label: _navigationLabels[index],
      child: GestureDetector(
        onTap: () => setState(() => _selectedIndex = index),
        behavior: HitTestBehavior.opaque,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOut,
          margin: const EdgeInsets.symmetric(horizontal: 3),
          decoration: BoxDecoration(
            color: isSelected ? logisticsDarkMaroon : Colors.transparent,
            borderRadius: BorderRadius.circular(18),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(
                isSelected
                    ? _navigationSelectedIcons[index]
                    : _navigationIcons[index],
                size: 22,
                color: isSelected ? logisticsWhite : logisticsMuted,
              ),
              const SizedBox(height: 3),
              Text(
                _navigationLabels[index],
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                  color: isSelected ? logisticsWhite : logisticsMuted,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ==========================================================
  // SETTINGS PAGE - NOW INCLUDES LOGOUT
  // ==========================================================

  Widget _buildSettingsPage() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 120),
      physics: const BouncingScrollPhysics(),
      children: [
        const Text(
          'Settings',
          style: TextStyle(
            color: logisticsDarkMaroon,
            fontSize: 26,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.6,
          ),
        ),
        const SizedBox(height: 4),
        const Text(
          'Manage your profile and app preferences.',
          style: TextStyle(color: logisticsMuted, fontSize: 12),
        ),
        const SizedBox(height: 20),

        _buildProfileCard(),

        const SizedBox(height: 24),
        _buildSectionHeading('General'),
        const SizedBox(height: 12),

        Container(
          decoration: _cardDecoration(),
          child: Column(
            children: [
              _buildSettingsTile(
                icon: Icons.person_outline_rounded,
                title: 'Personal information',
                subtitle: 'View your profile details',
                color: _statusBlue,
                onTap: _showPersonalInformation,
              ),
              Divider(height: 1, color: logisticsBeige.withValues(alpha: 0.6)),
              _buildSettingsTile(
                icon: Icons.notifications_none_rounded,
                title: 'Notifications',
                subtitle: 'See the latest updates',
                color: _statusAmber,
                onTap: _showNotifications,
              ),
              Divider(height: 1, color: logisticsBeige.withValues(alpha: 0.6)),
              _buildSettingsTile(
                icon: Icons.info_outline_rounded,
                title: 'About Lumear Logistics',
                subtitle: 'App version and information',
                color: _statusGreen,
                onTap: _showAbout,
              ),
            ],
          ),
        ),

        const SizedBox(height: 20),

        // Account notice
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: const Color(0xFFF8E7E3),
            borderRadius: BorderRadius.circular(_innerRadius + 2),
          ),
          child: const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.info_outline_rounded,
                color: logisticsMaroon,
                size: 20,
              ),
              SizedBox(width: 12),
              Expanded(
                child: Text(
                  'Account management is not connected yet. Connect your '
                  'backend to manage a real account from here.',
                  style: TextStyle(
                    color: logisticsDarkMaroon,
                    fontSize: 11,
                    height: 1.5,
                  ),
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: 24),

        // Logout heading
        _buildSectionHeading('Account Actions'),
        const SizedBox(height: 12),

        // Visible logout button
        Container(
          width: double.infinity,
          decoration: BoxDecoration(
            color: logisticsWhite,
            borderRadius: BorderRadius.circular(_cardRadius),
            border: Border.all(color: const Color(0xFFE5B8B8)),
          ),
          child: ListTile(
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 8,
            ),
            leading: Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: Colors.red.withValues(alpha: 0.10),
                borderRadius: BorderRadius.circular(_innerRadius),
              ),
              child: const Icon(Icons.logout_rounded, color: Colors.red),
            ),
            title: const Text(
              'Log Out',
              style: TextStyle(
                color: Colors.red,
                fontSize: 14,
                fontWeight: FontWeight.w800,
              ),
            ),
            subtitle: const Text(
              'Sign out of your administrator account',
              style: TextStyle(color: logisticsMuted, fontSize: 11),
            ),
            trailing: const Icon(
              Icons.chevron_right_rounded,
              color: Colors.red,
            ),
            onTap: _confirmLogout,
          ),
        ),

        const SizedBox(height: 24),
      ],
    );
  }

  // ==========================================================
  // PROFILE CARD
  // ==========================================================

  Widget _buildProfileCard() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: logisticsDarkMaroon,
        borderRadius: BorderRadius.circular(_cardRadius + 4),
      ),
      child: Row(
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.12),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.local_shipping_rounded,
              color: logisticsCream,
              size: 27,
            ),
          ),
          const SizedBox(width: 14),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Logistics Personnel',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                SizedBox(height: 3),
                Text(
                  'Lumear Logistics',
                  style: TextStyle(color: logisticsBeige, fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSettingsTile({
    required IconData icon,
    required String title,
    required String subtitle,
    required Color color,
    required VoidCallback onTap,
  }) {
    return ListTile(
      onTap: onTap,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(_cardRadius),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      leading: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(_innerRadius - 1),
        ),
        child: Icon(icon, color: color, size: 21),
      ),
      title: Text(
        title,
        style: const TextStyle(
          color: logisticsText,
          fontSize: 13,
          fontWeight: FontWeight.w700,
        ),
      ),
      subtitle: Padding(
        padding: const EdgeInsets.only(top: 2),
        child: Text(
          subtitle,
          style: const TextStyle(color: logisticsMuted, fontSize: 11),
        ),
      ),
      trailing: const Icon(Icons.chevron_right_rounded, color: logisticsMuted),
    );
  }

  // ==========================================================
  // LOGOUT CONFIRMATION
  // ==========================================================

  void _confirmLogout() {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: logisticsWhite,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(_cardRadius + 4),
        ),
        title: const Row(
          children: [
            Icon(Icons.logout_rounded, color: Colors.red),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                'Confirm Logout',
                style: TextStyle(
                  color: logisticsDarkMaroon,
                  fontWeight: FontWeight.w800,
                  fontSize: 19,
                ),
              ),
            ),
          ],
        ),
        content: const Text(
          'Are you sure you want to log out of your administrator account?',
          style: TextStyle(color: logisticsMuted, height: 1.5),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text(
              'Cancel',
              style: TextStyle(color: logisticsMuted),
            ),
          ),
          ElevatedButton.icon(
            style: ElevatedButton.styleFrom(
              backgroundColor: logisticsDarkMaroon,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            icon: const Icon(Icons.logout_rounded, size: 18),
            label: const Text('Log Out'),
            onPressed: () {
              // Close confirmation dialog first.
              Navigator.pop(dialogContext);

              // Navigate to login and remove previous routes.
              Navigator.of(context).pushAndRemoveUntil(
                MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
                (route) => false,
              );
            },
          ),
        ],
      ),
    );
  }

  // ==========================================================
  // NOTIFICATIONS
  // ==========================================================

  void _showNotifications() {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: logisticsWhite,
      isScrollControlled: true,
      showDragHandle: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Notifications',
                  style: TextStyle(
                    color: logisticsDarkMaroon,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.4,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Your latest logistics updates.',
                  style: TextStyle(color: logisticsMuted, fontSize: 12),
                ),
                const SizedBox(height: 12),
                ..._activities
                    .take(3)
                    .map(
                      (activity) => ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: CircleAvatar(
                          backgroundColor: activity.color.withValues(
                            alpha: 0.13,
                          ),
                          child: Icon(
                            activity.icon,
                            color: activity.color,
                            size: 20,
                          ),
                        ),
                        title: Text(
                          activity.title,
                          style: const TextStyle(
                            color: logisticsText,
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        subtitle: Text(
                          activity.description,
                          style: const TextStyle(
                            color: logisticsMuted,
                            fontSize: 11,
                          ),
                        ),
                        trailing: Text(
                          activity.time,
                          style: const TextStyle(
                            color: logisticsMuted,
                            fontSize: 10,
                          ),
                        ),
                      ),
                    ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(
                    onPressed: () => Navigator.pop(sheetContext),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: logisticsDarkMaroon,
                      side: const BorderSide(color: logisticsBeige),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(_innerRadius),
                      ),
                    ),
                    child: const Text('Close'),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  // ==========================================================
  // PERSONAL INFORMATION
  // ==========================================================

  void _showPersonalInformation() {
    showDialog<void>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          backgroundColor: logisticsWhite,
          surfaceTintColor: Colors.transparent,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(_cardRadius + 4),
          ),
          title: const Text(
            'Personal information',
            style: TextStyle(
              color: logisticsDarkMaroon,
              fontWeight: FontWeight.w800,
              fontSize: 19,
            ),
          ),
          content: const Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _InfoRow(label: 'Role', value: 'Logistics Personnel'),
              SizedBox(height: 12),
              _InfoRow(label: 'Organization', value: 'Lumear Logistics'),
              SizedBox(height: 12),
              _InfoRow(label: 'Account email', value: 'Not connected'),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text(
                'Close',
                style: TextStyle(
                  color: logisticsDarkMaroon,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        );
      },
    );
  }

  // ==========================================================
  // ABOUT
  // ==========================================================

  void _showAbout() {
    showAboutDialog(
      context: context,
      applicationName: 'Lumear Logistics',
      applicationVersion: '1.0.0',
      applicationIcon: Container(
        width: 48,
        height: 48,
        decoration: BoxDecoration(
          color: logisticsDarkMaroon,
          borderRadius: BorderRadius.circular(_innerRadius),
        ),
        child: const Icon(
          Icons.local_shipping_rounded,
          color: logisticsCream,
          size: 26,
        ),
      ),
      children: const [
        Text(
          'Lumear Logistics supports rider coordination, '
          'delivery reporting, and logistics operations.',
        ),
      ],
    );
  }
}

// ============================================================
// INFORMATION ROW
// ============================================================

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;

  const _InfoRow({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(color: logisticsMuted, fontSize: 11),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: const TextStyle(
            color: logisticsText,
            fontSize: 14,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

// ============================================================
// STATISTICS MODEL
// ============================================================

class _LogisticsStatistic {
  final String title;
  final String value;
  final String caption;
  final IconData icon;
  final Color color;

  const _LogisticsStatistic({
    required this.title,
    required this.value,
    required this.caption,
    required this.icon,
    required this.color,
  });
}

// ============================================================
// ACTIVITY MODEL
// ============================================================

class _LogisticsActivity {
  final String title;
  final String description;
  final String time;
  final IconData icon;
  final Color color;

  const _LogisticsActivity({
    required this.title,
    required this.description,
    required this.time,
    required this.icon,
    required this.color,
  });
}

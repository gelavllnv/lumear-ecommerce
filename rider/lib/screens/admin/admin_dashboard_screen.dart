import 'package:flutter/material.dart';

import '../../models/bug_report.dart';
import '../../services/bug_report_service.dart';
import '../login_screen.dart';
import 'admin_home_screen.dart';
import 'admin_bug_reports_screen.dart';
import 'admin_riders_screen.dart';

class AdminDashboardScreen extends StatefulWidget {
  const AdminDashboardScreen({super.key});

  @override
  State<AdminDashboardScreen> createState() => _AdminDashboardScreenState();
}

class _AdminDashboardScreenState extends State<AdminDashboardScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color cream = Color(0xFFE0CFC2);
  static const Color navCream = Color(0xFFF3E9E1);
  static const Color background = Color(0xFFF7F3F1);

  static const double _radiusXl = 28;

  static List<BoxShadow> get _shadowFloating => [
    BoxShadow(
      color: darkMaroon.withValues(alpha: 0.36),
      blurRadius: 26,
      offset: const Offset(0, 12),
    ),
    BoxShadow(
      color: Colors.black.withValues(alpha: 0.12),
      blurRadius: 5,
      offset: const Offset(0, 3),
    ),
  ];

  // =========================
  // FLOATING NAV ITEMS
  // =========================
  static const List<_NavItem> _navItems = [
    _NavItem(Icons.dashboard_outlined, Icons.dashboard_rounded, 'Home'),
    _NavItem(
      Icons.bug_report_outlined,
      Icons.bug_report_rounded,
      'Bug Reports',
    ),
    _NavItem(
      Icons.people_outline_rounded,
      Icons.people_rounded,
      'Rider Information',
    ),
  ];

  late final List<Widget> _pages;
  final BugReportService _bugReportService = BugReportService.instance;
  final Set<String> _seenReportIds = {};

  int _selectedIndex = 0;
  bool _pageVisible = true;
  bool _isNavigating = false;

  // =========================
  // PAGE TITLES
  // =========================
  final List<String> _titles = const [
    'Admin Dashboard',
    'Bug Reports',
    'Rider Information',
  ];

  @override
  void initState() {
    super.initState();
    _pages = [
      AdminHomeScreen(onViewAllReports: () => _goToPage(1)),
      const AdminBugReportsScreen(),
      const AdminRidersScreen(),
    ];
  }

  // Fade out -> switch page -> fade in.
  Future<void> _goToPage(int index) async {
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

  Future<void> _showBugReportNotifications() async {
    final List<BugReport> reports = _bugReportService.reports;
    setState(() {
      _seenReportIds.addAll(reports.map((report) => report.id));
    });

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Bug Report Notifications',
                  style: TextStyle(
                    color: darkMaroon,
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 14),
                if (reports.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 20),
                    child: Center(
                      child: Text(
                        'No bug reports yet.',
                        style: TextStyle(color: Color(0xFF9E7161)),
                      ),
                    ),
                  )
                else
                  ConstrainedBox(
                    constraints: BoxConstraints(
                      maxHeight: MediaQuery.sizeOf(sheetContext).height * 0.5,
                    ),
                    child: ListView.separated(
                      shrinkWrap: true,
                      itemCount: reports.length,
                      separatorBuilder: (_, __) => const Divider(height: 1),
                      itemBuilder: (context, index) {
                        final report = reports[index];
                        return ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: const CircleAvatar(
                            backgroundColor: Color(0xFFF3E9E1),
                            child: Icon(
                              Icons.bug_report_outlined,
                              color: darkMaroon,
                            ),
                          ),
                          title: Text(
                            report.description,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: darkMaroon,
                              fontWeight: FontWeight.w700,
                              fontSize: 13,
                            ),
                          ),
                          subtitle: Text(
                            '${report.riderName} · ${report.status}',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          onTap: () {
                            Navigator.pop(sheetContext);
                            _goToPage(1);
                          },
                        );
                      },
                    ),
                  ),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: () {
                      Navigator.pop(sheetContext);
                      _goToPage(1);
                    },
                    icon: const Icon(Icons.list_alt_rounded),
                    label: const Text('Open Bug Reports'),
                    style: FilledButton.styleFrom(
                      backgroundColor: darkMaroon,
                      foregroundColor: Colors.white,
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: background,

      // =========================
      // APP BAR
      // =========================
      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        titleSpacing: 16,

        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              _titles[_selectedIndex],
              style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
            ),
            const Text(
              'Lumear Administration',
              style: TextStyle(fontSize: 10, color: cream),
            ),
          ],
        ),

        actions: [
          // =========================
          // NOTIFICATIONS
          // =========================
          AnimatedBuilder(
            animation: _bugReportService,
            builder: (context, _) {
              final int unreadCount = _bugReportService.reports
                  .where((report) => !_seenReportIds.contains(report.id))
                  .length;

              return IconButton(
                tooltip: unreadCount == 0
                    ? 'Bug report notifications'
                    : '$unreadCount new bug report${unreadCount == 1 ? '' : 's'}',
                onPressed: _showBugReportNotifications,
                icon: Stack(
                  clipBehavior: Clip.none,
                  children: [
                    const Icon(Icons.notifications_none_rounded),
                    if (unreadCount > 0)
                      Positioned(
                        top: -7,
                        right: -8,
                        child: Container(
                          constraints: const BoxConstraints(
                            minWidth: 17,
                            minHeight: 17,
                          ),
                          padding: const EdgeInsets.symmetric(horizontal: 4),
                          decoration: const BoxDecoration(
                            color: Color(0xFFB3261E),
                            shape: BoxShape.circle,
                          ),
                          alignment: Alignment.center,
                          child: Text(
                            unreadCount > 99 ? '99+' : '$unreadCount',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 9,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              );
            },
          ),

          // =========================
          // ADMIN MENU
          // =========================
          PopupMenuButton<String>(
            onSelected: (value) {
              if (value == 'profile') {
                _showAdminProfile();
              }

              if (value == 'logout') {
                _showLogoutDialog();
              }
            },
            itemBuilder: (context) => const [
              PopupMenuItem(
                value: 'profile',
                child: Row(
                  children: [
                    Icon(
                      Icons.admin_panel_settings_outlined,
                      color: darkMaroon,
                    ),
                    SizedBox(width: 10),
                    Text('Admin Profile'),
                  ],
                ),
              ),
              PopupMenuItem(
                value: 'logout',
                child: Row(
                  children: [
                    Icon(Icons.logout_rounded, color: darkMaroon),
                    SizedBox(width: 10),
                    Text('Log Out'),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),

      // =========================
      // PAGES + FLOATING NAV
      // =========================
      body: Stack(
        children: [
          // Pages scroll underneath the floating bar, so each page keeps
          // about 130 px of bottom padding.
          Positioned.fill(
            child: AnimatedOpacity(
              opacity: _pageVisible ? 1.0 : 0.0,
              duration: Duration(milliseconds: _pageVisible ? 260 : 160),
              curve: _pageVisible ? Curves.easeOutCubic : Curves.easeInCubic,
              child: AnimatedScale(
                scale: _pageVisible ? 1.0 : 0.985,
                duration: Duration(milliseconds: _pageVisible ? 280 : 160),
                curve: _pageVisible ? Curves.easeOutCubic : Curves.easeInCubic,
                child: IndexedStack(index: _selectedIndex, children: _pages),
              ),
            ),
          ),

          Positioned(
            left: 22,
            right: 22,
            bottom: 22,
            child: SafeArea(top: false, child: _buildFloatingNavigation()),
          ),
        ],
      ),
    );
  }

  // =========================
  // FLOATING NAVIGATION
  // =========================
  Widget _buildFloatingNavigation() {
    return Container(
      height: 70,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: darkMaroon,
        borderRadius: BorderRadius.circular(_radiusXl),
        border: Border.all(color: Colors.white.withValues(alpha: 0.28)),
        boxShadow: _shadowFloating,
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          for (int i = 0; i < _navItems.length; i++)
            _bottomNavItem(index: i, item: _navItems[i]),
        ],
      ),
    );
  }

  // Cream pops out on the old item and pops in on the selected item.
  Widget _bottomNavItem({required int index, required _NavItem item}) {
    final bool isSelected = _selectedIndex == index;

    return Tooltip(
      message: item.label,
      child: InkWell(
        onTap: () => _goToPage(index),
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
                        color: navCream,
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
                          ? darkMaroon
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

  // =========================
  // ADMIN PROFILE
  // =========================
  void _showAdminProfile() {
    showDialog(
      context: context,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(22),
          ),
          title: const Row(
            children: [
              CircleAvatar(
                backgroundColor: cream,
                child: Icon(
                  Icons.admin_panel_settings_rounded,
                  color: darkMaroon,
                ),
              ),
              SizedBox(width: 12),
              Expanded(child: Text('Lumear Admin')),
            ],
          ),
          content: const Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Email', style: TextStyle(fontSize: 11, color: Colors.grey)),
              SizedBox(height: 3),
              Text(
                'lumear@gmail.com',
                style: TextStyle(fontWeight: FontWeight.w600),
              ),
              SizedBox(height: 14),
              Text('Role', style: TextStyle(fontSize: 11, color: Colors.grey)),
              SizedBox(height: 3),
              Text(
                'System Administrator',
                style: TextStyle(fontWeight: FontWeight.w600),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(context);
              },
              child: const Text('Close', style: TextStyle(color: darkMaroon)),
            ),
          ],
        );
      },
    );
  }

  // =========================
  // LOGOUT
  // =========================
  Future<void> _showLogoutDialog() async {
    final bool? logout = await showDialog<bool>(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text('Log Out'),
          content: const Text(
            'Are you sure you want to log out of the admin account?',
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(context, false);
              },
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              onPressed: () {
                Navigator.pop(context, true);
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: darkMaroon,
                foregroundColor: Colors.white,
              ),
              child: const Text('Log Out'),
            ),
          ],
        );
      },
    );

    if (logout != true || !mounted) {
      return;
    }

    Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }
}

// Data holder for one item in the floating nav bar.
class _NavItem {
  const _NavItem(this.icon, this.activeIcon, this.label);

  final IconData icon;
  final IconData activeIcon;
  final String label;
}

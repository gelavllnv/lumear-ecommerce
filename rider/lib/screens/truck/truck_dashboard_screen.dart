import 'package:flutter/material.dart';
import 'package:flutter_application_1/models/rider_profile.dart';
import 'package:flutter_application_1/models/truck_rider_profile.dart';

import 'trip_details_screen.dart';
import 'alerts_screen.dart';
import 'truck_info_screen.dart';
import 'profile_screen.dart';
import 'truck_handover_preview_screen.dart';

class TruckDashboardScreen extends StatefulWidget {
  const TruckDashboardScreen({super.key});

  static const Color darkMaroon = Color(0xFF671B1D);
  static const Color maroon = Color(0xFF873632);
  static const Color brown = Color(0xFF9E7161);
  static const Color cream = Color(0xFFFFF8E7);
  static const Color paleCream = Color(0xFFFFFDF5);
  static const Color border = Color(0xFFE8D6C3);

  @override
  State<TruckDashboardScreen> createState() => _TruckDashboardScreenState();
}

class _TruckDashboardScreenState extends State<TruckDashboardScreen> {
  static const Color _darkMaroon = Color(0xFF62191C);
  static const Color _maroon = Color(0xFF873632);
  static const Color _brownBeige = Color(0xFF9E7161);
  static const Color _cream = Color(0xFFF3E9E1);
  static const Color _line = Color(0xFFE8D6C3);
  static const double _radiusXl = 28;

  static List<BoxShadow> get _shadowFloating => [
    BoxShadow(
      color: _darkMaroon.withValues(alpha: 0.36),
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
    _NavItem(Icons.route_outlined, Icons.route_rounded, 'Trips'),
    _NavItem(
      Icons.local_shipping_outlined,
      Icons.local_shipping_rounded,
      'Truck Info',
    ),
    _NavItem(
      Icons.notifications_active_outlined,
      Icons.notifications_active_rounded,
      'Alerts',
    ),
    _NavItem(Icons.person_outline, Icons.person_rounded, 'Profile'),
  ];

  bool isAvailable = true;
  final String tripId = 'TRP-2301';
  final String origin = 'Victoria Hub';
  final String destination = 'Calamba Logistics Hub';
  final String eta = '3:15 PM';
  final String cargo = 'Electronics';
  final int parcelCount = 24;

  final int _activeTrips = 1;
  final int _pendingHandovers = 3;

  final List<Map<String, dynamic>> recentTrips = [
    {'id': 'TRP-2298', 'route': 'Victoria → Los Baños', 'status': 'Completed'},
    {'id': 'TRP-2294', 'route': 'Victoria → San Pablo', 'status': 'Pending'},
    {'id': 'TRP-2289', 'route': 'Calamba → Batangas', 'status': 'Completed'},
  ];

  int get _completedTrips =>
      recentTrips.where((t) => t['status'] == 'Completed').length;

  int _selectedIndex = 0;
  bool _pageVisible = true;
  bool _isNavigating = false;
  late TruckRiderProfile _profile;

  @override
  void initState() {
    super.initState();
    _profile = _buildProfileFromRider();
  }

  TruckRiderProfile _buildProfileFromRider() {
    return TruckRiderProfile(
      riderId: RiderProfile.riderId,
      fullName: RiderProfile.name,
      email: RiderProfile.email,
      phone: RiderProfile.phone,
      birthday: _parseBirthday(RiderProfile.birthday),
      age: RiderProfile.age ?? 0,
      region: RiderProfile.region,
      province: RiderProfile.province,
      municipality: RiderProfile.municipality,
      barangay: RiderProfile.barangay,
      street: RiderProfile.street,
      houseUnit: RiderProfile.houseUnit,
      vehicleType: RiderProfile.vehicleType,
      licenseNumber: RiderProfile.licenseNumber,
      plateNumber: RiderProfile.plateNumber,
      accountStatus: 'Active',
    );
  }

  DateTime _parseBirthday(String text) {
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
    final t = text.trim();
    final match = RegExp(r'^([A-Za-z]+)\s+(\d{1,2}),\s*(\d{4})$').firstMatch(t);
    if (match != null) {
      final index = months.indexOf(match.group(1)!);
      if (index >= 0) {
        return DateTime(
          int.parse(match.group(3)!),
          index + 1,
          int.parse(match.group(2)!),
        );
      }
    }
    final iso = DateTime.tryParse(t);
    if (iso != null) return iso;
    return DateTime(DateTime.now().year - 18);
  }

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

  void _openTripDetails() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const TripDetailsScreen()),
    );
  }

  Future<void> _openHandoverReport() async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (_) => TruckHandoverPreviewScreen(
          tripId: tripId,
          origin: origin,
          destination: destination,
          eta: eta,
          cargo: cargo,
          totalParcels: parcelCount,
          handedOverParcels: 22,
          failedParcels: 1,
          remainingParcels: 1,
          receiverName: 'Not yet recorded',
          remarks: 'Two parcels require verification.',
          handoverConfirmed: false,
        ),
      ),
    );
  }

  Future<void> _openHistoryScreen() async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(builder: (_) => _buildHistoryScreen()),
    );
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      _buildHomeScreen(),
      const TruckInfoScreen(),
      const AlertsScreen(),
      ProfileScreen(
        profile: _profile,
        onProfileChanged: (updated) => setState(() => _profile = updated),
      ),
    ];
    final safeIndex = _selectedIndex.clamp(0, pages.length - 1);

    return Scaffold(
      backgroundColor: _cream,
      body: Stack(
        children: [
          Positioned.fill(
            child: AnimatedOpacity(
              opacity: _pageVisible ? 1 : 0,
              duration: Duration(milliseconds: _pageVisible ? 260 : 160),
              curve: _pageVisible ? Curves.easeOutCubic : Curves.easeInCubic,
              child: AnimatedScale(
                scale: _pageVisible ? 1 : 0.985,
                duration: Duration(milliseconds: _pageVisible ? 280 : 160),
                curve: _pageVisible ? Curves.easeOutCubic : Curves.easeInCubic,
                child: IndexedStack(index: safeIndex, children: pages),
              ),
            ),
          ),
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

  Widget _buildFloatingNavigation(int selectedIndex) {
    return Container(
      height: 70,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: _darkMaroon,
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
                  scale: isSelected ? 1 : 0.35,
                  duration: const Duration(milliseconds: 280),
                  curve: Curves.easeOutBack,
                  child: AnimatedOpacity(
                    opacity: isSelected ? 1 : 0,
                    duration: const Duration(milliseconds: 170),
                    child: Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        color: _cream,
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
                  scale: isSelected ? 1.10 : 1,
                  duration: const Duration(milliseconds: 280),
                  curve: Curves.easeOutBack,
                  child: AnimatedSwitcher(
                    duration: const Duration(milliseconds: 180),
                    transitionBuilder: (child, animation) => ScaleTransition(
                      scale: animation,
                      child: FadeTransition(opacity: animation, child: child),
                    ),
                    child: Icon(
                      isSelected ? item.activeIcon : item.icon,
                      key: ValueKey(isSelected),
                      color: isSelected
                          ? _darkMaroon
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

  Widget _buildHomeScreen() {
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: () async {
          if (mounted) setState(() {});
        },
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
                          color: _brownBeige,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 5),
                      const Text(
                        'Truck Management',
                        style: TextStyle(
                          fontSize: 23,
                          color: _darkMaroon,
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
                    color: _darkMaroon,
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
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [_darkMaroon, _maroon],
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
                      _statusBadge(isAvailable ? 'Available' : 'Unavailable'),
                    ],
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Truck Delivery',
                    style: TextStyle(
                      fontSize: 25,
                      color: Colors.white,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Long-distance hub-to-hub handover',
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
                          'Total parcels on this trip',
                          style: TextStyle(color: Colors.white),
                        ),
                      ),
                      Text(
                        '$parcelCount',
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
            _sectionTitle('Assigned Trip'),
            const SizedBox(height: 12),
            _buildAssignedTripCard(),
            const SizedBox(height: 14),
            _buildAvailabilityCard(),
            const SizedBox(height: 24),
            _sectionTitle('Handover Overview'),
            const SizedBox(height: 12),
            _buildOverviewSection(),
            const SizedBox(height: 24),
            _sectionTitle('Quick Actions'),
            const SizedBox(height: 12),
            _buildQuickActions(),
            const SizedBox(height: 24),
            Row(
              children: [
                const Expanded(
                  child: Text(
                    'Recent Handover Trips',
                    style: TextStyle(
                      color: _darkMaroon,
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                TextButton(
                  onPressed: _openHistoryScreen,
                  child: const Text(
                    'View All',
                    style: TextStyle(color: _maroon),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            ...recentTrips.map(_buildRecentTripCard),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(15),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(15),
                border: Border.all(color: _line),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.info_outline, color: _brownBeige),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Demo mode: sample trip information is stored in memory. '
                      'Real trip assignments and handover reports require '
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

  Widget _buildAssignedTripCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: _line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: _maroon.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.local_shipping_rounded,
                  color: _maroon,
                  size: 26,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Current Assignment',
                      style: TextStyle(color: Colors.black54, fontSize: 12),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      tripId,
                      style: const TextStyle(
                        color: _darkMaroon,
                        fontWeight: FontWeight.bold,
                        fontSize: 19,
                      ),
                    ),
                  ],
                ),
              ),
              _statusBadge('Assigned'),
            ],
          ),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 15),
            child: Divider(height: 1, color: Color(0xFFF1E6D9)),
          ),
          _buildTripLocationRow(
            icon: Icons.radio_button_checked,
            iconColor: _brownBeige,
            label: 'ORIGIN',
            value: origin,
          ),
          Padding(
            padding: const EdgeInsets.only(left: 10),
            child: Container(width: 2, height: 22, color: _line),
          ),
          _buildTripLocationRow(
            icon: Icons.location_on,
            iconColor: _darkMaroon,
            label: 'DESTINATION',
            value: destination,
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _buildSmallDetail(
                  icon: Icons.access_time,
                  label: 'Estimated Arrival',
                  value: eta,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _buildSmallDetail(
                  icon: Icons.inventory_2_outlined,
                  label: 'Parcels',
                  value: '$parcelCount items',
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          _buildSmallDetail(
            icon: Icons.category_outlined,
            label: 'Cargo Type',
            value: cargo,
          ),
          const SizedBox(height: 18),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton.icon(
              onPressed: _openTripDetails,
              icon: const Icon(Icons.play_arrow_rounded),
              label: const Text('Start Trip'),
              style: ElevatedButton.styleFrom(
                backgroundColor: _darkMaroon,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
                textStyle: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTripLocationRow({
    required IconData icon,
    required Color iconColor,
    required String label,
    required String value,
  }) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Icon(icon, size: 21, color: iconColor),
      const SizedBox(width: 10),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: const TextStyle(
                fontSize: 10,
                letterSpacing: 0.7,
                color: Colors.black54,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 3),
            Text(
              value,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: _darkMaroon,
              ),
            ),
          ],
        ),
      ),
    ],
  );

  Widget _buildSmallDetail({
    required IconData icon,
    required String label,
    required String value,
  }) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Icon(icon, size: 19, color: _maroon),
      const SizedBox(width: 8),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: const TextStyle(fontSize: 11, color: Colors.black54),
            ),
            const SizedBox(height: 3),
            Text(
              value,
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.bold,
                color: _darkMaroon,
              ),
            ),
          ],
        ),
      ),
    ],
  );

  Widget _buildAvailabilityCard() {
    final accent = isAvailable ? const Color(0xFF287544) : Colors.grey.shade600;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 13),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: _line),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: accent.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              Icons.power_settings_new_rounded,
              color: accent,
              size: 24,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Driver Availability',
                  style: TextStyle(
                    color: _darkMaroon,
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  isAvailable
                      ? 'You are available for assignments.'
                      : 'You are currently unavailable.',
                  style: const TextStyle(fontSize: 11, color: Colors.black54),
                ),
                const SizedBox(height: 5),
                Text(
                  isAvailable ? 'AVAILABLE' : 'UNAVAILABLE',
                  style: TextStyle(
                    color: accent,
                    fontWeight: FontWeight.bold,
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          Switch(
            value: isAvailable,
            activeThumbColor: Colors.white,
            activeTrackColor: _maroon,
            onChanged: (value) {
              setState(() => isAvailable = value);
              _showMessage(
                value
                    ? 'Availability set to Available (local only).'
                    : 'Availability set to Unavailable (local only).',
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _buildOverviewSection() => Column(
    children: [
      Row(
        children: [
          Expanded(
            child: _buildStatCard(
              title: 'Active Trips',
              count: _activeTrips,
              icon: Icons.route_outlined,
              color: const Color(0xFF245CA6),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: _buildStatCard(
              title: 'Pending Handovers',
              count: _pendingHandovers,
              icon: Icons.inventory_2_outlined,
              color: const Color(0xFFB4570A),
            ),
          ),
        ],
      ),
      const SizedBox(height: 12),
      _buildCompletedCard(),
    ],
  );

  Widget _buildStatCard({
    required String title,
    required int count,
    required IconData icon,
    required Color color,
  }) => Container(
    height: 100,
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: _line),
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
            color: _darkMaroon,
            fontWeight: FontWeight.w600,
            fontSize: 13,
          ),
        ),
      ],
    ),
  );

  Widget _buildCompletedCard() {
    const color = Color(0xFF287544);
    final total = recentTrips.length;
    final progress = total == 0 ? 0.0 : _completedTrips / total;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: _line),
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
                      'Completed Trips',
                      style: TextStyle(
                        color: _darkMaroon,
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '$_completedTrips of $total recent trips completed',
                      style: const TextStyle(
                        color: Colors.black54,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              Text(
                '$_completedTrips',
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

  // ---------------------------------------------------------------
  // QUICK ACTIONS (HORIZONTAL)
  // ---------------------------------------------------------------
  Widget _buildQuickActions() {
    final actions = <_QuickAction>[
      _QuickAction(
        icon: Icons.route_outlined,
        title: 'Trip Details',
        subtitle: 'Route, stops and parcel handovers',
        color: const Color(0xFF245CA6),
        badgeCount: _pendingHandovers,
        onTap: _openTripDetails,
      ),
      _QuickAction(
        icon: Icons.summarize_outlined,
        title: 'Generate Report',
        subtitle: 'Preview and print handover report',
        color: const Color(0xFF6548A5),
        onTap: _openHandoverReport,
      ),
      _QuickAction(
        icon: Icons.local_shipping_outlined,
        title: 'Truck Info',
        subtitle: 'Vehicle details and capacity',
        color: const Color(0xFFB4570A),
        onTap: () => _openTab(1),
      ),
      _QuickAction(
        icon: Icons.notifications_active_outlined,
        title: 'Alerts',
        subtitle: 'Trip updates and notifications',
        color: const Color(0xFF287544),
        onTap: () => _openTab(2),
      ),
      _QuickAction(
        icon: Icons.history,
        title: 'Trip History',
        subtitle: 'Review completed handover trips',
        color: const Color(0xFF6548A5),
        onTap: _openHistoryScreen,
      ),
      _QuickAction(
        icon: Icons.person_outline,
        title: 'Rider Profile',
        subtitle: 'Your profile and vehicle information',
        color: _darkMaroon,
        onTap: () => _openTab(3),
      ),
    ];

    return SizedBox(
      height: 150,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: actions.length,
        separatorBuilder: (context, index) => const SizedBox(width: 12),
        itemBuilder: (context, index) => _buildActionCard(actions[index]),
      ),
    );
  }

  Widget _buildActionCard(_QuickAction action) => Container(
    width: 150,
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: _line),
    ),
    child: Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: action.onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
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
                  const Spacer(),
                  if (action.badgeCount > 0)
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 3,
                      ),
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
                ],
              ),
              const Spacer(),
              Text(
                action.title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: _darkMaroon,
                  fontWeight: FontWeight.w700,
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                action.subtitle,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.black54,
                  fontSize: 11,
                  height: 1.3,
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );

  Widget _buildRecentTripCard(Map<String, dynamic> trip) {
    final status = trip['status'] as String;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: _line),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(17),
          onTap: () => _showTripDialog(trip),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: _maroon.withValues(alpha: 0.10),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.local_shipping_outlined,
                    color: _maroon,
                    size: 22,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        trip['id'] as String,
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                          color: _darkMaroon,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        trip['route'] as String,
                        style: const TextStyle(
                          fontSize: 11.5,
                          color: Colors.black54,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 6),
                _statusBadge(status),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _showTripDialog(Map<String, dynamic> trip) async {
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: _cream,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22)),
        title: Text(
          trip['id'] as String,
          style: const TextStyle(
            color: _darkMaroon,
            fontWeight: FontWeight.w800,
            fontSize: 17,
          ),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Route: ${trip['route']}'),
            const SizedBox(height: 8),
            Text('Status: ${trip['status']}'),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Close', style: TextStyle(color: _maroon)),
          ),
        ],
      ),
    );
  }

  Widget _buildHistoryScreen() {
    final completed = recentTrips
        .where((t) => t['status'] == 'Completed')
        .toList();
    return Scaffold(
      backgroundColor: _cream,
      appBar: AppBar(
        backgroundColor: _darkMaroon,
        foregroundColor: Colors.white,
        title: const Text('Trip History'),
      ),
      body: completed.isEmpty
          ? ListView(
              padding: const EdgeInsets.all(16),
              children: [_emptyMessage('Completed trips will appear here.')],
            )
          : ListView(
              padding: const EdgeInsets.all(16),
              children: completed.map(_buildRecentTripCard).toList(),
            ),
    );
  }

  Widget _emptyMessage(String message) => Container(
    width: double.infinity,
    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 35),
    margin: const EdgeInsets.only(bottom: 12),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
    ),
    child: Column(
      children: [
        const Icon(Icons.inventory_2_outlined, size: 42, color: _brownBeige),
        const SizedBox(height: 12),
        Text(
          message,
          textAlign: TextAlign.center,
          style: const TextStyle(color: Colors.black54),
        ),
      ],
    ),
  );

  String _greeting() {
    final hour = DateTime.now().hour;
    final name = RiderProfile.name.trim().isEmpty
        ? 'Driver'
        : RiderProfile.name.trim().split(' ').first;
    if (hour < 12) return 'Good morning, $name!';
    if (hour < 17) return 'Good afternoon, $name!';
    return 'Good evening, $name!';
  }

  Widget _sectionTitle(String title) => Text(
    title,
    style: const TextStyle(
      color: _darkMaroon,
      fontSize: 18,
      fontWeight: FontWeight.bold,
    ),
  );

  Widget _statusBadge(String status) {
    Color foreground;
    Color background;
    switch (status) {
      case 'Completed':
        foreground = const Color(0xFF287544);
        background = foreground.withValues(alpha: 0.12);
        break;
      case 'Pending':
        foreground = const Color(0xFFB4570A);
        background = foreground.withValues(alpha: 0.12);
        break;
      case 'In Transit':
        foreground = const Color(0xFF245CA6);
        background = foreground.withValues(alpha: 0.12);
        break;
      case 'Available':
        foreground = const Color(0xFFB9F6CA);
        background = Colors.white.withValues(alpha: 0.15);
        break;
      case 'Unavailable':
        foreground = Colors.white70;
        background = Colors.white.withValues(alpha: 0.15);
        break;
      default:
        foreground = _maroon;
        background = _maroon.withValues(alpha: 0.10);
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        status,
        style: TextStyle(
          color: foreground,
          fontSize: 10.5,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: _darkMaroon,
          behavior: SnackBarBehavior.floating,
          margin: const EdgeInsets.fromLTRB(16, 0, 16, 110),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      );
  }
}

class _NavItem {
  const _NavItem(this.icon, this.activeIcon, this.label);
  final IconData icon;
  final IconData activeIcon;
  final String label;
}

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

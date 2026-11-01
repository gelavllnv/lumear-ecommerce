import 'package:flutter/material.dart';
import 'package:audioplayers/audioplayers.dart';

import 'qr_scanner_screen.dart';

import 'package:flutter_application_1/models/rider_profile.dart';
import 'package:flutter_application_1/models/parcel_store.dart';

import 'package:flutter_application_1/screens/delivers_screen.dart';

import 'package:flutter_application_1/screens/delivery_details_screen.dart';
import 'package:flutter_application_1/screens/earnings_screen.dart';
import 'package:flutter_application_1/screens/help_support_screen.dart';
import 'package:flutter_application_1/screens/buyer_chat_list_screen.dart';
import 'package:flutter_application_1/screens/rider_daily_report_screen.dart';

import 'profile_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  // ============================================================
  // DESIGN SYSTEM — COLORS
  // ============================================================

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color textMuted = Color(0xFF8B6F63);

  static const Color success = Color(0xFF2E7D32);
  static const Color warning = Color(0xFFC77700);
  static const Color danger = Color(0xFFB3261E);

  // ============================================================
  // VEHICLE AND JOB TYPE
  // ============================================================

  String get _vehicleType {
    return RiderProfile.normalizedVehicleType.trim().toLowerCase();
  }

  String get _jobType {
    return RiderProfile.jobType.trim().toLowerCase().replaceAll('_', '-');
  }

  bool get _isHubToHub {
    return _vehicleType == 'truck' &&
        (_jobType == 'hub-to-hub' ||
            _jobType == 'hub to hub' ||
            _jobType == 'hub transfer');
  }

  // Not displayed yet. Handy for an app bar / Truck Management screen later.
  // ignore: unused_element
  String get _dashboardTitle {
    if (_isHubToHub) return 'Truck Management';

    switch (_vehicleType) {
      case 'van':
      case 'delivery van':
        return 'Van Delivery Dashboard';

      case 'truck':
      case 'delivery truck':
        return 'Truck Delivery Dashboard';

      case 'motor':
      case 'motorcycle':
      case 'motorbike':
        return 'Motor Delivery Dashboard';

      default:
        return 'Rider Dashboard';
    }
  }

  String get _welcomeMessage {
    if (_isHubToHub) return 'Good day, Truck Driver!';

    switch (_vehicleType) {
      case 'van':
      case 'delivery van':
        return 'Good day, Van Driver!';

      case 'truck':
      case 'delivery truck':
        return 'Good day, Truck Driver!';

      case 'motor':
      case 'motorcycle':
      case 'motorbike':
        return 'Good day, Motor Rider!';

      default:
        return 'Good day!';
    }
  }

  // NOTE: these use a real newline ('\n'), so no replaceAll is needed
  // when displaying them.
  String get _heroTitle {
    if (_isHubToHub) return 'Manage Your\nHub-to-Hub Trips';

    switch (_vehicleType) {
      case 'van':
      case 'delivery van':
        return 'Van Delivery\nMade Easier';

      case 'truck':
      case 'delivery truck':
        return 'Truck Delivery\nMade Easier';

      case 'motor':
      case 'motorcycle':
      case 'motorbike':
        return 'Motor Delivery\nMade Easier';

      default:
        return "Let's Deliver\nA Better Tomorrow";
    }
  }

  String get _heroSubtitle {
    if (_isHubToHub) {
      return 'Manage your assigned trips and parcel handovers.';
    }

    switch (_vehicleType) {
      case 'van':
      case 'delivery van':
        return 'Manage your van deliveries and routes.';

      case 'truck':
      case 'delivery truck':
        return 'Manage your truck deliveries and routes.';

      case 'motor':
      case 'motorcycle':
      case 'motorbike':
        return 'Manage your motor deliveries and routes.';

      default:
        return 'Every delivery brings people closer.';
    }
  }

  // ============================================================
  // DESIGN SYSTEM — SHAPE & ELEVATION
  // ============================================================

  static const double radiusSm = 14;
  static const double radiusMd = 18;
  static const double radiusLg = 24;
  static const double radiusXl = 28;

  static List<BoxShadow> get shadowSoft => [
    BoxShadow(
      color: darkMaroon.withValues(alpha: 0.06),
      blurRadius: 18,
      offset: const Offset(0, 6),
    ),
  ];

  static List<BoxShadow> get shadowFloating => [
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

  static BoxDecoration get cardDecoration => BoxDecoration(
    color: surface,
    borderRadius: BorderRadius.circular(radiusLg),
    border: Border.all(color: darkMaroon.withValues(alpha: 0.06)),
    boxShadow: shadowSoft,
  );

  // ============================================================
  // NAVIGATION
  // ============================================================

  int _currentIndex = 0;
  bool _pageVisible = true;
  bool _isNavigating = false;

  // ============================================================
  // STATES
  // ============================================================

  bool _isStartingDelivery = false;
  bool _isRefreshingHome = false;
  bool _isAvailable = true;

  int _unreadNotifications = 0;

  final List<RiderNotification> _notifications = [];

  final AudioPlayer _notificationPlayer = AudioPlayer();

  OverlayEntry? _topNotificationEntry;

  // ============================================================
  // PARCEL GETTERS
  // ============================================================

  ParcelData? get _currentParcel {
    return ParcelStore.currentParcel.value;
  }

  bool get _isParcelPending {
    return _currentParcel?.status == 'Pending';
  }

  bool get _isParcelAccepted {
    final status = _currentParcel?.status;

    return status == 'Heading to Pickup' ||
        status == 'Arrived at Pickup' ||
        status == 'Parcel Picked Up' ||
        status == 'Out for Delivery' ||
        status == 'Arrived at Destination';
  }

  bool get _isParcelCompleted {
    return _currentParcel?.status == 'Delivered';
  }

  bool get _isParcelFailed {
    return _currentParcel?.status == 'Delivery Failed';
  }

  // Dashboard statistics are derived from the same ParcelStore used by Pickups.
  bool _isToday(DateTime? date) {
    if (date == null) return false;
    final now = DateTime.now();
    return date.year == now.year &&
        date.month == now.month &&
        date.day == now.day;
  }

  int get _todayDeliveredCount => ParcelStore.parcels.value
      .where((p) => p.status == 'Delivered' && _isToday(p.deliveredAt))
      .length;

  int get _todayFailedCount => ParcelStore.parcels.value
      .where(
        (p) => p.status == 'Delivery Failed' && _isToday(p.statusUpdatedAt),
      )
      .length;

  int get _pickupCount => ParcelStore.parcels.value
      .where((p) => p.status == 'To Pick Up' || p.status == 'Pending')
      .length;

  double get _todayCodCollected => ParcelStore.parcels.value
      .where((p) => p.status == 'Delivered' && _isToday(p.deliveredAt))
      .fold<double>(0, (sum, p) => sum + p.codAmount);

  double get _todayRiderEarnings =>
      ParcelStore.earnedParcels.value
          .where((p) => _isToday(p.deliveredAt))
          .length *
      ParcelStore.earningPerDelivery;

  // ============================================================
  // INIT
  // ============================================================

  @override
  void initState() {
    super.initState();

    ParcelStore.currentParcel.addListener(_onParcelChanged);
  }

  // ============================================================
  // DISPOSE
  // ============================================================

  @override
  void dispose() {
    ParcelStore.currentParcel.removeListener(_onParcelChanged);

    _topNotificationEntry?.remove();
    _topNotificationEntry = null;

    _notificationPlayer.dispose();

    super.dispose();
  }

  void _onParcelChanged() {
    if (!mounted) return;

    setState(() {});
  }

  // ============================================================
  // NAVIGATION
  // ============================================================

  Future<void> _goToPage(int index) async {
    if (_currentIndex == index || _isNavigating) {
      return;
    }

    _isNavigating = true;

    setState(() {
      _pageVisible = false;
    });

    await Future.delayed(const Duration(milliseconds: 160));

    if (!mounted) return;

    setState(() {
      _currentIndex = index;
    });

    await Future.delayed(const Duration(milliseconds: 100));

    if (!mounted) return;

    setState(() {
      _pageVisible = true;
    });

    await Future.delayed(const Duration(milliseconds: 260));

    if (!mounted) return;

    _isNavigating = false;
  }

  // ============================================================
  // CURRENT PAGE
  // ============================================================

  Widget _getCurrentPage() {
    switch (_currentIndex) {
      case 0:
        return KeyedSubtree(
          key: const ValueKey('home'),
          child: _buildHomeScreen(),
        );

      case 1:
        return const KeyedSubtree(
          key: ValueKey('parcels'),
          child: DeliveryScreen(),
        );

      case 2:
        return KeyedSubtree(
          key: const ValueKey('scanner'),
          child: QrScannerScreen(
            onParcelScanned: (qrValue) async {
              await _handleParcelScanned(qrValue);
            },
          ),
        );

      case 3:
        return const KeyedSubtree(
          key: ValueKey('earnings'),
          child: EarningsScreen(),
        );

      case 4:
        return const KeyedSubtree(
          key: ValueKey('profile'),
          child: ProfileScreen(),
        );

      default:
        return KeyedSubtree(
          key: const ValueKey('home'),
          child: _buildHomeScreen(),
        );
    }
  }

  // ============================================================
  // NOTIFICATION SOUND
  // ============================================================

  Future<void> _playNotificationSound() async {
    try {
      await _notificationPlayer.stop();

      await _notificationPlayer.play(AssetSource('sounds/notification.mp3'));
    } catch (e) {
      debugPrint('Notification sound error: $e');
    }
  }

  // ============================================================
  // ADD NOTIFICATION
  // ============================================================

  Future<void> _addNotification({
    required String title,
    required String message,
    IconData icon = Icons.notifications,
    bool playSound = true,
  }) async {
    if (!mounted) return;

    setState(() {
      _notifications.insert(
        0,
        RiderNotification(
          title: title,
          message: message,
          icon: icon,
          createdAt: DateTime.now(),
        ),
      );

      _unreadNotifications++;
    });

    if (playSound) {
      await _playNotificationSound();
    }
  }

  // ============================================================
  // FORMAT NOTIFICATION TIME
  // ============================================================

  String _formatNotificationTime(DateTime date) {
    final now = DateTime.now();

    final difference = now.difference(date);

    if (difference.inSeconds < 60) {
      return 'Just now';
    }

    if (difference.inMinutes < 60) {
      return '${difference.inMinutes} min ago';
    }

    if (difference.inHours < 24) {
      return '${difference.inHours} hr ago';
    }

    return '${date.month}/${date.day}/${date.year}';
  }

  // ============================================================
  // SHOW NOTIFICATIONS
  // ============================================================

  void _showNotifications() {
    setState(() {
      _unreadNotifications = 0;
    });

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (context) {
        return Container(
          decoration: const BoxDecoration(
            color: cream,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(22, 14, 22, 24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Drag handle
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      margin: const EdgeInsets.only(bottom: 18),
                      decoration: BoxDecoration(
                        color: brownBeige.withValues(alpha: 0.35),
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                  ),

                  Row(
                    children: [
                      Container(
                        width: 42,
                        height: 42,
                        decoration: BoxDecoration(
                          color: darkMaroon,
                          borderRadius: BorderRadius.circular(13),
                        ),
                        child: const Icon(
                          Icons.notifications_rounded,
                          color: Colors.white,
                          size: 22,
                        ),
                      ),

                      const SizedBox(width: 13),

                      const Expanded(
                        child: Text(
                          'Notifications',
                          style: TextStyle(
                            color: darkMaroon,
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.3,
                          ),
                        ),
                      ),

                      IconButton(
                        style: IconButton.styleFrom(
                          backgroundColor: surface,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                        onPressed: () {
                          Navigator.pop(context);
                        },
                        icon: const Icon(
                          Icons.close_rounded,
                          color: darkMaroon,
                          size: 20,
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 18),

                  if (_notifications.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 44),
                      child: Center(
                        child: Column(
                          children: [
                            Container(
                              width: 72,
                              height: 72,
                              decoration: BoxDecoration(
                                color: surface,
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(
                                Icons.notifications_none_rounded,
                                size: 34,
                                color: brownBeige,
                              ),
                            ),

                            const SizedBox(height: 16),

                            const Text(
                              'No notifications yet',
                              style: TextStyle(
                                color: darkMaroon,
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                              ),
                            ),

                            const SizedBox(height: 4),

                            const Text(
                              'Delivery updates will appear here.',
                              style: TextStyle(
                                color: textMuted,
                                fontSize: 12.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                    )
                  else
                    ConstrainedBox(
                      constraints: BoxConstraints(
                        maxHeight: MediaQuery.of(context).size.height * 0.55,
                      ),
                      child: ListView.separated(
                        shrinkWrap: true,
                        itemCount: _notifications.length,
                        separatorBuilder: (_, __) {
                          return const SizedBox(height: 10);
                        },
                        itemBuilder: (context, index) {
                          final notification = _notifications[index];

                          return Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(15),
                            decoration: BoxDecoration(
                              color: surface,
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(
                                color: darkMaroon.withValues(alpha: 0.05),
                              ),
                            ),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Container(
                                  width: 42,
                                  height: 42,
                                  decoration: BoxDecoration(
                                    color: lightBeige.withValues(alpha: 0.5),
                                    borderRadius: BorderRadius.circular(13),
                                  ),
                                  child: Icon(
                                    notification.icon,
                                    color: darkMaroon,
                                    size: 20,
                                  ),
                                ),

                                const SizedBox(width: 13),

                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        notification.title,
                                        style: const TextStyle(
                                          color: darkMaroon,
                                          fontWeight: FontWeight.w700,
                                          fontSize: 13.5,
                                        ),
                                      ),

                                      const SizedBox(height: 4),

                                      Text(
                                        notification.message,
                                        style: const TextStyle(
                                          color: textMuted,
                                          fontSize: 12,
                                          height: 1.45,
                                        ),
                                      ),

                                      const SizedBox(height: 8),

                                      Text(
                                        _formatNotificationTime(
                                          notification.createdAt,
                                        ),
                                        style: TextStyle(
                                          color: brownBeige.withValues(
                                            alpha: 0.8,
                                          ),
                                          fontSize: 10.5,
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
                    ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  // ============================================================
  // TOP NOTIFICATION
  // ============================================================

  void _showTopNotification(String message, {Color? color, IconData? icon}) {
    _topNotificationEntry?.remove();
    _topNotificationEntry = null;

    final overlay = Overlay.of(context);

    late OverlayEntry entry;

    entry = OverlayEntry(
      builder: (context) {
        return Positioned(
          top: MediaQuery.of(context).padding.top + 10,
          left: 16,
          right: 16,
          child: Material(
            color: Colors.transparent,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              decoration: BoxDecoration(
                color: color ?? darkMaroon,
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.22),
                    blurRadius: 16,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: Row(
                children: [
                  Container(
                    width: 30,
                    height: 30,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.18),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(
                      icon ?? Icons.notifications_outlined,
                      color: Colors.white,
                      size: 17,
                    ),
                  ),

                  const SizedBox(width: 12),

                  Expanded(
                    child: Text(
                      message,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                        height: 1.3,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );

    _topNotificationEntry = entry;

    overlay.insert(entry);

    Future.delayed(const Duration(seconds: 3), () {
      if (_topNotificationEntry == entry) {
        entry.remove();

        _topNotificationEntry = null;
      }
    });
  }

  // ============================================================
  // QR SCANNED
  // ============================================================

  Future<void> _handleParcelScanned(String qrValue) async {
    final scannedQr = qrValue.trim();

    if (scannedQr.isEmpty) {
      _showTopNotification(
        'Invalid QR code.',
        color: danger,
        icon: Icons.error_outline,
      );

      return;
    }

    ParcelStore.createParcelFromQr(scannedQr);

    if (!mounted) return;

    final parcel = ParcelStore.currentParcel.value;

    if (parcel == null) {
      _showTopNotification(
        'Parcel not found.',
        color: danger,
        icon: Icons.error_outline,
      );

      return;
    }

    await _addNotification(
      title: 'Parcel Scan Successful',
      message:
          'Parcel ${parcel.parcelNumber} was scanned successfully and added to My Parcels.',
      icon: Icons.check_circle,
    );

    if (!mounted) return;

    _goToPage(0);

    if (!mounted) return;

    _showTopNotification(
      'Scan successful! ${parcel.parcelNumber} added to My Parcels.',
      color: success,
      icon: Icons.check_circle,
    );
  }

  // ============================================================
  // START DELIVERY
  // ============================================================

  Future<void> _startCurrentDelivery() async {
    final parcel = _currentParcel;

    if (parcel == null) return;
    if (_isStartingDelivery) return;

    setState(() {
      _isStartingDelivery = true;
    });

    await Future.delayed(const Duration(milliseconds: 300));

    if (!mounted) return;

    // QR scan already assigns the parcel to the rider.
    // Pressing Start Delivery begins the actual rider workflow.
    ParcelStore.updateStatus('Heading to Pickup');

    setState(() {
      _isStartingDelivery = false;
    });

    await _addNotification(
      title: 'Delivery Started',
      message:
          '${parcel.parcelNumber} is now active. Proceed to deliver to ${parcel.recipient}.',
      icon: Icons.delivery_dining,
      playSound: false,
    );

    if (!mounted) return;

    _showTopNotification(
      'Delivery started! Proceed to the delivery address.',
      icon: Icons.delivery_dining,
    );

    await _openDeliveryDetails();
  }

  // ============================================================
  // DELIVERY DETAILS
  // ============================================================

  Future<void> _openDeliveryDetails() async {
    final parcel = _currentParcel;

    if (parcel == null) return;

    // Was Navigator.push<String>, which only accepts a String result.
    // The "Return Parcel" flow in DeliveryDetailsScreen pops a
    // Map<String, dynamic> (status, returnReason, returnedAt, etc.),
    // so typing this push as <String> silently broke that result —
    // pressing "Return Parcel" from Home did nothing. Switched to
    // <dynamic> and now reading both shapes below.
    final dynamic result = await Navigator.push<dynamic>(
      context,
      MaterialPageRoute<dynamic>(
        builder: (context) {
          return DeliveryDetailsScreen(
            orderNumber: parcel.parcelNumber,
            store: parcel.hub,
            customer: parcel.recipient,
            address: parcel.address,
            distance: parcel.distance,
            fee: '₱${parcel.riderFee.toStringAsFixed(0)}',
            customerPhone: parcel.phone,
          );
        },
      ),
    );

    if (!mounted || result == null) return;

    // Newer DeliveryDetailsScreen pops a Map (e.g. {'status': 'completed', ...}
    // or {'status': 'returned', ...}); older versions popped a plain String
    // ('completed' / 'failed'). Normalize both into a single status string.
    final String? status = result is Map
        ? result['status'] as String?
        : (result is String ? result : null);

    // ==========================================================
    // DELIVERY COMPLETED
    // ==========================================================
    if (status == 'completed') {
      ParcelStore.updateStatus('Delivered');

      // Credit earnings once for each successful delivery.
      final completedParcel = ParcelStore.parcels.value.firstWhere(
        (p) => p.trackingNumber == parcel.trackingNumber,
        orElse: () => ParcelStore.currentParcel.value ?? parcel,
      );
      ParcelStore.awardSuccessfulDelivery(completedParcel);

      await _addNotification(
        title: 'Delivery Completed',
        message:
            '${parcel.parcelNumber} was successfully delivered to ${parcel.recipient}.',
        icon: Icons.check_circle_outline,
        playSound: false,
      );

      if (!mounted) return;

      _showTopNotification(
        '${parcel.parcelNumber} successfully delivered!',
        color: darkMaroon,
        icon: Icons.check_circle_outline,
      );

      return;
    }

    // ==========================================================
    // DELIVERY FAILED / RETURNED
    // ==========================================================
    if (status == 'failed' || status == 'returned') {
      ParcelStore.updateStatus('Delivery Failed');

      await _addNotification(
        title: 'Delivery Attempt Failed',
        message: '${parcel.parcelNumber} was moved to Failed deliveries.',
        icon: Icons.error_outline,
        playSound: false,
      );

      if (!mounted) return;

      _showTopNotification(
        '${parcel.parcelNumber} moved to Failed deliveries.',
        color: maroon,
        icon: Icons.error_outline,
      );
    }
  }

  // ============================================================
  // BUYER CHAT
  // ============================================================

  void _openBuyerChat() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const BuyerChatListScreen()),
    );
  }

  // ============================================================
  // DAILY PDF REPORT
  // ============================================================

  Future<void> _openDailyReport() async {
    await Navigator.push(
      context,
      MaterialPageRoute<void>(builder: (_) => const RiderDailyReportScreen()),
    );
  }

  // ============================================================
  // REFRESH HOME
  // ============================================================

  Future<void> _refreshHome() async {
    if (_isRefreshingHome) return;

    setState(() {
      _isRefreshingHome = true;
    });

    await Future.delayed(const Duration(milliseconds: 900));

    if (!mounted) return;

    setState(() {
      _isRefreshingHome = false;
    });
  }

  // ============================================================
  // MAIN BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      body: Stack(
        children: [
          // ====================================================
          // PAGE FADE OUT -> NAV POP -> FADE IN
          // ====================================================

          Positioned.fill(
            child: AnimatedOpacity(
              opacity: _pageVisible ? 1.0 : 0.0,
              duration: Duration(milliseconds: _pageVisible ? 260 : 160),
              curve: _pageVisible ? Curves.easeOutCubic : Curves.easeInCubic,
              child: AnimatedScale(
                scale: _pageVisible ? 1.0 : 0.985,
                duration: Duration(milliseconds: _pageVisible ? 280 : 160),
                curve: _pageVisible ? Curves.easeOutCubic : Curves.easeInCubic,
                child: KeyedSubtree(
                  key: ValueKey(_currentIndex),
                  child: _getCurrentPage(),
                ),
              ),
            ),
          ),

          // ====================================================
          // FLOATING NAVIGATION
          // ====================================================
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

  // ============================================================
  // FLOATING NAVIGATION
  // ============================================================

  Widget _buildFloatingNavigation() {
    return Container(
      height: 70,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: darkMaroon,
        borderRadius: BorderRadius.circular(radiusXl),
        border: Border.all(color: Colors.white.withValues(alpha: 0.28)),
        boxShadow: shadowFloating,
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: [
          _bottomNavItem(
            index: 0,
            icon: Icons.home_outlined,
            activeIcon: Icons.home_rounded,
          ),
          _bottomNavItem(
            index: 1,
            icon: Icons.inventory_2_outlined,
            activeIcon: Icons.inventory_2,
          ),
          _bottomNavItem(
            index: 2,
            icon: Icons.qr_code_scanner_rounded,
            activeIcon: Icons.qr_code_scanner_rounded,
          ),
          _bottomNavItem(
            index: 3,
            icon: Icons.account_balance_wallet_outlined,
            activeIcon: Icons.account_balance_wallet_rounded,
          ),
          _bottomNavItem(
            index: 4,
            icon: Icons.person_outline,
            activeIcon: Icons.person_rounded,
          ),
        ],
      ),
    );
  }

  // ============================================================
  // NAV ITEM
  // Cream pops out on the old item and pops in on the selected item
  // ============================================================

  Widget _bottomNavItem({
    required int index,
    required IconData icon,
    required IconData activeIcon,
  }) {
    final bool isSelected = _currentIndex == index;

    return InkWell(
      onTap: () {
        _goToPage(index);
      },
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
                      color: cream,
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
                    isSelected ? activeIcon : icon,
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
    );
  }

  // ============================================================
  // HOME
  // ============================================================

  Widget _buildHomeScreen() {
    final parcel = _currentParcel;

    return SafeArea(
      child: Stack(
        children: [
          RefreshIndicator(
            onRefresh: _refreshHome,
            color: Colors.transparent,
            backgroundColor: Colors.transparent,
            elevation: 0,
            displacement: 56,
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 104),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // ==========================================
                  // HEADER
                  // ==========================================

                  _EntranceAnimation(
                    delay: 30,
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(2.5),
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: darkMaroon.withValues(alpha: 0.15),
                              width: 1.5,
                            ),
                          ),
                          child: CircleAvatar(
                            radius: 25,
                            backgroundColor: lightBeige,
                            backgroundImage: RiderProfile.profileImage != null
                                ? FileImage(RiderProfile.profileImage!)
                                : null,
                            child: RiderProfile.profileImage == null
                                ? const Icon(
                                    Icons.person,
                                    color: darkMaroon,
                                    size: 26,
                                  )
                                : null,
                          ),
                        ),

                        const SizedBox(width: 13),

                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              // Dynamic welcome message (vehicle/job type)
                              Text(
                                _welcomeMessage,
                                style: const TextStyle(
                                  color: textMuted,
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),

                              const SizedBox(height: 1),

                              Text(
                                RiderProfile.name,
                                style: const TextStyle(
                                  color: darkMaroon,
                                  fontSize: 20,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: -0.3,
                                ),
                              ),
                            ],
                          ),
                        ),

                        Stack(
                          clipBehavior: Clip.none,
                          children: [
                            Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                InkWell(
                                  onTap: () {
                                    setState(() {
                                      _isAvailable = !_isAvailable;
                                    });
                                  },
                                  borderRadius: BorderRadius.circular(14),
                                  child: Container(
                                    height: 44,
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 10,
                                    ),
                                    decoration: BoxDecoration(
                                      color: _isAvailable
                                          ? success.withValues(alpha: 0.10)
                                          : textMuted.withValues(alpha: 0.10),
                                      borderRadius: BorderRadius.circular(14),
                                    ),
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Icon(
                                          Icons.circle,
                                          size: 8,
                                          color: _isAvailable
                                              ? const Color(0xFF62191C)
                                              : textMuted,
                                        ),
                                        const SizedBox(width: 6),
                                        Text(
                                          _isAvailable ? 'Online' : 'Offline',
                                          style: TextStyle(
                                            color: _isAvailable
                                                ? const Color(0xFF62191C)
                                                : textMuted,
                                            fontSize: 11,
                                            fontWeight: FontWeight.w700,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Container(
                                  decoration: BoxDecoration(
                                    color: surface,
                                    borderRadius: BorderRadius.circular(14),
                                    boxShadow: shadowSoft,
                                  ),
                                  child: IconButton(
                                    onPressed: _showNotifications,
                                    icon: const Icon(
                                      Icons.notifications_outlined,
                                      color: darkMaroon,
                                      size: 22,
                                    ),
                                  ),
                                ),
                              ],
                            ),

                            if (_unreadNotifications > 0)
                              Positioned(
                                right: 4,
                                top: 4,
                                child: Container(
                                  constraints: const BoxConstraints(
                                    minWidth: 18,
                                    minHeight: 18,
                                  ),
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 4,
                                  ),
                                  decoration: BoxDecoration(
                                    color: danger,
                                    borderRadius: BorderRadius.circular(20),
                                    border: Border.all(
                                      color: surface,
                                      width: 2,
                                    ),
                                  ),
                                  child: Center(
                                    child: Text(
                                      _unreadNotifications > 9
                                          ? '9+'
                                          : '$_unreadNotifications',
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 9,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 22),

                  // ==========================================
                  // HERO CARD (dynamic banner)
                  // ==========================================
                  _EntranceAnimation(
                    delay: 90,
                    child: Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(24),
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                          colors: [darkMaroon, maroon],
                        ),
                        borderRadius: BorderRadius.circular(radiusXl),
                        boxShadow: [
                          BoxShadow(
                            color: darkMaroon.withValues(alpha: 0.25),
                            blurRadius: 20,
                            offset: const Offset(0, 10),
                          ),
                        ],
                      ),
                      child: Stack(
                        children: [
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _heroTitle,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 25,
                                  fontWeight: FontWeight.w800,
                                  height: 1.2,
                                  letterSpacing: -0.4,
                                ),
                              ),

                              const SizedBox(height: 8),

                              Text(
                                _heroSubtitle,
                                style: TextStyle(
                                  color: Colors.white.withValues(alpha: 0.75),
                                  fontSize: 13,
                                ),
                              ),

                              const SizedBox(height: 22),

                              ElevatedButton.icon(
                                onPressed: () {
                                  _goToPage(2);
                                },
                                icon: const Icon(
                                  Icons.qr_code_scanner_rounded,
                                  size: 19,
                                ),
                                label: const Text(
                                  'Scan Parcel',
                                  style: TextStyle(fontWeight: FontWeight.w700),
                                ),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: Colors.white,
                                  foregroundColor: darkMaroon,
                                  elevation: 0,
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 20,
                                    vertical: 14,
                                  ),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(
                                      radiusSm,
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 22),

                  // ==========================================
                  // LIVE PARCEL & DAILY STATS
                  // ==========================================
                  _EntranceAnimation(
                    delay: 150,
                    child: Column(
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: _statCard(
                                icon: Icons.inventory_2_rounded,
                                iconColor: darkMaroon,
                                value: '$_pickupCount',
                                label: 'Pickups',
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: _statCard(
                                icon: Icons.check_circle_rounded,
                                iconColor: darkMaroon,
                                value: '$_todayDeliveredCount',
                                label: 'Delivered Today',
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(
                              child: _statCard(
                                icon: Icons.payments_rounded,
                                iconColor: darkMaroon,
                                value:
                                    '₱${_todayCodCollected.toStringAsFixed(0)}',
                                label: 'COD Collected Today',
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: _statCard(
                                icon: Icons.account_balance_wallet_rounded,
                                iconColor: maroon,
                                value:
                                    '₱${_todayRiderEarnings.toStringAsFixed(0)}',
                                label: 'Earnings Today',
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        SizedBox(
                          width: double.infinity,
                          child: OutlinedButton.icon(
                            onPressed: _openDailyReport,
                            icon: const Icon(Icons.picture_as_pdf_rounded),
                            label: const Text(
                              'Generate Daily PDF Report',
                              style: TextStyle(fontWeight: FontWeight.w800),
                            ),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: darkMaroon,
                              side: const BorderSide(color: darkMaroon),
                              padding: const EdgeInsets.symmetric(vertical: 15),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(radiusSm),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 28),

                  // ==========================================
                  // CURRENT DELIVERY
                  // ==========================================
                  _EntranceAnimation(
                    delay: 210,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _sectionHeader(
                          title: 'Current Delivery',
                          actionLabel: 'View All',
                          onAction: () {
                            _goToPage(1);
                          },
                        ),

                        const SizedBox(height: 12),

                        if (parcel == null)
                          _buildNoParcelCard()
                        else
                          _buildCurrentParcelCard(parcel),
                      ],
                    ),
                  ),

                  const SizedBox(height: 28),

                  // ==========================================
                  // QUICK ACTIONS
                  // ==========================================
                  _EntranceAnimation(
                    delay: 270,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _sectionHeader(title: 'Quick Actions'),

                        const SizedBox(height: 14),

                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            _quickAction(
                              icon: Icons.qr_code_scanner_rounded,
                              label: 'Scan QR',
                              primary: true,
                              onTap: () {
                                _goToPage(2);
                              },
                            ),

                            _quickAction(
                              icon: Icons.inventory_2_outlined,
                              label: 'Parcels',
                              onTap: () {
                                _goToPage(1);
                              },
                            ),

                            _quickAction(
                              icon: Icons.forum_outlined,
                              label: 'Buyer Chat',
                              onTap: _openBuyerChat,
                            ),

                            _quickAction(
                              icon: Icons.support_agent_rounded,
                              label: 'Support',
                              onTap: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => const HelpSupportScreen(),
                                  ),
                                );
                              },
                            ),

                            _quickAction(
                              icon: Icons.person_outline_rounded,
                              label: 'Profile',
                              onTap: () {
                                _goToPage(4);
                              },
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),
                ],
              ),
            ),
          ),

          if (_isRefreshingHome)
            const Positioned(
              top: 8,
              left: 0,
              right: 0,
              child: IgnorePointer(
                child: Center(child: _LumearRefreshDecoration()),
              ),
            ),
        ],
      ),
    );
  }

  // ============================================================
  // SECTION HEADER
  // ============================================================

  Widget _sectionHeader({
    required String title,
    String? actionLabel,
    VoidCallback? onAction,
  }) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          title,
          style: const TextStyle(
            color: darkMaroon,
            fontWeight: FontWeight.w800,
            fontSize: 18.5,
            letterSpacing: -0.3,
          ),
        ),

        if (actionLabel != null)
          TextButton(
            onPressed: onAction,
            style: TextButton.styleFrom(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              minimumSize: Size.zero,
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
            child: Row(
              children: [
                Text(
                  actionLabel,
                  style: const TextStyle(
                    color: maroon,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(width: 2),
                const Icon(
                  Icons.arrow_forward_ios_rounded,
                  size: 11,
                  color: maroon,
                ),
              ],
            ),
          ),
      ],
    );
  }

  // ============================================================
  // NO PARCEL
  // ============================================================

  Widget _buildNoParcelCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 36),
      decoration: cardDecoration,
      child: Column(
        children: [
          Container(
            width: 66,
            height: 66,
            decoration: BoxDecoration(
              color: lightBeige.withValues(alpha: 0.4),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.qr_code_scanner_rounded,
              color: darkMaroon,
              size: 30,
            ),
          ),

          const SizedBox(height: 16),

          const Text(
            'No Current Parcel',
            style: TextStyle(
              color: darkMaroon,
              fontSize: 16.5,
              fontWeight: FontWeight.w800,
            ),
          ),

          const SizedBox(height: 6),

          const Text(
            'Scan a parcel QR code to receive a new delivery.',
            textAlign: TextAlign.center,
            style: TextStyle(color: textMuted, fontSize: 12.5, height: 1.4),
          ),

          const SizedBox(height: 18),

          ElevatedButton.icon(
            onPressed: () {
              _goToPage(2);
            },
            icon: const Icon(Icons.qr_code_scanner_rounded, size: 18),
            label: const Text(
              'Scan QR',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
            style: ElevatedButton.styleFrom(
              backgroundColor: darkMaroon,
              foregroundColor: Colors.white,
              elevation: 0,
              padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 13),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(radiusSm),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // CURRENT PARCEL
  // ============================================================

  Widget _buildCurrentParcelCard(ParcelData parcel) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: cardDecoration,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: lightBeige.withValues(alpha: 0.45),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.inventory_2_rounded,
                  color: darkMaroon,
                  size: 22,
                ),
              ),

              const SizedBox(width: 12),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Parcel ${parcel.parcelNumber}',
                      style: const TextStyle(
                        color: darkMaroon,
                        fontSize: 16.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),

                    const SizedBox(height: 4),

                    _statusBadge(parcel.status),
                  ],
                ),
              ),

              Text(
                '₱${parcel.riderFee.toStringAsFixed(0)}',
                style: const TextStyle(
                  color: darkMaroon,
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.3,
                ),
              ),
            ],
          ),

          const SizedBox(height: 20),

          // Delivery destination only. There is no seller pickup task
          // on this dashboard, so the "Pick up at" section was removed.
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: cream.withValues(alpha: 0.6),
              borderRadius: BorderRadius.circular(16),
            ),
            child: _deliveryInfo(
              icon: Icons.location_on_rounded,
              label: 'Deliver to',
              title: parcel.recipient,
              subtitle: parcel.address,
            ),
          ),

          const SizedBox(height: 14),

          Row(
            children: [
              Expanded(
                child: _infoPill(label: 'Distance', value: parcel.distance),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _infoPill(
                  label: parcel.paymentMethod,
                  value: parcel.codAmount > 0
                      ? '₱${parcel.codAmount.toStringAsFixed(0)}'
                      : 'Paid',
                ),
              ),
            ],
          ),

          const SizedBox(height: 18),

          if (_isParcelPending)
            SizedBox(
              width: double.infinity,
              height: 54,
              child: ElevatedButton.icon(
                onPressed: _isStartingDelivery ? null : _startCurrentDelivery,
                icon: _isStartingDelivery
                    ? const SizedBox.shrink()
                    : const Icon(Icons.delivery_dining_rounded, size: 22),
                label: _isStartingDelivery
                    ? const SizedBox(
                        width: 21,
                        height: 21,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Text(
                        'Start Delivery',
                        style: TextStyle(
                          fontSize: 15.5,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: darkMaroon,
                  foregroundColor: Colors.white,
                  disabledBackgroundColor: brownBeige,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(radiusSm),
                  ),
                ),
              ),
            ),

          if (_isParcelAccepted)
            SizedBox(
              width: double.infinity,
              height: 54,
              child: ElevatedButton.icon(
                onPressed: _openDeliveryDetails,
                icon: const Icon(Icons.delivery_dining_rounded),
                label: const Text(
                  'Continue Delivery',
                  style: TextStyle(fontSize: 15.5, fontWeight: FontWeight.w800),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: darkMaroon,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(radiusSm),
                  ),
                ),
              ),
            ),

          if (_isParcelCompleted)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 14),
              decoration: BoxDecoration(
                color: success.withValues(alpha: 0.10),
                borderRadius: BorderRadius.circular(radiusSm),
                border: Border.all(color: success.withValues(alpha: 0.25)),
              ),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.check_circle_rounded, color: success, size: 20),

                  SizedBox(width: 8),

                  Text(
                    'Parcel Delivered',
                    style: TextStyle(
                      color: success,
                      fontWeight: FontWeight.w800,
                      fontSize: 13.5,
                    ),
                  ),
                ],
              ),
            ),

          if (_isParcelFailed)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 14),
              decoration: BoxDecoration(
                color: danger.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(radiusSm),
                border: Border.all(color: danger.withValues(alpha: 0.25)),
              ),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.error_outline_rounded, color: danger, size: 20),
                  SizedBox(width: 8),
                  Text(
                    'Delivery Attempt Failed',
                    style: TextStyle(
                      color: danger,
                      fontWeight: FontWeight.w800,
                      fontSize: 13.5,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  // ============================================================
  // STATUS BADGE
  // ============================================================

  Widget _statusBadge(String status) {
    final color = _getStatusColor(status);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
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
            status,
            style: TextStyle(
              color: color,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // INFO PILL
  // ============================================================

  Widget _infoPill({required String label, required String value}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 11),
      decoration: BoxDecoration(
        color: lightBeige.withValues(alpha: 0.35),
        borderRadius: BorderRadius.circular(13),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              color: textMuted,
              fontSize: 10,
              fontWeight: FontWeight.w600,
            ),
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 2),
          Text(
            value,
            style: const TextStyle(
              color: darkMaroon,
              fontWeight: FontWeight.w800,
              fontSize: 14,
            ),
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }

  // ============================================================
  // DELIVERY INFO
  // ============================================================

  Widget _deliveryInfo({
    required IconData icon,
    required String label,
    required String title,
    required String subtitle,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: darkMaroon, size: 19),

        const SizedBox(width: 11),

        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(
                  color: textMuted,
                  fontSize: 10,
                  fontWeight: FontWeight.w600,
                ),
              ),

              const SizedBox(height: 3),

              Text(
                title,
                style: const TextStyle(
                  color: darkMaroon,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                ),
              ),

              const SizedBox(height: 2),

              Text(
                subtitle,
                style: const TextStyle(
                  color: textMuted,
                  fontSize: 11.5,
                  height: 1.3,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  // ============================================================
  // STAT CARD
  // ============================================================

  Widget _statCard({
    required IconData icon,
    required Color iconColor,
    required String value,
    required String label,
  }) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: cardDecoration,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: iconColor.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: iconColor, size: 19),
          ),

          const SizedBox(height: 12),

          Text(
            value,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 21,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.4,
            ),
          ),

          const SizedBox(height: 2),

          Text(
            label,
            style: const TextStyle(
              color: textMuted,
              fontSize: 11.5,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // QUICK ACTION
  // ============================================================

  Widget _quickAction({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
    bool primary = false,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Column(
        children: [
          Container(
            width: primary ? 62 : 54,
            height: primary ? 62 : 54,
            decoration: BoxDecoration(
              color: primary ? darkMaroon : surface,
              borderRadius: BorderRadius.circular(19),
              boxShadow: primary
                  ? [
                      BoxShadow(
                        color: darkMaroon.withValues(alpha: 0.28),
                        blurRadius: 14,
                        offset: const Offset(0, 6),
                      ),
                    ]
                  : shadowSoft,
            ),
            child: Icon(
              icon,
              color: primary ? Colors.white : darkMaroon,
              size: primary ? 28 : 23,
            ),
          ),

          const SizedBox(height: 8),

          Text(
            label,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // STATUS COLOR
  // ============================================================

  Color _getStatusColor(String status) {
    switch (status) {
      case 'Pending':
        return warning;

      case 'Heading to Pickup':
        return Colors.blue.shade700;

      case 'Arrived at Pickup':
        return Colors.indigo;

      case 'Parcel Picked Up':
        return Colors.purple;

      case 'Out for Delivery':
        return Colors.deepPurple;

      case 'Arrived at Destination':
        return Colors.teal.shade700;

      case 'Delivered':
        return success;

      case 'Delivery Failed':
        return danger;

      default:
        return maroon;
    }
  }
}

// ============================================================
// SUBTLE ENTRANCE ANIMATION
// Used inside Home
// ============================================================

class _EntranceAnimation extends StatefulWidget {
  final Widget child;
  final int delay;

  const _EntranceAnimation({required this.child, required this.delay});

  @override
  State<_EntranceAnimation> createState() => _EntranceAnimationState();
}

class _EntranceAnimationState extends State<_EntranceAnimation> {
  bool _show = false;

  @override
  void initState() {
    super.initState();

    Future.delayed(Duration(milliseconds: widget.delay), () {
      if (!mounted) return;

      setState(() {
        _show = true;
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedOpacity(
      opacity: _show ? 1 : 0,
      duration: const Duration(milliseconds: 400),
      curve: Curves.easeOutCubic,
      child: AnimatedSlide(
        offset: _show ? Offset.zero : const Offset(0, 0.025),
        duration: const Duration(milliseconds: 400),
        curve: Curves.easeOutCubic,
        child: AnimatedScale(
          scale: _show ? 1 : 0.985,
          duration: const Duration(milliseconds: 400),
          curve: Curves.easeOutCubic,
          child: widget.child,
        ),
      ),
    );
  }
}

// ============================================================
// RIDER NOTIFICATION MODEL
// ============================================================

class RiderNotification {
  final String title;
  final String message;
  final IconData icon;
  final DateTime createdAt;

  RiderNotification({
    required this.title,
    required this.message,
    required this.icon,
    required this.createdAt,
  });
}

// ============================================================
// REFRESH DECORATION
// ============================================================

class _LumearRefreshDecoration extends StatelessWidget {
  const _LumearRefreshDecoration();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 112,
      height: 44,
      decoration: BoxDecoration(
        color: const Color(0xFF62191C),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.2),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: const Center(
        child: SizedBox(
          width: 19,
          height: 19,
          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
        ),
      ),
    );
  }
}

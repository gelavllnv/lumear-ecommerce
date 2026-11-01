import 'dart:io';

import 'package:flutter/material.dart';

import 'package:flutter_application_1/models/parcel_store.dart';

import 'package:flutter_application_1/services/rider_earnings_service.dart';

import 'package:flutter_application_1/screens/delivery_details_screen.dart';

class Parcel {
  final String parcelNumber;
  final String trackingNumber;
  final String hub;
  final String recipient;
  final String phone;
  final String address;
  final String parcelType;
  final String paymentMethod;
  final double codAmount;
  final double riderFee;
  final String distance;
  final double customerLatitude;
  final double customerLongitude;

  String status;
  DateTime? deliveredAt;
  String? receivedBy;
  String? proofImagePath;
  DateTime? returnedAt;
  String? returnReason;
  String? returnNote;
  DateTime? rescheduledAt;
  String? rescheduleNote;

  Parcel({
    required this.parcelNumber,
    required this.trackingNumber,
    required this.hub,
    required this.recipient,
    required this.phone,
    required this.address,
    required this.parcelType,
    required this.paymentMethod,
    required this.codAmount,
    required this.riderFee,
    required this.distance,
    required this.customerLatitude,
    required this.customerLongitude,
    required this.status,
    this.deliveredAt,
    this.receivedBy,
    this.proofImagePath,
    this.returnedAt,
    this.returnReason,
    this.returnNote,
    this.rescheduledAt,
    this.rescheduleNote,
  });
}

class DeliveryScreen extends StatefulWidget {
  const DeliveryScreen({super.key});

  @override
  State<DeliveryScreen> createState() => _DeliveryScreenState();
}

class _DeliveryScreenState extends State<DeliveryScreen> {
  // ============================================================
  // DESIGN SYSTEM — COLORS
  // Matches the dashboard screen's palette and tokens.
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

  static const double radiusSm = 14;
  static const double radiusMd = 18;
  static const double radiusLg = 22;

  static List<BoxShadow> get shadowSoft => [
    BoxShadow(
      color: darkMaroon.withValues(alpha: 0.06),
      blurRadius: 18,
      offset: const Offset(0, 6),
    ),
  ];

  static BoxDecoration get cardDecoration => BoxDecoration(
    color: surface,
    borderRadius: BorderRadius.circular(radiusLg),
    border: Border.all(color: darkMaroon.withValues(alpha: 0.06)),
    boxShadow: shadowSoft,
  );

  static const double _tabsHeight = 66;

  static const EdgeInsets _listPadding = EdgeInsets.only(
    top: _tabsHeight + 4,
    bottom: 120,
  );

  int _selectedTab = 0;

  final List<_TabInfo> _tabs = const [
    _TabInfo('Pickups', Icons.inventory_2_outlined),
    _TabInfo('Ongoing', Icons.local_shipping_outlined),
    _TabInfo('Delivered', Icons.check_circle_outline_rounded),
    _TabInfo('Returns', Icons.assignment_return_outlined),
    _TabInfo('Proof of Delivery', Icons.photo_camera_outlined),
  ];

  DateTime _selectedPodDate = DateTime.now();

  // ============================================================
  // PICKUPS
  // Newly scanned parcels go DIRECTLY here. No Pending section.
  // ============================================================

  final List<Parcel> _pickupParcels = [
    Parcel(
      parcelNumber: '#SPX10234',
      trackingNumber: 'SPXPH102345678',
      hub: 'San Pablo Delivery Hub',
      recipient: 'Juan Santos',
      phone: '0912 345 6789',
      address: 'Brgy. Maligaya, San Pablo City',
      parcelType: 'Small Parcel',
      paymentMethod: 'Cash on Delivery',
      codAmount: 1250,
      riderFee: 45,
      distance: '2.4 km',
      customerLatitude: 14.0648,
      customerLongitude: 121.3310,
      status: 'To Pick Up',
    ),
    Parcel(
      parcelNumber: '#SPX10235',
      trackingNumber: 'SPXPH102345679',
      hub: 'San Pablo Delivery Hub',
      recipient: 'Maria Reyes',
      phone: '0917 456 1234',
      address: 'Brgy. San Roque, San Pablo City',
      parcelType: 'Medium Parcel',
      paymentMethod: 'Paid',
      codAmount: 0,
      riderFee: 55,
      distance: '3.1 km',
      customerLatitude: 14.0720,
      customerLongitude: 121.3205,
      status: 'To Pick Up',
    ),
  ];

  final List<Parcel> _ongoingParcels = [
    Parcel(
      parcelNumber: '#SPX10230',
      trackingNumber: 'SPXPH102345670',
      hub: 'San Pablo Delivery Hub',
      recipient: 'Pedro Cruz',
      phone: '0998 234 5678',
      address: 'Brgy. San Antonio, San Pablo City',
      parcelType: 'Small Parcel',
      paymentMethod: 'Cash on Delivery',
      codAmount: 850,
      riderFee: 40,
      distance: '1.8 km',
      customerLatitude: 14.0615,
      customerLongitude: 121.3285,
      status: 'Out for Delivery',
    ),
  ];

  // Demo delivered records. New completed deliveries are inserted here.
  final List<Parcel> _deliveredParcels = [
    Parcel(
      parcelNumber: '#SPX10220',
      trackingNumber: 'SPXPH102345660',
      hub: 'San Pablo Delivery Hub',
      recipient: 'Ana Reyes',
      phone: '0918 765 4321',
      address: 'Brgy. San Rafael, San Pablo City',
      parcelType: 'Medium Parcel',
      paymentMethod: 'Paid',
      codAmount: 0,
      riderFee: 65,
      distance: '4.2 km',
      customerLatitude: 14.0600,
      customerLongitude: 121.3200,
      status: 'Delivered',
      deliveredAt: DateTime(2026, 9, 16, 10, 35),
      receivedBy: 'Ana Reyes',
    ),
    Parcel(
      parcelNumber: '#SPX10219',
      trackingNumber: 'SPXPH102345659',
      hub: 'San Pablo Delivery Hub',
      recipient: 'Mark Santos',
      phone: '0920 111 2233',
      address: 'Brgy. Del Remedio, San Pablo City',
      parcelType: 'Small Parcel',
      paymentMethod: 'Cash on Delivery',
      codAmount: 650,
      riderFee: 55,
      distance: '3.5 km',
      customerLatitude: 14.0660,
      customerLongitude: 121.3150,
      status: 'Delivered',
      deliveredAt: DateTime(2026, 9, 15, 15, 21),
      receivedBy: 'Mark Santos',
    ),
  ];

  final List<Parcel> _returnedParcels = [
    Parcel(
      parcelNumber: '#SPX10218',
      trackingNumber: 'SPXPH102345658',
      hub: 'San Pablo Delivery Hub',
      recipient: 'Rene Cruz',
      phone: '0911 555 0188',
      address: 'Brgy. San Isidro, San Pablo City',
      parcelType: 'Small Parcel',
      paymentMethod: 'Cash on Delivery',
      codAmount: 520,
      riderFee: 45,
      distance: '2.9 km',
      customerLatitude: 14.0630,
      customerLongitude: 121.3180,
      status: 'Returned',
      returnedAt: DateTime(2026, 9, 15, 9, 15),
      returnReason: 'Parcel Damaged',
      returnNote: 'Parcel packaging was visibly damaged.',
    ),
  ];

  @override
  void initState() {
    super.initState();

    _syncScannedParcel();

    ParcelStore.currentParcel.addListener(_onParcelStoreChanged);
  }

  @override
  void dispose() {
    ParcelStore.currentParcel.removeListener(_onParcelStoreChanged);

    super.dispose();
  }

  void _onParcelStoreChanged() {
    if (!mounted) return;

    _syncScannedParcel();
  }

  // ============================================================
  // QR SCAN -> PICKUPS
  // ============================================================

  void _syncScannedParcel() {
    final ParcelData? scannedParcel = ParcelStore.currentParcel.value;

    if (scannedParcel == null) return;

    final bool alreadyExists = [
      ..._pickupParcels,
      ..._ongoingParcels,
      ..._deliveredParcels,
      ..._returnedParcels,
    ].any((parcel) => parcel.trackingNumber == scannedParcel.trackingNumber);

    if (alreadyExists) return;

    final Parcel newParcel = Parcel(
      parcelNumber: scannedParcel.parcelNumber,
      trackingNumber: scannedParcel.trackingNumber,
      hub: scannedParcel.hub,
      recipient: scannedParcel.recipient,
      phone: scannedParcel.phone,
      address: scannedParcel.address,
      parcelType: scannedParcel.parcelType,
      paymentMethod: scannedParcel.paymentMethod,
      codAmount: scannedParcel.codAmount,
      riderFee: scannedParcel.riderFee,
      distance: scannedParcel.distance,
      customerLatitude: scannedParcel.customerLatitude,
      customerLongitude: scannedParcel.customerLongitude,
      status: 'To Pick Up',
    );

    setState(() {
      _pickupParcels.insert(0, newParcel);

      _selectedTab = 0;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      body: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'My Parcels',
                style: TextStyle(
                  fontSize: 27,
                  fontWeight: FontWeight.w800,
                  color: darkMaroon,
                  letterSpacing: -0.4,
                ),
              ),

              const SizedBox(height: 4),

              const Text(
                'Manage pickups, deliveries, returns, and proof of delivery.',
                style: TextStyle(fontSize: 12.5, color: textMuted),
              ),

              const SizedBox(height: 14),

              Expanded(
                child: Stack(
                  clipBehavior: Clip.none,
                  children: [
                    Positioned.fill(child: _buildSelectedTab()),

                    Positioned(
                      top: 0,
                      left: 0,
                      right: 0,
                      child: _buildFloatingTabs(),
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

  Widget _buildFloatingTabs() {
    return Container(
      height: 54,
      padding: const EdgeInsets.symmetric(vertical: 6),
      decoration: BoxDecoration(
        color: darkMaroon,
        borderRadius: BorderRadius.circular(radiusLg),
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
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.symmetric(horizontal: 6),
        itemCount: _tabs.length,
        separatorBuilder: (_, __) => const SizedBox(width: 6),
        itemBuilder: (context, index) {
          final bool selected = _selectedTab == index;

          final tab = _tabs[index];

          return GestureDetector(
            onTap: () => setState(() => _selectedTab = index),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 220),
              curve: Curves.easeOut,
              padding: const EdgeInsets.symmetric(horizontal: 14),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: selected ? cream : Colors.transparent,
                borderRadius: BorderRadius.circular(radiusSm),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    tab.icon,
                    size: 15,
                    color: selected
                        ? darkMaroon
                        : Colors.white.withValues(alpha: 0.85),
                  ),

                  const SizedBox(width: 6),

                  Text(
                    tab.label,
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: selected
                          ? darkMaroon
                          : Colors.white.withValues(alpha: 0.85),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildSelectedTab() {
    switch (_selectedTab) {
      case 0:
        // Seller pickup tasks were removed. This tab only lists
        // scanned parcels.
        return _buildParcelList(_pickupParcels, type: 'pickup');

      case 1:
        return _buildParcelList(_ongoingParcels, type: 'ongoing');

      case 2:
        return _buildParcelList(_deliveredParcels, type: 'delivered');

      case 3:
        return _buildParcelList(_returnedParcels, type: 'return');

      case 4:
        return _buildProofOfDeliveryTab();

      default:
        return const SizedBox.shrink();
    }
  }

  Widget _buildParcelList(List<Parcel> parcels, {required String type}) {
    if (parcels.isEmpty) {
      return _emptyState(type);
    }

    return ListView.separated(
      physics: const BouncingScrollPhysics(),
      padding: _listPadding,
      itemCount: parcels.length,
      separatorBuilder: (_, __) => const SizedBox(height: 14),
      itemBuilder: (context, index) {
        return _parcelCard(parcel: parcels[index], type: type);
      },
    );
  }

  Widget _emptyState(String type) {
    String title;
    String message;
    IconData icon;

    switch (type) {
      case 'pickup':
        title = 'No Pickups';
        message = 'Scan a parcel QR code and it will appear here.';
        icon = Icons.inventory_2_outlined;
        break;

      case 'ongoing':
        title = 'No Ongoing Deliveries';
        message = 'Parcels you start delivering will appear here.';
        icon = Icons.local_shipping_outlined;
        break;

      case 'delivered':
        title = 'No Delivered Parcels';
        message = 'Successfully delivered parcels will be stored here.';
        icon = Icons.check_circle_outline_rounded;
        break;

      default:
        title = 'No Returned Parcels';
        message = 'Returned or damaged parcels will be stored here.';
        icon = Icons.assignment_return_outlined;
    }

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
                color: lightBeige.withValues(alpha: 0.4),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 36, color: darkMaroon),
            ),

            const SizedBox(height: 18),

            Text(
              title,
              style: const TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: darkMaroon,
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
                  color: textMuted,
                  height: 1.4,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _parcelCard({required Parcel parcel, required String type}) {
    return Container(
      padding: const EdgeInsets.all(18),
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
                child: Icon(
                  type == 'return'
                      ? Icons.assignment_return_rounded
                      : Icons.inventory_2_rounded,
                  color: darkMaroon,
                  size: 21,
                ),
              ),

              const SizedBox(width: 12),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      parcel.parcelNumber,
                      style: const TextStyle(
                        fontSize: 15.5,
                        fontWeight: FontWeight.w800,
                        color: darkMaroon,
                      ),
                    ),

                    const SizedBox(height: 2),

                    Text(
                      parcel.trackingNumber,
                      style: const TextStyle(fontSize: 10.5, color: textMuted),
                    ),
                  ],
                ),
              ),

              _statusChip(parcel.status),
            ],
          ),

          const SizedBox(height: 16),

          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(13),
            decoration: BoxDecoration(
              color: cream.withValues(alpha: 0.6),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _compactInfo(Icons.person_rounded, parcel.recipient),

                const SizedBox(height: 9),

                _compactInfo(Icons.location_on_rounded, parcel.address),

                if (parcel.codAmount > 0) ...[
                  const SizedBox(height: 9),

                  _compactInfo(
                    Icons.payments_rounded,
                    'COD: ₱${parcel.codAmount.toStringAsFixed(2)}',
                  ),
                ],
              ],
            ),
          ),

          if (type == 'delivered' && parcel.deliveredAt != null) ...[
            const SizedBox(height: 10),

            _dateInfoBox(
              icon: Icons.event_available_rounded,
              title: 'Delivered on',
              value:
                  '${_formatDate(parcel.deliveredAt!)} • ${_formatTime(parcel.deliveredAt!)}',
            ),

            if ((parcel.receivedBy ?? '').isNotEmpty) ...[
              const SizedBox(height: 8),

              _dateInfoBox(
                icon: Icons.person_pin_rounded,
                title: 'Received by',
                value: parcel.receivedBy!,
              ),
            ],

            if (parcel.proofImagePath != null) ...[
              const SizedBox(height: 10),

              _podPreview(parcel),
            ],
          ],

          if (type == 'return') ...[
            const SizedBox(height: 10),
            _dateInfoBox(
              icon: Icons.assignment_return_rounded,
              title: 'Return reason',
              value: parcel.returnReason ?? 'Parcel returned',
              accent: danger,
            ),
            if (parcel.returnedAt != null) ...[
              const SizedBox(height: 8),
              _dateInfoBox(
                icon: Icons.schedule_rounded,
                title: 'Returned on',
                value:
                    '${_formatDate(parcel.returnedAt!)} • ${_formatTime(parcel.returnedAt!)}',
              ),
            ],
            if ((parcel.returnNote ?? '').isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                parcel.returnNote!,
                style: const TextStyle(
                  fontSize: 11.5,
                  color: textMuted,
                  height: 1.4,
                ),
              ),
            ],
            if (parcel.rescheduledAt != null) ...[
              const SizedBox(height: 10),
              _dateInfoBox(
                icon: Icons.event_available_rounded,
                title: 'New delivery schedule',
                value:
                    '${_formatDate(parcel.rescheduledAt!)} • ${_formatTime(parcel.rescheduledAt!)}',
                accent: success,
              ),
              if ((parcel.rescheduleNote ?? '').trim().isNotEmpty) ...[
                const SizedBox(height: 6),
                Text(
                  'Note: ${parcel.rescheduleNote}',
                  style: const TextStyle(fontSize: 11.5, color: textMuted),
                ),
              ],
            ],
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: OutlinedButton.icon(
                onPressed: () => _rescheduleParcel(parcel),
                icon: const Icon(Icons.calendar_month_rounded),
                label: Text(
                  parcel.rescheduledAt == null
                      ? 'Reschedule Parcel'
                      : 'Update Schedule',
                ),
                style: OutlinedButton.styleFrom(
                  foregroundColor: darkMaroon,
                  side: const BorderSide(color: darkMaroon),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(radiusSm),
                  ),
                ),
              ),
            ),
          ],

          if (type == 'pickup') ...[
            const SizedBox(height: 16),

            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton.icon(
                onPressed: () => _startPickup(parcel),
                icon: const Icon(Icons.inventory_2_rounded, size: 19),
                label: const Text(
                  'Start Pickup',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5),
                ),
                style: _primaryButtonStyle(),
              ),
            ),
          ],

          if (type == 'ongoing') ...[
            const SizedBox(height: 16),

            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton.icon(
                onPressed: () => _openDeliveryDetails(parcel),
                icon: const Icon(Icons.delivery_dining_rounded, size: 20),
                label: const Text(
                  'View Delivery Details',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5),
                ),
                style: _primaryButtonStyle(),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _statusChip(String status) {
    final bool delivered = status == 'Delivered';
    final bool returned = status == 'Returned';

    final Color color = delivered
        ? success
        : returned
        ? danger
        : darkMaroon;

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
            status,
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

  Widget _compactInfo(IconData icon, String text) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: darkMaroon, size: 16),

        const SizedBox(width: 9),

        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 12,
              fontWeight: FontWeight.w500,
              height: 1.35,
            ),
          ),
        ),
      ],
    );
  }

  Widget _dateInfoBox({
    required IconData icon,
    required String title,
    required String value,
    Color accent = darkMaroon,
  }) {
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
                    color: textMuted,
                    fontWeight: FontWeight.w600,
                  ),
                ),

                const SizedBox(height: 2),

                Text(
                  value,
                  style: TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: accent == darkMaroon ? darkMaroon : accent,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _podPreview(Parcel parcel) {
    final String? path = parcel.proofImagePath;

    return InkWell(
      borderRadius: BorderRadius.circular(15),
      onTap: path == null ? null : () => _showProofImage(parcel),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: lightBeige.withValues(alpha: 0.32),
          borderRadius: BorderRadius.circular(15),
          border: Border.all(color: darkMaroon.withValues(alpha: 0.06)),
        ),
        child: Row(
          children: [
            _proofThumbnail(path, size: 56),

            const SizedBox(width: 12),

            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Proof of Delivery',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: darkMaroon,
                    ),
                  ),

                  SizedBox(height: 3),

                  Text(
                    'Tap to view photo',
                    style: TextStyle(fontSize: 10.5, color: textMuted),
                  ),
                ],
              ),
            ),

            Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: surface,
                borderRadius: BorderRadius.circular(9),
              ),
              child: const Icon(
                Icons.arrow_forward_ios_rounded,
                size: 13,
                color: darkMaroon,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _proofThumbnail(String? path, {double size = 58}) {
    if (path != null && File(path).existsSync()) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Image.file(
          File(path),
          width: size,
          height: size,
          fit: BoxFit.cover,
        ),
      );
    }

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: cream,
        borderRadius: BorderRadius.circular(12),
      ),
      child: const Icon(Icons.image_outlined, color: brownBeige),
    );
  }

  // ============================================================
  // PROOF OF DELIVERY TAB
  // Rider chooses a date; only deliveries on that date are shown.
  // ============================================================

  Widget _buildProofOfDeliveryTab() {
    final List<Parcel> filtered = _deliveredParcels.where((parcel) {
      final DateTime? deliveredAt = parcel.deliveredAt;

      if (deliveredAt == null) return false;

      return _sameDate(deliveredAt, _selectedPodDate);
    }).toList()..sort((a, b) => b.deliveredAt!.compareTo(a.deliveredAt!));

    return Padding(
      padding: const EdgeInsets.only(top: _tabsHeight + 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          InkWell(
            borderRadius: BorderRadius.circular(radiusMd),
            onTap: _pickPodDate,
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: cardDecoration,
              child: Row(
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      color: lightBeige.withValues(alpha: 0.45),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Icon(
                      Icons.calendar_month_rounded,
                      color: darkMaroon,
                      size: 21,
                    ),
                  ),

                  const SizedBox(width: 12),

                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Select Delivery Date',
                          style: TextStyle(
                            fontSize: 10,
                            color: textMuted,
                            fontWeight: FontWeight.w600,
                          ),
                        ),

                        const SizedBox(height: 3),

                        Text(
                          _formatDate(_selectedPodDate),
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            color: darkMaroon,
                          ),
                        ),
                      ],
                    ),
                  ),

                  Container(
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                      color: cream,
                      borderRadius: BorderRadius.circular(9),
                    ),
                    child: const Icon(
                      Icons.keyboard_arrow_down_rounded,
                      color: darkMaroon,
                      size: 20,
                    ),
                  ),
                ],
              ),
            ),
          ),

          const SizedBox(height: 18),

          Text(
            '${filtered.length} ${filtered.length == 1 ? 'delivery' : 'deliveries'} on ${_formatDate(_selectedPodDate)}',
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: darkMaroon,
              letterSpacing: -0.2,
            ),
          ),

          const SizedBox(height: 12),

          Expanded(
            child: filtered.isEmpty
                ? Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 72,
                          height: 72,
                          decoration: BoxDecoration(
                            color: lightBeige.withValues(alpha: 0.4),
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(
                            Icons.photo_camera_outlined,
                            size: 30,
                            color: darkMaroon,
                          ),
                        ),

                        const SizedBox(height: 14),

                        const Text(
                          'No proof of delivery records\nfor this date.',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: textMuted,
                            fontSize: 12.5,
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  )
                : ListView.separated(
                    physics: const BouncingScrollPhysics(),
                    itemCount: filtered.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      return _podHistoryCard(filtered[index]);
                    },
                  ),
          ),
        ],
      ),
    );
  }

  Widget _podHistoryCard(Parcel parcel) {
    return InkWell(
      borderRadius: BorderRadius.circular(radiusMd),
      onTap: parcel.proofImagePath == null
          ? null
          : () => _showProofImage(parcel),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: cardDecoration,
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: lightBeige.withValues(alpha: 0.45),
                borderRadius: BorderRadius.circular(13),
              ),
              child: const Icon(
                Icons.inventory_2_rounded,
                color: darkMaroon,
                size: 20,
              ),
            ),

            const SizedBox(width: 12),

            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    parcel.parcelNumber,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: darkMaroon,
                      fontSize: 14,
                    ),
                  ),

                  const SizedBox(height: 3),

                  Text(
                    parcel.recipient,
                    style: const TextStyle(
                      color: darkMaroon,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),

                  const SizedBox(height: 3),

                  Text(
                    parcel.deliveredAt == null
                        ? ''
                        : _formatTime(parcel.deliveredAt!),
                    style: const TextStyle(color: textMuted, fontSize: 10.5),
                  ),

                  if ((parcel.receivedBy ?? '').isNotEmpty) ...[
                    const SizedBox(height: 2),

                    Text(
                      'Received by: ${parcel.receivedBy}',
                      style: const TextStyle(color: textMuted, fontSize: 10),
                    ),
                  ],
                ],
              ),
            ),

            _proofThumbnail(parcel.proofImagePath, size: 52),
          ],
        ),
      ),
    );
  }

  Future<void> _pickPodDate() async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: _selectedPodDate,
      firstDate: DateTime(2025),
      lastDate: DateTime(2030),
      helpText: 'Select delivery date',
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

    if (picked == null || !mounted) return;

    setState(() {
      _selectedPodDate = picked;
    });
  }

  void _showProofImage(Parcel parcel) {
    final String? path = parcel.proofImagePath;

    if (path == null || !File(path).existsSync()) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Proof of Delivery image is not available.'),
        ),
      );

      return;
    }

    showDialog<void>(
      context: context,
      builder: (dialogContext) {
        return Dialog(
          backgroundColor: cream,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24),
          ),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Proof of Delivery',
                        style: TextStyle(
                          color: darkMaroon,
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.2,
                        ),
                      ),
                    ),

                    Container(
                      decoration: BoxDecoration(
                        color: surface,
                        borderRadius: BorderRadius.circular(11),
                      ),
                      child: IconButton(
                        onPressed: () => Navigator.pop(dialogContext),
                        icon: const Icon(
                          Icons.close_rounded,
                          color: darkMaroon,
                          size: 20,
                        ),
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 8),

                ClipRRect(
                  borderRadius: BorderRadius.circular(18),
                  child: Image.file(File(path), fit: BoxFit.cover),
                ),

                const SizedBox(height: 14),

                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: surface,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _dialogInfo(
                        'Parcel',
                        '${parcel.parcelNumber} • ${parcel.recipient}',
                      ),

                      if (parcel.deliveredAt != null)
                        _dialogInfo(
                          'Delivered',
                          '${_formatDate(parcel.deliveredAt!)} • ${_formatTime(parcel.deliveredAt!)}',
                        ),

                      if ((parcel.receivedBy ?? '').isNotEmpty)
                        _dialogInfo(
                          'Received by',
                          parcel.receivedBy!,
                          last: true,
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

  Widget _dialogInfo(String label, String value, {bool last = false}) {
    return Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 9),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 88,
            child: Text(
              label,
              style: const TextStyle(
                color: textMuted,
                fontSize: 11,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),

          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                color: darkMaroon,
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // PICKUP -> ONGOING
  // There is no seller pickup step anymore, so starting a parcel
  // goes straight to 'Out for Delivery'.
  // ============================================================

  void _startPickup(Parcel parcel) {
    setState(() {
      _pickupParcels.remove(parcel);

      parcel.status = 'Out for Delivery';

      _ongoingParcels.insert(0, parcel);

      _selectedTab = 1;
    });

    final scannedParcel = ParcelStore.currentParcel.value;

    if (scannedParcel != null &&
        scannedParcel.trackingNumber == parcel.trackingNumber) {
      ParcelStore.updateStatus('Out for Delivery');
    }

    _openDeliveryDetails(parcel);
  }

  // ============================================================
  // DELIVERY DETAILS RESULT
  // ============================================================

  Future<void> _openDeliveryDetails(Parcel parcel) async {
    final dynamic result = await Navigator.push<dynamic>(
      context,
      MaterialPageRoute(
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

    // New result format from delivery_details_screen.dart.
    if (result is Map) {
      final String? status = result['status'] as String?;

      if (status == 'completed') {
        setState(() {
          _ongoingParcels.remove(parcel);

          parcel.status = 'Delivered';

          parcel.deliveredAt =
              result['deliveredAt'] as DateTime? ?? DateTime.now();

          parcel.receivedBy = result['receivedBy'] as String?;

          parcel.proofImagePath = result['proofImagePath'] as String?;

          _deliveredParcels.insert(0, parcel);

          _selectedPodDate = parcel.deliveredAt!;

          _selectedTab = 2;
        });

        _updateStoreStatus(parcel, 'Delivered');

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${parcel.parcelNumber} saved to Delivered with date, time, and POD.',
            ),
          ),
        );

        return;
      }

      if (status == 'returned') {
        setState(() {
          _ongoingParcels.remove(parcel);

          parcel.status = 'Returned';

          parcel.returnedAt =
              result['returnedAt'] as DateTime? ?? DateTime.now();

          parcel.returnReason =
              result['returnReason'] as String? ?? 'Parcel returned';

          parcel.returnNote = result['returnNote'] as String?;

          _returnedParcels.insert(0, parcel);

          _selectedTab = 3;
        });

        _updateStoreStatus(parcel, 'Returned');

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${parcel.parcelNumber} saved to Returns.')),
        );

        return;
      }
    }

    // Compatibility with an older delivery_details_screen.dart.
    if (result == 'completed') {
      setState(() {
        _ongoingParcels.remove(parcel);

        parcel.status = 'Delivered';

        parcel.deliveredAt = DateTime.now();

        parcel.receivedBy = parcel.recipient;

        _deliveredParcels.insert(0, parcel);

        _selectedPodDate = parcel.deliveredAt!;

        _selectedTab = 2;
      });

      _updateStoreStatus(parcel, 'Delivered');
    } else if (result == 'failed') {
      setState(() {
        _ongoingParcels.remove(parcel);

        parcel.status = 'Returned';

        parcel.returnedAt = DateTime.now();

        parcel.returnReason = 'Delivery issue';

        _returnedParcels.insert(0, parcel);

        _selectedTab = 3;
      });

      _updateStoreStatus(parcel, 'Returned');
    }
  }

  // Select a new delivery date and time for a returned parcel.
  // This prototype saves the schedule locally; it does not notify the customer
  // or logistics center until a backend/API is connected.
  Future<void> _rescheduleParcel(Parcel parcel) async {
    final DateTime now = DateTime.now();
    final DateTime initial =
        parcel.rescheduledAt != null && parcel.rescheduledAt!.isAfter(now)
        ? parcel.rescheduledAt!
        : now.add(const Duration(days: 1));

    final DateTime? date = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(now.year, now.month, now.day),
      lastDate: DateTime(now.year + 2),
      helpText: 'Select new delivery date',
      builder: (context, child) => Theme(
        data: Theme.of(context).copyWith(
          colorScheme: const ColorScheme.light(
            primary: darkMaroon,
            onPrimary: Colors.white,
            surface: cream,
            onSurface: darkMaroon,
          ),
        ),
        child: child!,
      ),
    );
    if (date == null || !mounted) return;

    final TimeOfDay? time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(initial),
      helpText: 'Select new delivery time',
      builder: (context, child) => Theme(
        data: Theme.of(context).copyWith(
          colorScheme: const ColorScheme.light(
            primary: darkMaroon,
            onPrimary: Colors.white,
            surface: cream,
            onSurface: darkMaroon,
          ),
        ),
        child: child!,
      ),
    );
    if (time == null || !mounted) return;

    final TextEditingController noteController = TextEditingController(
      text: parcel.rescheduleNote ?? '',
    );
    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: cream,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radiusLg),
        ),
        title: const Text(
          'Confirm Reschedule',
          style: TextStyle(color: darkMaroon, fontWeight: FontWeight.w800),
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                parcel.parcelNumber,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              Text('${_formatDate(date)} • ${time.format(context)}'),
              const SizedBox(height: 14),
              TextField(
                controller: noteController,
                maxLines: 2,
                decoration: InputDecoration(
                  labelText: 'Note (optional)',
                  hintText: 'e.g. Customer requested afternoon delivery',
                  filled: true,
                  fillColor: surface,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(radiusSm),
                  ),
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel', style: TextStyle(color: textMuted)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: darkMaroon,
              foregroundColor: Colors.white,
            ),
            child: const Text('Save Schedule'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) {
      noteController.dispose();
      return;
    }

    setState(() {
      parcel.rescheduledAt = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      );
      parcel.rescheduleNote = noteController.text.trim();
    });
    noteController.dispose();

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('${parcel.parcelNumber} schedule saved locally.'),
        backgroundColor: success,
      ),
    );
  }

  void _updateStoreStatus(Parcel parcel, String status) {
    final scannedParcel = ParcelStore.currentParcel.value;

    if (scannedParcel != null &&
        scannedParcel.trackingNumber == parcel.trackingNumber) {
      ParcelStore.updateStatus(status);
    }

    if (status == 'Delivered') {
      final ParcelData completedParcel = ParcelData(
        parcelNumber: parcel.parcelNumber,
        trackingNumber: parcel.trackingNumber,
        hub: parcel.hub,
        recipient: parcel.recipient,
        phone: parcel.phone,
        address: parcel.address,
        parcelType: parcel.parcelType,
        paymentMethod: parcel.paymentMethod,
        codAmount: parcel.codAmount,
        riderFee: parcel.riderFee,
        distance: parcel.distance,
        customerLatitude: parcel.customerLatitude,
        customerLongitude: parcel.customerLongitude,
        qrValue: parcel.trackingNumber,
        status: 'Delivered',
      );

      ParcelStore.awardSuccessfulDelivery(completedParcel);

      RiderEarningsService.instance.recordSuccessfulDelivery(completedParcel);
    }
  }

  ButtonStyle _primaryButtonStyle() {
    return ElevatedButton.styleFrom(
      backgroundColor: darkMaroon,
      foregroundColor: Colors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(radiusSm),
      ),
    );
  }

  bool _sameDate(DateTime a, DateTime b) {
    return a.year == b.year && a.month == b.month && a.day == b.day;
  }

  String _formatDate(DateTime date) {
    const months = [
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

    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }

  String _formatTime(DateTime date) {
    int hour = date.hour;

    final String period = hour >= 12 ? 'PM' : 'AM';

    if (hour == 0) {
      hour = 12;
    } else if (hour > 12) {
      hour -= 12;
    }

    final String minute = date.minute.toString().padLeft(2, '0');

    return '$hour:$minute $period';
  }
}

// ============================================================
// TAB INFO
// ============================================================

class _TabInfo {
  final String label;
  final IconData icon;

  const _TabInfo(this.label, this.icon);
}

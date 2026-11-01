import 'dart:io';

import 'package:flutter/material.dart';

import 'rider_detail_screen.dart' as detail;

const Color darkMaroon = Color(0xFF671B1D);
const Color maroon = Color(0xFF873632);
const Color brown = Color(0xFF9E7161);
const Color cream = Color(0xFFFFF8E7);
const Color paleCream = Color(0xFFFFFDF5);
const Color borderColor = Color(0xFFE8D6C3);
const Color successGreen = Color(0xFF427A52);
const Color warningOrange = Color(0xFFB87920);
const Color dangerRed = Color(0xFFB3261E);
const Color infoBlue = Color(0xFF4A7FBF);
const Color textColor = Color(0xFF382523);
const Color mutedColor = Color(0xFF8C7770);

Color _upgradeStatusColor(String status) {
  switch (status) {
    case 'Approved':
      return successGreen;
    case 'Pending':
      return warningOrange;
    case 'Declined':
      return dangerRed;
    default:
      return brown;
  }
}

// -----------------------------------------------------------------------------
// Models (local demo only, not connected to a backend or the rider app yet)
// -----------------------------------------------------------------------------

// A file the rider uploaded with the upgrade request (OR/CR, photo, license).
// `path` can be a local file path or an http(s) URL once you have a backend.
class _UpgradeAttachment {
  final String name;
  final String path;
  final int? sizeBytes;

  const _UpgradeAttachment({
    required this.name,
    required this.path,
    this.sizeBytes,
  });

  String get ext {
    final dot = name.lastIndexOf('.');
    if (dot == -1) return '';
    return name.substring(dot + 1).toLowerCase();
  }

  bool get isPdf => ext == 'pdf';

  bool get isUrl => path.startsWith('http://') || path.startsWith('https://');

  String get sizeLabel {
    int? bytes = sizeBytes;

    if (bytes == null && !isUrl) {
      try {
        final f = File(path);
        if (f.existsSync()) bytes = f.lengthSync();
      } catch (_) {}
    }

    if (bytes == null) return '';
    if (bytes < 1024) return '$bytes B';
    if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(0)} KB';
    return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
  }
}

class _UpgradeRequest {
  final String id;
  final String riderId;
  final String riderName;
  final String currentVehicle;
  final String currentPlate;
  final String requestedVehicle;
  final String newPlate;
  final String reason;
  final DateTime requestedAt;

  // Same data the rider uploads in the Upgrade Vehicle form.
  final List<_UpgradeAttachment> orCrFiles;
  final List<_UpgradeAttachment> vehiclePhotos;
  final _UpgradeAttachment? license;

  String status; // Pending, Approved, Declined

  _UpgradeRequest({
    required this.id,
    required this.riderId,
    required this.riderName,
    required this.currentVehicle,
    required this.currentPlate,
    required this.requestedVehicle,
    required this.newPlate,
    required this.reason,
    required this.requestedAt,
    this.orCrFiles = const [],
    this.vehiclePhotos = const [],
    this.license,
    this.status = 'Pending',
  });

  static String formatDate(DateTime d) {
    const m = [
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
    return '${m[d.month - 1]} ${d.day}, ${d.year}';
  }
}

IconData _vehicleIcon(String vehicle) {
  switch (vehicle.toLowerCase()) {
    case 'van':
    case 'pickup van':
      return Icons.airport_shuttle_rounded;
    case 'truck':
      return Icons.local_shipping_rounded;
    case 'tricycle':
      return Icons.electric_rickshaw_outlined;
    default:
      return Icons.two_wheeler_rounded;
  }
}

// Shows a local file, a network image, or a placeholder if it can't be loaded.
Widget _attachmentImage(_UpgradeAttachment a, {BoxFit fit = BoxFit.cover}) {
  Widget placeholder() => Container(
    color: cream,
    alignment: Alignment.center,
    child: const Icon(
      Icons.image_not_supported_outlined,
      color: mutedColor,
      size: 26,
    ),
  );

  if (a.isUrl) {
    return Image.network(
      a.path,
      fit: fit,
      errorBuilder: (_, _, _) => placeholder(),
    );
  }

  try {
    final f = File(a.path);
    if (f.existsSync()) {
      return Image.file(f, fit: fit, errorBuilder: (_, _, _) => placeholder());
    }
  } catch (_) {}

  return placeholder();
}

// -----------------------------------------------------------------------------
// Riders screen
// -----------------------------------------------------------------------------

class RidersScreen extends StatefulWidget {
  const RidersScreen({super.key});

  @override
  State<RidersScreen> createState() => _RidersScreenState();
}

class _RidersScreenState extends State<RidersScreen> {
  // 'Applications' | 'Registered Riders' | 'Vehicle Upgrades'
  String selectedSection = 'Applications';
  String searchQuery = '';
  String selectedStatus = 'All';
  String selectedUpgradeStatus = 'Pending';

  final TextEditingController _searchController = TextEditingController();

  // Demo upgrade requests (local only). Replace with API data later.
  final List<_UpgradeRequest> _upgradeRequests = [];

  _UpgradeRequest? _pendingFor(String riderId) {
    for (final r in _upgradeRequests) {
      if (r.riderId == riderId && r.status == 'Pending') return r;
    }
    return null;
  }

  // Demo data only. Connect this list to your API later.
  final List<Map<String, dynamic>> riders = [
    {
      'id': 'RDR-1001',
      'name': 'Mary Angela Cruz',
      'phone': '09171234567',
      'email': 'mary@example.com',
      'hub': 'Victoria Hub',
      'area': 'Brgy. Masapang',
      'status': 'Available',
      'delivered': 92,
      'ongoing': 2,
      'failed': 3,
      'joined': 'January 15, 2026',
      'vehicle': 'Motorcycle',
      'address': 'Victoria, Laguna',
      'sex': 'Female',
      'age': '25',
      'birthday': 'June 10, 2001',
      'province': 'Laguna',
      'municipality': 'Victoria',
      'barangay': 'Masapang',
      'street': 'Main Street',
      'houseUnit': 'Block 1',
      'plateNumber': 'ABC-1234',
      'licenseNumber': 'N01-12-345678',
    },
    {
      'id': 'RDR-1002',
      'name': 'Lou Garcia',
      'phone': '09181234567',
      'email': 'lou@example.com',
      'hub': 'Victoria Hub',
      'area': 'Victoria Town',
      'status': 'On Delivery',
      'delivered': 76,
      'ongoing': 4,
      'failed': 2,
      'joined': 'February 10, 2026',
      'vehicle': 'Motorcycle',
      'address': 'Victoria, Laguna',
      'sex': 'Male',
      'age': '28',
      'birthday': 'April 12, 1998',
      'province': 'Laguna',
      'municipality': 'Victoria',
      'barangay': 'San Roque',
      'plateNumber': 'XYZ-5678',
    },
    {
      'id': 'RDR-1003',
      'name': 'Maricko Sheiy Villacorta',
      'phone': '09191234567',
      'email': 'maricko@example.com',
      'hub': 'Calauan Hub',
      'area': 'Brgy. San Roque',
      'status': 'Suspended',
      'delivered': 45,
      'ongoing': 0,
      'failed': 5,
      'joined': 'March 05, 2026',
      'vehicle': 'Motorcycle',
      'address': 'Calauan, Laguna',
      'sex': 'Female',
      'age': '24',
      'birthday': 'March 5, 2002',
      'province': 'Laguna',
      'municipality': 'Calauan',
      'barangay': 'San Roque',
    },
    {
      'id': 'APP-1004',
      'name': 'Juan Dela Cruz',
      'phone': '09201234567',
      'email': 'juan@example.com',
      'hub': 'Victoria Hub',
      'area': 'Brgy. San Benito',
      'status': 'Pending Approval',
      'delivered': 0,
      'ongoing': 0,
      'failed': 0,
      'joined': 'October 20, 2026',
      'vehicle': 'Motorcycle',
      'address': 'Victoria, Laguna',
      'sex': 'Male',
      'age': '26',
      'birthday': 'May 15, 2000',
      'province': 'Laguna',
      'municipality': 'Victoria',
      'barangay': 'San Benito',
      'street': 'Rizal Street',
      'houseUnit': 'House 12',
      'plateNumber': 'DEF-5678',
      'licenseNumber': 'N01-22-123456',
    },
  ];

  @override
  void initState() {
    super.initState();

    // Demo data so the Upgrades button has something to show.
    // The file paths below are fake, so images show a placeholder.
    // Real requests will carry real paths/URLs from the backend.
    _upgradeRequests.addAll([
      _UpgradeRequest(
        id: 'VUP-1',
        riderId: 'RDR-1001',
        riderName: 'Mary Angela Cruz',
        currentVehicle: 'Motorcycle',
        currentPlate: 'ABC-1234',
        requestedVehicle: 'Van',
        newPlate: 'VAN-4321',
        reason: 'Handling bulk parcels in my area.',
        requestedAt: DateTime.now().subtract(const Duration(days: 1)),
        orCrFiles: const [
          _UpgradeAttachment(
            name: 'official_receipt.pdf',
            path: '/demo/official_receipt.pdf',
            sizeBytes: 482000,
          ),
          _UpgradeAttachment(
            name: 'certificate_of_registration.pdf',
            path: '/demo/cr.pdf',
            sizeBytes: 731000,
          ),
        ],
        vehiclePhotos: const [
          _UpgradeAttachment(
            name: 'van_front.jpg',
            path: '/demo/van_front.jpg',
          ),
          _UpgradeAttachment(name: 'van_side.jpg', path: '/demo/van_side.jpg'),
          _UpgradeAttachment(
            name: 'van_plate.jpg',
            path: '/demo/van_plate.jpg',
          ),
        ],
        license: const _UpgradeAttachment(
          name: 'drivers_license.jpg',
          path: '/demo/license.jpg',
          sizeBytes: 912000,
        ),
      ),
      _UpgradeRequest(
        id: 'VUP-2',
        riderId: 'RDR-1002',
        riderName: 'Lou Garcia',
        currentVehicle: 'Motorcycle',
        currentPlate: 'XYZ-5678',
        requestedVehicle: 'Tricycle',
        newPlate: 'TRI-1122',
        reason: 'Need more space for large orders.',
        requestedAt: DateTime.now().subtract(const Duration(hours: 5)),
        orCrFiles: const [
          _UpgradeAttachment(
            name: 'OR.docx',
            path: '/demo/OR.docx',
            sizeBytes: 210000,
          ),
          _UpgradeAttachment(
            name: 'CR.pdf',
            path: '/demo/CR.pdf',
            sizeBytes: 540000,
          ),
        ],
        vehiclePhotos: const [
          _UpgradeAttachment(name: 'tricycle.jpg', path: '/demo/tricycle.jpg'),
        ],
      ),
    ]);
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  // ---------------------------------------------------------------------------
  // Data helpers
  // ---------------------------------------------------------------------------

  bool get _isApplications => selectedSection == 'Applications';
  bool get _isUpgrades => selectedSection == 'Vehicle Upgrades';

  int get pendingCount =>
      riders.where((r) => r['status'] == 'Pending Approval').length;

  int get registeredCount => riders.where((r) {
    return r['status'] != 'Pending Approval' && r['status'] != 'Declined';
  }).length;

  int get upgradePendingCount =>
      _upgradeRequests.where((r) => r.status == 'Pending').length;

  List<Map<String, dynamic>> get _sectionRiders {
    if (_isApplications) {
      return riders
          .where(
            (r) =>
                r['status'] == 'Pending Approval' || r['status'] == 'Declined',
          )
          .toList();
    }

    return riders
        .where(
          (r) => r['status'] != 'Pending Approval' && r['status'] != 'Declined',
        )
        .toList();
  }

  List<Map<String, dynamic>> get filteredRiders {
    final query = searchQuery.trim().toLowerCase();

    return _sectionRiders.where((rider) {
      final name = '${rider['name']}'.toLowerCase();
      final id = '${rider['id']}'.toLowerCase();

      final matchesSearch = name.contains(query) || id.contains(query);
      final matchesStatus =
          selectedStatus == 'All' || rider['status'] == selectedStatus;

      return matchesSearch && matchesStatus;
    }).toList();
  }

  List<_UpgradeRequest> get filteredUpgrades {
    final items = _upgradeRequests.where((r) {
      return selectedUpgradeStatus == 'All' ||
          r.status == selectedUpgradeStatus;
    }).toList();

    items.sort((a, b) {
      if (a.status == 'Pending' && b.status != 'Pending') return -1;
      if (a.status != 'Pending' && b.status == 'Pending') return 1;
      return b.requestedAt.compareTo(a.requestedAt);
    });

    return items;
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'Available':
      case 'Approved':
        return successGreen;
      case 'On Delivery':
        return infoBlue;
      case 'Pending Approval':
      case 'Pending':
        return warningOrange;
      case 'Suspended':
      case 'Terminated':
      case 'Declined':
        return dangerRed;
      default:
        return brown;
    }
  }

  void _setSection(String section) {
    setState(() {
      selectedSection = section;
      selectedStatus = 'All';
      selectedUpgradeStatus = 'Pending';
      searchQuery = '';
      _searchController.clear();
    });
  }

  void _clearFilters() {
    setState(() {
      searchQuery = '';
      selectedStatus = 'All';
      _searchController.clear();
    });
  }

  // ---------------------------------------------------------------------------
  // Messages and confirmations
  // ---------------------------------------------------------------------------

  void _showMessage(String message, Color color) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: color,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
        ),
      );
  }

  Future<bool> _confirm({
    required String title,
    required String message,
    required String confirmText,
    Color confirmColor = darkMaroon,
  }) async {
    final result = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: paleCream,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Text(
          title,
          style: const TextStyle(
            color: darkMaroon,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
        content: Text(
          message,
          style: const TextStyle(color: textColor, fontSize: 14, height: 1.45),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel', style: TextStyle(color: mutedColor)),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: confirmColor,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(confirmText),
          ),
        ],
      ),
    );

    return result ?? false;
  }

  // ---------------------------------------------------------------------------
  // Status actions
  // ---------------------------------------------------------------------------

  Future<void> _changeStatus(
    Map<String, dynamic> rider, {
    required String newStatus,
    required String title,
    required String message,
    required String confirmText,
    Color confirmColor = darkMaroon,
  }) async {
    final confirmed = await _confirm(
      title: title,
      message: message,
      confirmText: confirmText,
      confirmColor: confirmColor,
    );

    if (!confirmed || !mounted) return;

    setState(() {
      rider['status'] = newStatus;
    });

    _showMessage(
      '${rider['name']} status updated to $newStatus.',
      _statusColor(newStatus),
    );
  }

  Future<void> _approveRider(Map<String, dynamic> rider) async {
    if (rider['status'] != 'Pending Approval') return;

    await _changeStatus(
      rider,
      newStatus: 'Available',
      title: 'Approve application?',
      message:
          'Approve ${rider['name']} as a Lumear rider? '
          'The rider will move to Registered Riders.',
      confirmText: 'Approve',
      confirmColor: successGreen,
    );
  }

  Future<void> _declineRider(Map<String, dynamic> rider) async {
    if (rider['status'] != 'Pending Approval') return;

    await _changeStatus(
      rider,
      newStatus: 'Declined',
      title: 'Decline application?',
      message:
          'Decline ${rider['name']}? The application will remain '
          'in the application history.',
      confirmText: 'Decline',
      confirmColor: dangerRed,
    );
  }

  Future<void> _suspendRider(Map<String, dynamic> rider) async {
    if (rider['status'] == 'On Delivery') {
      _showMessage(
        'Resolve the ongoing delivery before suspending this rider.',
        warningOrange,
      );
      return;
    }

    if (!['Available', 'Inactive'].contains(rider['status'])) return;

    await _changeStatus(
      rider,
      newStatus: 'Suspended',
      title: 'Suspend rider?',
      message:
          'Suspend ${rider['name']}? They will not be eligible '
          'for new delivery assignments.',
      confirmText: 'Suspend',
      confirmColor: warningOrange,
    );
  }

  Future<void> _terminateRider(Map<String, dynamic> rider) async {
    if (rider['status'] == 'On Delivery') {
      _showMessage(
        'Resolve the ongoing delivery before terminating this rider.',
        warningOrange,
      );
      return;
    }

    if (!['Available', 'Inactive', 'Suspended'].contains(rider['status'])) {
      return;
    }

    await _changeStatus(
      rider,
      newStatus: 'Terminated',
      title: 'Terminate rider?',
      message:
          'Terminate ${rider['name']}? The rider record will remain '
          'visible, but the status will be Terminated.',
      confirmText: 'Terminate',
      confirmColor: dangerRed,
    );
  }

  Future<void> _reactivateRider(Map<String, dynamic> rider) async {
    if (!['Suspended', 'Inactive'].contains(rider['status'])) return;

    await _changeStatus(
      rider,
      newStatus: 'Available',
      title: 'Reactivate rider?',
      message: 'Reactivate ${rider['name']} and mark them Available?',
      confirmText: 'Reactivate',
      confirmColor: successGreen,
    );
  }

  Future<void> _openProfile(Map<String, dynamic> rider) async {
    final isPending = rider['status'] == 'Pending Approval';

    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (_) => detail.RiderDetailScreen(
          rider: rider,
          onApprove: isPending ? () => _approveRider(rider) : null,
          onDecline: isPending ? () => _declineRider(rider) : null,
        ),
      ),
    );

    if (mounted) setState(() {});
  }

  // ---------------------------------------------------------------------------
  // Vehicle upgrade actions
  // ---------------------------------------------------------------------------

  // Opens the read-only request form (same layout as the rider's form).
  Future<void> _openUpgradeDetail(_UpgradeRequest r) async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (_) => _UpgradeRequestDetailScreen(
          request: r,
          onApprove: () => _approveUpgrade(r),
          onDecline: () => _declineUpgrade(r),
        ),
      ),
    );

    if (mounted) setState(() {});
  }

  // Returns true when the request was approved.
  Future<bool> _approveUpgrade(_UpgradeRequest r) async {
    if (r.status != 'Pending') return false;

    final ok = await _confirm(
      title: 'Approve vehicle upgrade?',
      message:
          'Change ${r.riderName} from ${r.currentVehicle} to '
          '${r.requestedVehicle} (${r.newPlate})?',
      confirmText: 'Approve',
      confirmColor: successGreen,
    );

    if (!ok || !mounted) return false;

    // TODO: call backend here (e.g. POST /logistics/vehicle-upgrades/{id}/approve)
    setState(() {
      r.status = 'Approved';

      for (final rider in riders) {
        if (rider['id'] == r.riderId) {
          rider['vehicle'] = r.requestedVehicle;
          rider['plateNumber'] = r.newPlate;
        }
      }
    });

    _showMessage(
      '${r.riderName} upgraded to ${r.requestedVehicle}.',
      successGreen,
    );

    return true;
  }

  // Returns true when the request was declined.
  Future<bool> _declineUpgrade(_UpgradeRequest r) async {
    if (r.status != 'Pending') return false;

    final ok = await _confirm(
      title: 'Decline vehicle upgrade?',
      message: 'Decline the upgrade request from ${r.riderName}?',
      confirmText: 'Decline',
      confirmColor: dangerRed,
    );

    if (!ok || !mounted) return false;

    // TODO: call backend here (e.g. POST /logistics/vehicle-upgrades/{id}/decline)
    setState(() => r.status = 'Declined');

    _showMessage('Upgrade request declined.', dangerRed);

    return true;
  }

  // ---------------------------------------------------------------------------
  // Build
  // ---------------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final list = filteredRiders;
    final available = riders.where((r) => r['status'] == 'Available').length;
    final suspended = riders.where((r) => r['status'] == 'Suspended').length;

    return Container(
      color: cream,
      child: ListView(
        physics: const BouncingScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 120),
        children: [
          // ---------------- HEADER + UPGRADE BUTTON ----------------
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Riders',
                      style: TextStyle(
                        color: darkMaroon,
                        fontSize: 26,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Review applications and manage registered riders.',
                      style: TextStyle(color: mutedColor, fontSize: 13),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              _buildUpgradeButton(),
            ],
          ),
          const SizedBox(height: 18),

          if (selectedSection == 'Registered Riders' && pendingCount > 0)
            _buildAttentionBanner(),
          if (!_isUpgrades && upgradePendingCount > 0) _buildUpgradeBanner(),

          _buildOverview(available, suspended),
          const SizedBox(height: 18),

          if (_isUpgrades) ...[
            _buildBackToRiders(),
            const SizedBox(height: 14),
            ..._buildUpgradeSection(),
          ] else ...[
            _buildSectionSelector(),
            const SizedBox(height: 14),
            _buildSearchField(),
            const SizedBox(height: 12),
            _buildStatusFilters(),
            const SizedBox(height: 18),
            Row(
              children: [
                Expanded(
                  child: Text(
                    _isApplications
                        ? 'Rider applications'
                        : 'Registered riders',
                    style: const TextStyle(
                      color: darkMaroon,
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                Text(
                  '${list.length} ${list.length == 1 ? 'record' : 'records'}',
                  style: const TextStyle(color: mutedColor, fontSize: 12),
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (list.isEmpty)
              _buildEmptyState()
            else
              ...list.map(_buildRiderCard),
          ],
        ],
      ),
    );
  }

  BoxDecoration _cardDecoration({double radius = 16}) {
    return BoxDecoration(
      color: paleCream,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: borderColor),
      boxShadow: [
        BoxShadow(
          color: darkMaroon.withValues(alpha: 0.05),
          blurRadius: 12,
          offset: const Offset(0, 4),
        ),
      ],
    );
  }

  // ---------------------------------------------------------------------------
  // Upgrade button (header) + banner
  // ---------------------------------------------------------------------------

  Widget _buildUpgradeButton() {
    final count = upgradePendingCount;
    final active = _isUpgrades;

    return Stack(
      clipBehavior: Clip.none,
      children: [
        Material(
          color: active ? maroon : darkMaroon,
          borderRadius: BorderRadius.circular(14),
          child: InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: () => _setSection('Vehicle Upgrades'),
            child: const Padding(
              padding: EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.upgrade_rounded, color: Colors.white, size: 18),
                  SizedBox(width: 6),
                  Text(
                    'Upgrades',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 12.5,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
        if (count > 0)
          Positioned(
            top: -6,
            right: -6,
            child: Container(
              constraints: const BoxConstraints(minWidth: 20, minHeight: 20),
              padding: const EdgeInsets.symmetric(horizontal: 5),
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: warningOrange,
                shape: BoxShape.circle,
                border: Border.all(color: cream, width: 2),
              ),
              child: Text(
                '$count',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 10.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
      ],
    );
  }

  Widget _buildUpgradeBanner() {
    final noun = upgradePendingCount == 1 ? 'rider is' : 'riders are';

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: InkWell(
        onTap: () => _setSection('Vehicle Upgrades'),
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: infoBlue.withValues(alpha: 0.10),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: infoBlue.withValues(alpha: 0.35)),
          ),
          child: Row(
            children: [
              const Icon(Icons.upgrade_rounded, color: infoBlue, size: 22),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '$upgradePendingCount $noun requesting a vehicle upgrade',
                      style: const TextStyle(
                        color: darkMaroon,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    const Text(
                      'Tap to see who and review the request.',
                      style: TextStyle(color: textColor, fontSize: 12),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: infoBlue),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildBackToRiders() {
    return Align(
      alignment: Alignment.centerLeft,
      child: TextButton.icon(
        onPressed: () => _setSection('Registered Riders'),
        icon: const Icon(Icons.arrow_back_rounded, size: 18),
        label: const Text('Back to riders'),
        style: TextButton.styleFrom(
          foregroundColor: darkMaroon,
          padding: const EdgeInsets.symmetric(horizontal: 4),
          textStyle: const TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Vehicle upgrade section
  // ---------------------------------------------------------------------------

  List<Widget> _buildUpgradeSection() {
    final items = filteredUpgrades;
    const statuses = ['Pending', 'Approved', 'Declined', 'All'];

    return [
      SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: statuses.map((status) {
            final selected = selectedUpgradeStatus == status;

            return Padding(
              padding: const EdgeInsets.only(right: 8),
              child: ChoiceChip(
                label: Text(status),
                selected: selected,
                showCheckmark: false,
                onSelected: (_) =>
                    setState(() => selectedUpgradeStatus = status),
                selectedColor: darkMaroon,
                backgroundColor: paleCream,
                labelStyle: TextStyle(
                  color: selected ? Colors.white : maroon,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
                side: BorderSide(color: selected ? darkMaroon : borderColor),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(20),
                ),
              ),
            );
          }).toList(),
        ),
      ),
      const SizedBox(height: 18),
      Row(
        children: [
          const Expanded(
            child: Text(
              'Vehicle upgrade requests',
              style: TextStyle(
                color: darkMaroon,
                fontSize: 17,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          Text(
            '${items.length} ${items.length == 1 ? 'record' : 'records'}',
            style: const TextStyle(color: mutedColor, fontSize: 12),
          ),
        ],
      ),
      const SizedBox(height: 12),
      if (items.isEmpty)
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
          decoration: _cardDecoration(),
          child: Column(
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: darkMaroon.withValues(alpha: 0.08),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.upgrade_rounded,
                  size: 28,
                  color: darkMaroon,
                ),
              ),
              const SizedBox(height: 14),
              const Text(
                'No upgrade requests',
                style: TextStyle(
                  color: darkMaroon,
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                'Riders requesting a vehicle upgrade will appear here.',
                textAlign: TextAlign.center,
                style: TextStyle(color: mutedColor, fontSize: 13),
              ),
            ],
          ),
        )
      else
        ...items.map(_buildUpgradeCard),
    ];
  }

  Widget _buildUpgradeCard(_UpgradeRequest r) {
    final color = _statusColor(r.status);
    final isPending = r.status == 'Pending';

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: darkMaroon.withValues(alpha: 0.05),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: Container(
          decoration: BoxDecoration(
            color: paleCream,
            border: Border.all(color: borderColor),
            borderRadius: BorderRadius.circular(16),
          ),
          child: IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Container(width: 4, color: color),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Container(
                              width: 46,
                              height: 46,
                              alignment: Alignment.center,
                              decoration: BoxDecoration(
                                color: color.withValues(alpha: 0.12),
                                shape: BoxShape.circle,
                              ),
                              child: Text(
                                _initials(r.riderName),
                                style: TextStyle(
                                  color: color,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    r.riderName,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: textColor,
                                      fontSize: 15,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    r.riderId,
                                    style: const TextStyle(
                                      color: mutedColor,
                                      fontSize: 12,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            _statusChip(r.status, color),
                          ],
                        ),
                        const SizedBox(height: 14),
                        const Divider(height: 1, color: borderColor),
                        const SizedBox(height: 12),
                        _infoLine(
                          Icons.two_wheeler_rounded,
                          'From: ${r.currentVehicle} • ${r.currentPlate}',
                        ),
                        const SizedBox(height: 8),
                        _infoLine(
                          Icons.upgrade_rounded,
                          'To: ${r.requestedVehicle} • ${r.newPlate}',
                        ),
                        const SizedBox(height: 8),
                        _infoLine(
                          Icons.attach_file_rounded,
                          '${r.orCrFiles.length} OR/CR • '
                          '${r.vehiclePhotos.length} '
                          '${r.vehiclePhotos.length == 1 ? 'photo' : 'photos'}'
                          '${r.license != null ? ' • License' : ''}',
                        ),
                        const SizedBox(height: 8),
                        _infoLine(
                          Icons.event_outlined,
                          'Requested ${_UpgradeRequest.formatDate(r.requestedAt)}',
                        ),
                        const SizedBox(height: 14),
                        isPending
                            ? FilledButton.icon(
                                onPressed: () => _openUpgradeDetail(r),
                                icon: const Icon(
                                  Icons.fact_check_outlined,
                                  size: 18,
                                ),
                                label: const Text('Review request'),
                                style: FilledButton.styleFrom(
                                  backgroundColor: darkMaroon,
                                  foregroundColor: Colors.white,
                                  minimumSize: const Size.fromHeight(46),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  textStyle: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              )
                            : OutlinedButton.icon(
                                onPressed: () => _openUpgradeDetail(r),
                                icon: const Icon(
                                  Icons.visibility_outlined,
                                  size: 18,
                                ),
                                label: const Text('View request'),
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: darkMaroon,
                                  side: const BorderSide(color: darkMaroon),
                                  minimumSize: const Size.fromHeight(44),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  textStyle: const TextStyle(
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                        if (isPending) ...[
                          const SizedBox(height: 8),
                          const Text(
                            'Check the documents and photos before approving.',
                            textAlign: TextAlign.center,
                            style: TextStyle(color: mutedColor, fontSize: 11),
                          ),
                        ],
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

  // ---------------------------------------------------------------------------
  // Overview
  // ---------------------------------------------------------------------------

  Widget _buildAttentionBanner() {
    final noun = pendingCount == 1 ? 'application' : 'applications';

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: InkWell(
        onTap: () => _setSection('Applications'),
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: warningOrange.withValues(alpha: 0.10),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: warningOrange.withValues(alpha: 0.35)),
          ),
          child: Row(
            children: [
              const Icon(
                Icons.pending_actions_rounded,
                color: warningOrange,
                size: 22,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '$pendingCount $noun waiting for review',
                      style: const TextStyle(
                        color: darkMaroon,
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    const Text(
                      'Check the rider details and documents.',
                      style: TextStyle(color: textColor, fontSize: 12),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded, color: warningOrange),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildOverview(int available, int suspended) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16),
      decoration: _cardDecoration(),
      child: IntrinsicHeight(
        child: Row(
          children: [
            Expanded(
              child: _metric(
                '$pendingCount',
                'Pending',
                warningOrange,
                onTap: () => _setSection('Applications'),
              ),
            ),
            _metricDivider(),
            Expanded(
              child: _metric(
                '$registeredCount',
                'Registered',
                darkMaroon,
                onTap: () => _setSection('Registered Riders'),
              ),
            ),
            _metricDivider(),
            Expanded(child: _metric('$available', 'Available', successGreen)),
            _metricDivider(),
            Expanded(child: _metric('$suspended', 'Suspended', dangerRed)),
          ],
        ),
      ),
    );
  }

  Widget _metricDivider() {
    return const VerticalDivider(width: 1, thickness: 1, color: borderColor);
  }

  Widget _metric(
    String value,
    String label,
    Color color, {
    VoidCallback? onTap,
  }) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 2, horizontal: 4),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              value,
              style: TextStyle(
                color: color,
                fontSize: 24,
                fontWeight: FontWeight.w800,
                height: 1.1,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: mutedColor, fontSize: 11),
            ),
          ],
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Section selector, search and filters
  // ---------------------------------------------------------------------------

  Widget _buildSectionSelector() {
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: darkMaroon.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Expanded(
            child: _sectionButton('Applications', 'Applications', pendingCount),
          ),
          const SizedBox(width: 4),
          Expanded(
            child: _sectionButton(
              'Registered Riders',
              'Registered',
              registeredCount,
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionButton(String section, String label, int count) {
    final selected = selectedSection == section;

    return InkWell(
      borderRadius: BorderRadius.circular(11),
      onTap: () => _setSection(section),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
        decoration: BoxDecoration(
          color: selected ? darkMaroon : Colors.transparent,
          borderRadius: BorderRadius.circular(11),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: selected ? Colors.white : maroon,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(width: 8),
            Container(
              constraints: const BoxConstraints(minWidth: 22),
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
              decoration: BoxDecoration(
                color: selected
                    ? Colors.white.withValues(alpha: 0.22)
                    : darkMaroon.withValues(alpha: 0.10),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                '$count',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: selected ? Colors.white : darkMaroon,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSearchField() {
    return TextField(
      controller: _searchController,
      onChanged: (value) => setState(() => searchQuery = value),
      style: const TextStyle(color: textColor, fontSize: 14),
      decoration: InputDecoration(
        hintText: 'Search by rider name or ID',
        hintStyle: const TextStyle(color: mutedColor, fontSize: 14),
        prefixIcon: const Icon(Icons.search_rounded, color: brown),
        suffixIcon: searchQuery.isEmpty
            ? null
            : IconButton(
                icon: const Icon(Icons.close_rounded, color: brown, size: 20),
                onPressed: () {
                  setState(() {
                    searchQuery = '';
                    _searchController.clear();
                  });
                },
              ),
        filled: true,
        fillColor: paleCream,
        contentPadding: const EdgeInsets.symmetric(vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: borderColor),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: borderColor),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: darkMaroon, width: 1.5),
        ),
      ),
    );
  }

  Widget _buildStatusFilters() {
    final statuses = _isApplications
        ? ['All', 'Pending Approval', 'Declined']
        : [
            'All',
            'Available',
            'On Delivery',
            'Inactive',
            'Suspended',
            'Terminated',
          ];

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: statuses.map((status) {
          final selected = selectedStatus == status;

          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: ChoiceChip(
              label: Text(status),
              selected: selected,
              showCheckmark: false,
              onSelected: (_) => setState(() => selectedStatus = status),
              selectedColor: darkMaroon,
              backgroundColor: paleCream,
              labelStyle: TextStyle(
                color: selected ? Colors.white : maroon,
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
              side: BorderSide(color: selected ? darkMaroon : borderColor),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Empty state
  // ---------------------------------------------------------------------------

  Widget _buildEmptyState() {
    final filtering = searchQuery.trim().isNotEmpty || selectedStatus != 'All';

    final title = filtering
        ? 'No matching riders'
        : _isApplications
        ? 'No applications yet'
        : 'No registered riders yet';

    final message = filtering
        ? 'Try a different name, ID or status.'
        : _isApplications
        ? 'New rider applications will appear here.'
        : 'Approved riders will appear in this section.';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
      decoration: _cardDecoration(),
      child: Column(
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: darkMaroon.withValues(alpha: 0.08),
              shape: BoxShape.circle,
            ),
            child: Icon(
              filtering
                  ? Icons.search_off_rounded
                  : _isApplications
                  ? Icons.assignment_outlined
                  : Icons.people_outline,
              size: 28,
              color: darkMaroon,
            ),
          ),
          const SizedBox(height: 14),
          Text(
            title,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 15,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: mutedColor, fontSize: 13),
          ),
          if (filtering) ...[
            const SizedBox(height: 12),
            TextButton(
              onPressed: _clearFilters,
              child: const Text(
                'Clear filters',
                style: TextStyle(
                  color: darkMaroon,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Rider card
  // ---------------------------------------------------------------------------

  Widget _statusChip(String status, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
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
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildRiderCard(Map<String, dynamic> rider) {
    final status = rider['status'] as String;
    final color = _statusColor(status);
    final isPending = status == 'Pending Approval';
    final isRegistered = !_isApplications;

    final location = '${rider['area'] ?? rider['address'] ?? 'Not provided'}';
    final upgrade = _pendingFor('${rider['id']}');

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: darkMaroon.withValues(alpha: 0.05),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: Container(
          decoration: BoxDecoration(
            color: paleCream,
            border: Border.all(color: borderColor),
            borderRadius: BorderRadius.circular(16),
          ),
          child: IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Container(width: 4, color: color),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 46,
                              height: 46,
                              alignment: Alignment.center,
                              decoration: BoxDecoration(
                                color: color.withValues(alpha: 0.12),
                                shape: BoxShape.circle,
                              ),
                              child: Text(
                                _initials('${rider['name']}'),
                                style: TextStyle(
                                  color: color,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    '${rider['name']}',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: textColor,
                                      fontSize: 15,
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    '${rider['id']}  •  ${rider['vehicle'] ?? 'Vehicle not provided'}',
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                      color: mutedColor,
                                      fontSize: 12,
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  Wrap(
                                    spacing: 6,
                                    runSpacing: 6,
                                    children: [
                                      _statusChip(status, color),
                                      if (upgrade != null)
                                        _statusChip('Upgrading', infoBlue),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        const Divider(height: 1, color: borderColor),
                        const SizedBox(height: 12),
                        _infoLine(
                          Icons.warehouse_outlined,
                          '${rider['hub'] ?? 'Hub not assigned'}',
                        ),
                        const SizedBox(height: 8),
                        _infoLine(Icons.location_on_outlined, location),
                        const SizedBox(height: 8),
                        _infoLine(
                          Icons.phone_outlined,
                          '${rider['phone'] ?? 'Phone not provided'}',
                        ),
                        if (upgrade != null) ...[
                          const SizedBox(height: 8),
                          _infoLine(
                            Icons.upgrade_rounded,
                            'Upgrading to ${upgrade.requestedVehicle}',
                          ),
                        ],
                        if (_isApplications && rider['joined'] != null) ...[
                          const SizedBox(height: 8),
                          _infoLine(
                            Icons.event_outlined,
                            'Applied ${rider['joined']}',
                          ),
                        ],
                        if (isRegistered) ...[
                          const SizedBox(height: 14),
                          _buildPerformanceStrip(rider),
                        ],
                        const SizedBox(height: 14),
                        if (isPending)
                          _buildPendingActions(rider)
                        else if (_isApplications)
                          _buildHistoryActions(rider)
                        else
                          _buildRegisteredActions(rider, status),
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

  Widget _buildPerformanceStrip(Map<String, dynamic> rider) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        color: cream,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: borderColor),
      ),
      child: IntrinsicHeight(
        child: Row(
          children: [
            Expanded(
              child: _miniStat(
                '${rider['delivered'] ?? 0}',
                'Delivered',
                successGreen,
              ),
            ),
            _metricDivider(),
            Expanded(
              child: _miniStat('${rider['ongoing'] ?? 0}', 'Ongoing', infoBlue),
            ),
            _metricDivider(),
            Expanded(
              child: _miniStat('${rider['failed'] ?? 0}', 'Failed', dangerRed),
            ),
          ],
        ),
      ),
    );
  }

  Widget _miniStat(String value, String label, Color color) {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Text(
          value,
          style: TextStyle(
            color: color,
            fontSize: 17,
            fontWeight: FontWeight.w800,
            height: 1.1,
          ),
        ),
        const SizedBox(height: 2),
        Text(label, style: const TextStyle(color: mutedColor, fontSize: 11)),
      ],
    );
  }

  Widget _buildPendingActions(Map<String, dynamic> rider) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        FilledButton.icon(
          onPressed: () => _openProfile(rider),
          icon: const Icon(Icons.fact_check_outlined, size: 18),
          label: const Text('Review application'),
          style: FilledButton.styleFrom(
            backgroundColor: darkMaroon,
            foregroundColor: Colors.white,
            minimumSize: const Size.fromHeight(46),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            textStyle: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
        const SizedBox(height: 8),
        const Text(
          'Approve or decline after checking the rider details and documents.',
          textAlign: TextAlign.center,
          style: TextStyle(color: mutedColor, fontSize: 11),
        ),
      ],
    );
  }

  Widget _buildHistoryActions(Map<String, dynamic> rider) {
    return Row(
      children: [
        const Expanded(
          child: Text(
            'Application history',
            style: TextStyle(color: mutedColor, fontSize: 12),
          ),
        ),
        TextButton.icon(
          onPressed: () => _openProfile(rider),
          icon: const Icon(Icons.visibility_outlined, size: 16),
          label: const Text('View record'),
          style: TextButton.styleFrom(
            foregroundColor: darkMaroon,
            textStyle: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
      ],
    );
  }

  Widget _buildRegisteredActions(Map<String, dynamic> rider, String status) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => _openProfile(rider),
                icon: const Icon(Icons.person_outline, size: 18),
                label: const Text('View profile'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: darkMaroon,
                  side: const BorderSide(color: darkMaroon),
                  minimumSize: const Size.fromHeight(44),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  textStyle: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
            ),
            _buildManageMenu(rider),
          ],
        ),
        if (status == 'Suspended' || status == 'Inactive') ...[
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: () => _reactivateRider(rider),
            icon: const Icon(Icons.refresh_rounded, size: 18),
            label: const Text('Reactivate'),
            style: OutlinedButton.styleFrom(
              foregroundColor: successGreen,
              side: const BorderSide(color: successGreen),
              minimumSize: const Size.fromHeight(44),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              textStyle: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        ],
        if (status == 'Terminated') ...[
          const SizedBox(height: 10),
          const Row(
            children: [
              Icon(Icons.info_outline_rounded, color: dangerRed, size: 16),
              SizedBox(width: 6),
              Expanded(
                child: Text(
                  'Account terminated. The record is retained.',
                  style: TextStyle(
                    color: dangerRed,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }

  Widget _infoLine(IconData icon, String value) {
    return Row(
      children: [
        Icon(icon, color: brown, size: 17),
        const SizedBox(width: 9),
        Expanded(
          child: Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: textColor, fontSize: 13),
          ),
        ),
      ],
    );
  }

  Widget _buildManageMenu(Map<String, dynamic> rider) {
    final status = rider['status'] as String;
    final options = <PopupMenuEntry<String>>[];

    if (status == 'Available' || status == 'Inactive') {
      options.add(
        const PopupMenuItem(value: 'suspend', child: Text('Suspend rider')),
      );
    }

    if (status == 'Available' ||
        status == 'Inactive' ||
        status == 'Suspended') {
      options.add(
        const PopupMenuItem(
          value: 'terminate',
          child: Text(
            'Terminate rider',
            style: TextStyle(color: dangerRed, fontWeight: FontWeight.w600),
          ),
        ),
      );
    }

    if (options.isEmpty) return const SizedBox.shrink();

    return PopupMenuButton<String>(
      tooltip: 'Manage rider',
      color: paleCream,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      icon: const Icon(Icons.more_vert_rounded, color: darkMaroon),
      onSelected: (value) {
        if (value == 'suspend') _suspendRider(rider);
        if (value == 'terminate') _terminateRider(rider);
      },
      itemBuilder: (_) => options,
    );
  }

  String _initials(String name) {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((s) => s.isNotEmpty)
        .toList();

    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first[0].toUpperCase();

    return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
  }
}

// -----------------------------------------------------------------------------
// Upgrade request detail (read-only version of the rider's upgrade form)
// -----------------------------------------------------------------------------

class _UpgradeRequestDetailScreen extends StatefulWidget {
  final _UpgradeRequest request;
  final Future<bool> Function() onApprove;
  final Future<bool> Function() onDecline;

  const _UpgradeRequestDetailScreen({
    required this.request,
    required this.onApprove,
    required this.onDecline,
  });

  @override
  State<_UpgradeRequestDetailScreen> createState() =>
      _UpgradeRequestDetailScreenState();
}

class _UpgradeRequestDetailScreenState
    extends State<_UpgradeRequestDetailScreen> {
  bool _busy = false;

  _UpgradeRequest get r => widget.request;

  Future<void> _run(Future<bool> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);

    final done = await action();

    if (!mounted) return;
    setState(() => _busy = false);

    if (done) Navigator.pop(context);
  }

  void _openPhotos(List<_UpgradeAttachment> items, int index, String title) {
    Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (_) =>
            _ImageViewerScreen(title: title, items: items, initialIndex: index),
      ),
    );
  }

  void _openDocument(_UpgradeAttachment file) {
    // TODO: open the real file. With the open_filex package:
    //   OpenFilex.open(file.path);
    // For URLs, use url_launcher instead.
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text('Opening ${file.name}...'),
          backgroundColor: darkMaroon,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    final color = _upgradeStatusColor(r.status);
    final isPending = r.status == 'Pending';

    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: cream,
        elevation: 0,
        scrolledUnderElevation: 0,
        foregroundColor: darkMaroon,
        title: const Text(
          'Upgrade Request',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 20),
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: ListView(
                physics: const BouncingScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
                children: [
                  _riderHeader(color),
                  const SizedBox(height: 24),
                  _section('Vehicle'),
                  const SizedBox(height: 12),
                  _vehicleCard(),
                  const SizedBox(height: 26),
                  _section('Documents'),
                  const SizedBox(height: 4),
                  const Text(
                    'Files uploaded by the rider. Tap to open.',
                    style: TextStyle(fontSize: 12, color: mutedColor),
                  ),
                  const SizedBox(height: 12),
                  _orCrCard(),
                  _photosCard(),
                  _licenseCard(),
                  const SizedBox(height: 16),
                  _section('Notes'),
                  const SizedBox(height: 12),
                  _notesCard(),
                ],
              ),
            ),
            _bottomBar(isPending, color),
          ],
        ),
      ),
    );
  }

  // ------------------------- Header -------------------------

  Widget _riderHeader(Color color) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _card(),
      child: Row(
        children: [
          Container(
            width: 50,
            height: 50,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              shape: BoxShape.circle,
            ),
            child: Text(
              _initials(r.riderName),
              style: TextStyle(color: color, fontWeight: FontWeight.w800),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  r.riderName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: textColor,
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '${r.riderId} • Requested ${_UpgradeRequest.formatDate(r.requestedAt)}',
                  style: const TextStyle(color: mutedColor, fontSize: 12),
                ),
              ],
            ),
          ),
          _chip(r.status, color),
        ],
      ),
    );
  }

  // ------------------------- Vehicle -------------------------

  Widget _vehicleCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: _card(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _fieldLabel('Current vehicle'),
          const SizedBox(height: 8),
          _valueBox(
            _vehicleIcon(r.currentVehicle),
            r.currentPlate.isEmpty
                ? r.currentVehicle
                : '${r.currentVehicle} • ${r.currentPlate}',
          ),
          const SizedBox(height: 16),
          _fieldLabel('Upgrade to'),
          const SizedBox(height: 8),
          _valueBox(_vehicleIcon(r.requestedVehicle), r.requestedVehicle),
          const SizedBox(height: 16),
          _fieldLabel('Plate number'),
          const SizedBox(height: 8),
          _valueBox(Icons.pin_outlined, r.newPlate),
        ],
      ),
    );
  }

  Widget _valueBox(IconData icon, String value) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      decoration: BoxDecoration(
        color: cream,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Icon(icon, color: darkMaroon, size: 19),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              value.isEmpty ? '-' : value,
              style: const TextStyle(
                color: darkMaroon,
                fontWeight: FontWeight.w700,
                fontSize: 13.5,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ------------------------- OR/CR -------------------------

  Widget _orCrCard() {
    return _docCard(
      title: 'OR/CR',
      subtitle: 'Official Receipt and Certificate of Registration.',
      icon: Icons.description_rounded,
      badge: '${r.orCrFiles.length}',
      child: r.orCrFiles.isEmpty
          ? _emptyNote('No OR/CR files uploaded.')
          : Column(children: r.orCrFiles.map(_orCrRow).toList()),
    );
  }

  Widget _orCrRow(_UpgradeAttachment file) {
    final isPdf = file.isPdf;
    final tint = isPdf ? dangerRed : const Color(0xFF1E5AA8);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () => _openDocument(file),
        child: Container(
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
          decoration: BoxDecoration(
            color: cream,
            borderRadius: BorderRadius.circular(14),
          ),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: tint.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  isPdf
                      ? Icons.picture_as_pdf_rounded
                      : Icons.description_rounded,
                  color: tint,
                  size: 19,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      file.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: darkMaroon,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      [
                        file.ext.toUpperCase(),
                        file.sizeLabel,
                      ].where((s) => s.isNotEmpty).join(' • '),
                      style: const TextStyle(color: mutedColor, fontSize: 10.5),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.open_in_new_rounded, color: brown, size: 18),
            ],
          ),
        ),
      ),
    );
  }

  // ------------------------- Photos -------------------------

  Widget _photosCard() {
    return _docCard(
      title: 'Vehicle Photos',
      subtitle: 'Photos of the whole vehicle and its plate.',
      icon: Icons.photo_camera_rounded,
      badge: '${r.vehiclePhotos.length}',
      child: r.vehiclePhotos.isEmpty
          ? _emptyNote('No vehicle photos uploaded.')
          : Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                for (int i = 0; i < r.vehiclePhotos.length; i++)
                  GestureDetector(
                    onTap: () =>
                        _openPhotos(r.vehiclePhotos, i, 'Vehicle Photos'),
                    child: SizedBox(
                      width: 88,
                      height: 88,
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(14),
                        child: _attachmentImage(r.vehiclePhotos[i]),
                      ),
                    ),
                  ),
              ],
            ),
    );
  }

  // ------------------------- License -------------------------

  Widget _licenseCard() {
    final license = r.license;

    return _docCard(
      title: "Driver's License",
      subtitle: 'Optional. Provided if the new vehicle needs a different code.',
      icon: Icons.badge_rounded,
      child: license == null
          ? _emptyNote('Not provided.')
          : InkWell(
              borderRadius: BorderRadius.circular(14),
              onTap: () => _openPhotos([license], 0, "Driver's License"),
              child: Container(
                padding: const EdgeInsets.fromLTRB(10, 10, 12, 10),
                decoration: BoxDecoration(
                  color: cream,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Row(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(10),
                      child: SizedBox(
                        width: 56,
                        height: 56,
                        child: _attachmentImage(license),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            license.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: darkMaroon,
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            [
                              license.ext.toUpperCase(),
                              license.sizeLabel,
                            ].where((s) => s.isNotEmpty).join(' • '),
                            style: const TextStyle(
                              color: mutedColor,
                              fontSize: 10.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.zoom_in_rounded, color: brown, size: 20),
                  ],
                ),
              ),
            ),
    );
  }

  // ------------------------- Notes -------------------------

  Widget _notesCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: _card(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _fieldLabel('Reason for upgrade'),
          const SizedBox(height: 8),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: cream,
              borderRadius: BorderRadius.circular(14),
            ),
            child: Text(
              r.reason.trim().isEmpty ? 'No reason provided.' : r.reason,
              style: TextStyle(
                color: r.reason.trim().isEmpty ? mutedColor : textColor,
                fontSize: 13.5,
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ------------------------- Bottom bar -------------------------

  Widget _bottomBar(bool isPending, Color color) {
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 14),
      decoration: BoxDecoration(
        color: paleCream,
        border: const Border(top: BorderSide(color: borderColor)),
        boxShadow: [
          BoxShadow(
            color: darkMaroon.withValues(alpha: 0.06),
            blurRadius: 12,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: isPending
          ? Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _busy ? null : () => _run(widget.onDecline),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: dangerRed,
                      side: const BorderSide(color: dangerRed),
                      minimumSize: const Size.fromHeight(48),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      textStyle: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    child: const Text('Decline'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton(
                    onPressed: _busy ? null : () => _run(widget.onApprove),
                    style: FilledButton.styleFrom(
                      backgroundColor: successGreen,
                      foregroundColor: Colors.white,
                      minimumSize: const Size.fromHeight(48),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      textStyle: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    child: const Text('Approve'),
                  ),
                ),
              ],
            )
          : Row(
              children: [
                Icon(
                  r.status == 'Approved'
                      ? Icons.check_circle_rounded
                      : Icons.cancel_rounded,
                  color: color,
                  size: 20,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    r.status == 'Approved'
                        ? 'Upgrade approved. Rider vehicle updated.'
                        : 'Upgrade request declined.',
                    style: TextStyle(
                      color: color,
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
    );
  }

  // ------------------------- Shared helpers -------------------------

  BoxDecoration _card({Color? border, double borderWidth = 1}) {
    return BoxDecoration(
      color: paleCream,
      borderRadius: BorderRadius.circular(22),
      border: Border.all(color: border ?? borderColor, width: borderWidth),
      boxShadow: [
        BoxShadow(
          color: darkMaroon.withValues(alpha: 0.05),
          blurRadius: 14,
          offset: const Offset(0, 5),
        ),
      ],
    );
  }

  Widget _docCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required Widget child,
    String? badge,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: _card(),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: darkMaroon.withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(icon, color: darkMaroon, size: 20),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontSize: 14.5,
                          fontWeight: FontWeight.w800,
                          color: darkMaroon,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          fontSize: 11,
                          height: 1.3,
                          color: mutedColor,
                        ),
                      ),
                    ],
                  ),
                ),
                if (badge != null)
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: maroon.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      badge,
                      style: const TextStyle(
                        color: maroon,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 14),
            child,
          ],
        ),
      ),
    );
  }

  Widget _emptyNote(String text) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: cream,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Text(
        text,
        style: const TextStyle(color: mutedColor, fontSize: 12.5),
      ),
    );
  }

  Widget _section(String label) {
    return Text(
      label,
      style: const TextStyle(
        fontSize: 18.5,
        fontWeight: FontWeight.w800,
        color: darkMaroon,
        letterSpacing: -0.3,
      ),
    );
  }

  Widget _fieldLabel(String label) {
    return Text(
      label,
      style: const TextStyle(
        fontSize: 12.5,
        fontWeight: FontWeight.w700,
        color: darkMaroon,
      ),
    );
  }

  Widget _chip(String status, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
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
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  String _initials(String name) {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((s) => s.isNotEmpty)
        .toList();

    if (parts.isEmpty) return '?';
    if (parts.length == 1) return parts.first[0].toUpperCase();

    return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
  }
}

// -----------------------------------------------------------------------------
// Full-screen image viewer (swipe between photos, pinch to zoom)
// -----------------------------------------------------------------------------

class _ImageViewerScreen extends StatefulWidget {
  final String title;
  final List<_UpgradeAttachment> items;
  final int initialIndex;

  const _ImageViewerScreen({
    required this.title,
    required this.items,
    this.initialIndex = 0,
  });

  @override
  State<_ImageViewerScreen> createState() => _ImageViewerScreenState();
}

class _ImageViewerScreenState extends State<_ImageViewerScreen> {
  late final PageController _controller;
  late int _index;

  @override
  void initState() {
    super.initState();
    _index = widget.initialIndex;
    _controller = PageController(initialPage: widget.initialIndex);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        elevation: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.title,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
            ),
            Text(
              '${_index + 1} of ${widget.items.length}',
              style: const TextStyle(fontSize: 11, color: Colors.white70),
            ),
          ],
        ),
      ),
      body: PageView.builder(
        controller: _controller,
        itemCount: widget.items.length,
        onPageChanged: (i) => setState(() => _index = i),
        itemBuilder: (_, i) => InteractiveViewer(
          minScale: 1,
          maxScale: 5,
          child: Center(
            child: _attachmentImage(widget.items[i], fit: BoxFit.contain),
          ),
        ),
      ),
    );
  }
}

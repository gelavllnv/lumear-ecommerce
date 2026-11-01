import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutter_application_1/models/rider_profile.dart';

class UpgradeScreen extends StatefulWidget {
  const UpgradeScreen({super.key});

  // ============================================================
  // SHARED UPGRADE STATE
  // Kept static (like AssignmentScreen) so the profile screen can
  // read it. Replace with your backend / provider when ready.
  // ============================================================

  static String status = 'none'; // 'none' | 'pending'
  static String requestedVehicle = '';
  static String requestedPlate = '';
  static String requestedNotes = '';
  static int requestedOrCrCount = 0;
  static int requestedPhotoCount = 0;
  static bool requestedHasLicense = false;

  static bool get isPending => status == 'pending';

  @override
  State<UpgradeScreen> createState() => _UpgradeScreenState();
}

class _UpgradeScreenState extends State<UpgradeScreen> {
  // ============================================================
  // DESIGN SYSTEM
  // ============================================================

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color textMuted = Color(0xFF8B6F63);
  static const Color success = Color(0xFF2E7D32);
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

  // ============================================================
  // RULES (change these to adjust requirements)
  // ============================================================

  // OR and CR are two documents, so at least 2 files are required.
  // Set to 1 if riders may upload a single combined PDF.
  static const int _minOrCrFiles = 2;
  static const int _maxOrCrFiles = 6;
  static const int _maxVehiclePhotos = 6;
  static const int _maxDocSizeMb = 10;

  static const List<String> _orCrExtensions = ['pdf', 'doc', 'docx'];
  static const List<String> _licenseExtensions = ['jpg', 'jpeg', 'png'];

  // ============================================================
  // FORM STATE
  // ============================================================

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final ImagePicker _picker = ImagePicker();

  final TextEditingController _plateController = TextEditingController();
  final TextEditingController _notesController = TextEditingController();

  static const List<String> _allVehicleOptions = [
    'Motorcycle',
    'Tricycle',
    'Van',
    'Pickup Van',
    'Truck',
  ];

  String? _selectedVehicle;

  final List<File> _orCrFiles = [];
  final List<File> _vehiclePhotos = [];
  File? _licenseImage;

  bool _isSubmitting = false;
  bool _showDocumentErrors = false;

  // ============================================================
  // HELPERS
  // ============================================================

  String get _currentVehicle {
    final String raw = RiderProfile.vehicleType.trim();
    if (raw.isEmpty) return 'Motorcycle';
    return raw[0].toUpperCase() + raw.substring(1);
  }

  List<String> get _upgradeOptions => _allVehicleOptions
      .where((v) => v.toLowerCase() != _currentVehicle.toLowerCase())
      .toList();

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

  String _fileName(File file) => file.path.split(RegExp(r'[\\/]')).last;

  String _fileExt(String path) {
    final String name = path.split(RegExp(r'[\\/]')).last;
    final int dot = name.lastIndexOf('.');
    if (dot == -1) return '';
    return name.substring(dot + 1).toLowerCase();
  }

  String _fileSize(File file) {
    try {
      final int bytes = file.lengthSync();
      if (bytes < 1024) return '$bytes B';
      if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(0)} KB';
      return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
    } catch (_) {
      return '';
    }
  }

  @override
  void dispose() {
    _plateController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  // ============================================================
  // PICKERS
  // ============================================================

  // ---------- OR/CR: PDF / Word, multiple ----------

  Future<void> _pickOrCrFiles() async {
    final int remaining = _maxOrCrFiles - _orCrFiles.length;

    if (remaining <= 0) {
      _showMessage('You can upload up to $_maxOrCrFiles files.', isError: true);
      return;
    }

    try {
      // file_picker v12+: static call, multi-select by default,
      // returns an empty list when the user cancels.
      final List<PlatformFile> pickedFiles = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: _orCrExtensions,
      );

      if (pickedFiles.isEmpty || !mounted) return;

      int skippedType = 0;
      int skippedSize = 0;
      int skippedLimit = 0;
      int skippedDuplicate = 0;

      setState(() {
        for (final PlatformFile picked in pickedFiles) {
          final String? path = picked.path;
          if (path == null) continue;

          if (!_orCrExtensions.contains(_fileExt(path))) {
            skippedType++;
            continue;
          }

          if (File(path).lengthSync() > _maxDocSizeMb * 1024 * 1024) {
            skippedSize++;
            continue;
          }

          if (_orCrFiles.any((f) => f.path == path)) {
            skippedDuplicate++;
            continue;
          }

          if (_orCrFiles.length >= _maxOrCrFiles) {
            skippedLimit++;
            continue;
          }

          _orCrFiles.add(File(path));
        }
      });

      if (skippedType > 0) {
        _showMessage('Only PDF or Word files are accepted.', isError: true);
      } else if (skippedSize > 0) {
        _showMessage(
          'Files must be $_maxDocSizeMb MB or smaller.',
          isError: true,
        );
      } else if (skippedLimit > 0) {
        _showMessage('Maximum of $_maxOrCrFiles files reached.', isError: true);
      } else if (skippedDuplicate > 0) {
        _showMessage('That file was already added.', isError: true);
      }
    } catch (e) {
      if (!mounted) return;
      _showMessage('Unable to load the file. Please try again.', isError: true);
    }
  }

  // ---------- VEHICLE PHOTOS: camera (repeatable) or gallery (multiple) ----------

  Future<void> _addVehiclePhotos() async {
    final int remaining = _maxVehiclePhotos - _vehiclePhotos.length;

    if (remaining <= 0) {
      _showMessage(
        'You can upload up to $_maxVehiclePhotos photos.',
        isError: true,
      );
      return;
    }

    final String? action = await _showSourceSheet(
      title: 'Add Vehicle Photos',
      cameraLabel: 'Take Photo',
      galleryLabel: 'Choose from Gallery (multiple)',
    );

    if (action == null) return;

    try {
      if (action == 'camera') {
        final XFile? shot = await _picker.pickImage(
          source: ImageSource.camera,
          imageQuality: 80,
        );

        if (shot == null || !mounted) return;

        setState(() => _vehiclePhotos.add(File(shot.path)));

        if (_vehiclePhotos.length < _maxVehiclePhotos) {
          _showMessage('Photo added. Tap + to take another.');
        }
      } else {
        List<XFile> picked;

        // image_picker requires limit >= 2 for multi-select.
        if (remaining == 1) {
          final XFile? single = await _picker.pickImage(
            source: ImageSource.gallery,
            imageQuality: 80,
          );
          picked = single == null ? [] : [single];
        } else {
          picked = await _picker.pickMultiImage(
            imageQuality: 80,
            limit: remaining,
          );
        }

        if (picked.isEmpty || !mounted) return;

        setState(() {
          for (final XFile x in picked.take(remaining)) {
            if (!_vehiclePhotos.any((f) => f.path == x.path)) {
              _vehiclePhotos.add(File(x.path));
            }
          }
        });
      }
    } catch (e) {
      if (!mounted) return;
      _showMessage(
        'Unable to load the photo. Please try again.',
        isError: true,
      );
    }
  }

  // ---------- LICENSE: JPG / PNG only ----------

  Future<void> _pickLicense() async {
    final String? action = await _showSourceSheet(
      title: "Upload Driver's License",
      cameraLabel: 'Take Photo',
      galleryLabel: 'Choose from Gallery',
      showRemove: _licenseImage != null,
    );

    if (action == null) return;

    if (action == 'remove') {
      setState(() => _licenseImage = null);
      return;
    }

    try {
      final XFile? picked = await _picker.pickImage(
        source: action == 'camera' ? ImageSource.camera : ImageSource.gallery,
        imageQuality: 85,
      );

      if (picked == null || !mounted) return;

      if (!_licenseExtensions.contains(_fileExt(picked.path))) {
        _showMessage(
          "Driver's license must be a JPG or PNG image.",
          isError: true,
        );
        return;
      }

      setState(() => _licenseImage = File(picked.path));
    } catch (e) {
      if (!mounted) return;
      _showMessage(
        'Unable to load the image. Please try again.',
        isError: true,
      );
    }
  }

  // ============================================================
  // SOURCE SHEET
  // ============================================================

  Future<String?> _showSourceSheet({
    required String title,
    required String cameraLabel,
    required String galleryLabel,
    bool showRemove = false,
  }) {
    return showModalBottomSheet<String>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return Container(
          decoration: const BoxDecoration(
            color: cream,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 14),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: brownBeige.withValues(alpha: 0.35),
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  const SizedBox(height: 18),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      title,
                      style: const TextStyle(
                        color: darkMaroon,
                        fontSize: 16.5,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.2,
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  _sheetOption(
                    icon: Icons.camera_alt_rounded,
                    label: cameraLabel,
                    onTap: () => Navigator.pop(sheetContext, 'camera'),
                  ),
                  const SizedBox(height: 10),
                  _sheetOption(
                    icon: Icons.photo_library_rounded,
                    label: galleryLabel,
                    onTap: () => Navigator.pop(sheetContext, 'gallery'),
                  ),
                  if (showRemove) ...[
                    const SizedBox(height: 10),
                    _sheetOption(
                      icon: Icons.delete_outline_rounded,
                      label: 'Remove',
                      color: danger,
                      onTap: () => Navigator.pop(sheetContext, 'remove'),
                    ),
                  ],
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _sheetOption({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
    Color color = darkMaroon,
  }) {
    return Material(
      color: surface,
      borderRadius: BorderRadius.circular(radiusMd),
      child: InkWell(
        borderRadius: BorderRadius.circular(radiusMd),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 14),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(11),
                ),
                child: Icon(icon, color: color, size: 19),
              ),
              const SizedBox(width: 13),
              Expanded(
                child: Text(
                  label,
                  style: TextStyle(
                    color: color,
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
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
  // MESSAGES
  // ============================================================

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: isError ? danger : darkMaroon,
          margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radiusSm),
          ),
          content: Text(
            message,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w600,
              fontSize: 13,
            ),
          ),
        ),
      );
  }

  // ============================================================
  // SUBMIT / CANCEL
  // ============================================================

  Future<void> _submit() async {
    setState(() => _showDocumentErrors = true);

    final bool formValid = _formKey.currentState?.validate() ?? false;
    final bool docsValid =
        _orCrFiles.length >= _minOrCrFiles && _vehiclePhotos.isNotEmpty;

    if (!formValid || !docsValid) {
      _showMessage('Please complete all required fields.', isError: true);
      return;
    }

    setState(() => _isSubmitting = true);

    // TODO: Replace with a real API call that uploads the form + files.
    await Future.delayed(const Duration(milliseconds: 1200));

    if (!mounted) return;

    UpgradeScreen.status = 'pending';
    UpgradeScreen.requestedVehicle = _selectedVehicle ?? '';
    UpgradeScreen.requestedPlate = _plateController.text.trim().toUpperCase();
    UpgradeScreen.requestedNotes = _notesController.text.trim();
    UpgradeScreen.requestedOrCrCount = _orCrFiles.length;
    UpgradeScreen.requestedPhotoCount = _vehiclePhotos.length;
    UpgradeScreen.requestedHasLicense = _licenseImage != null;

    setState(() => _isSubmitting = false);

    _showMessage('Upgrade request submitted.');
  }

  Future<void> _confirmCancelRequest() async {
    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          backgroundColor: cream,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(22),
          ),
          title: const Text(
            'Cancel Request',
            style: TextStyle(
              color: darkMaroon,
              fontWeight: FontWeight.w800,
              fontSize: 17,
            ),
          ),
          content: const Text(
            'Do you want to cancel your upgrade request? You can submit a new one anytime.',
            style: TextStyle(color: textMuted, fontSize: 13.5, height: 1.4),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text(
                'Keep Request',
                style: TextStyle(color: textMuted, fontWeight: FontWeight.w700),
              ),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              style: ElevatedButton.styleFrom(
                backgroundColor: danger,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(radiusSm),
                ),
              ),
              child: const Text(
                'Yes, Cancel',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        );
      },
    );

    if (confirmed != true || !mounted) return;

    setState(() {
      UpgradeScreen.status = 'none';
      UpgradeScreen.requestedVehicle = '';
      UpgradeScreen.requestedPlate = '';
      UpgradeScreen.requestedNotes = '';
      UpgradeScreen.requestedOrCrCount = 0;
      UpgradeScreen.requestedPhotoCount = 0;
      UpgradeScreen.requestedHasLicense = false;

      _selectedVehicle = null;
      _plateController.clear();
      _notesController.clear();
      _orCrFiles.clear();
      _vehiclePhotos.clear();
      _licenseImage = null;
      _showDocumentErrors = false;
    });

    _showMessage('Upgrade request cancelled.');
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: cream,
        elevation: 0,
        scrolledUnderElevation: 0,
        foregroundColor: darkMaroon,
        title: const Text(
          'Upgrade Vehicle',
          style: TextStyle(
            fontWeight: FontWeight.w800,
            fontSize: 20,
            letterSpacing: -0.3,
          ),
        ),
      ),
      body: SafeArea(
        child: GestureDetector(
          onTap: () => FocusScope.of(context).unfocus(),
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
            child: UpgradeScreen.isPending
                ? _buildPendingView()
                : _buildFormView(),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // PENDING VIEW
  // ============================================================

  Widget _buildPendingView() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.fromLTRB(22, 26, 22, 24),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [darkMaroon, maroon],
            ),
            borderRadius: BorderRadius.circular(26),
            boxShadow: [
              BoxShadow(
                color: darkMaroon.withValues(alpha: 0.25),
                blurRadius: 20,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: Column(
            children: [
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.16),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.hourglass_top_rounded,
                  color: Colors.white,
                  size: 30,
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Upgrade Pending',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 21,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.3,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                'Your request is under review by the admin. We will notify you once it has been approved.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.8),
                  fontSize: 12.5,
                  height: 1.4,
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: 20),

        Container(
          padding: const EdgeInsets.all(16),
          decoration: cardDecoration,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _summaryRow(
                'Current vehicle',
                _currentVehicle,
                icon: _vehicleIcon(_currentVehicle),
              ),
              _summaryDivider(),
              _summaryRow(
                'Upgrading to',
                UpgradeScreen.requestedVehicle,
                icon: _vehicleIcon(UpgradeScreen.requestedVehicle),
              ),
              _summaryDivider(),
              _summaryRow(
                'Plate number',
                UpgradeScreen.requestedPlate,
                icon: Icons.pin_outlined,
              ),
              _summaryDivider(),
              _summaryRow(
                'OR/CR files',
                '${UpgradeScreen.requestedOrCrCount} uploaded',
                icon: Icons.description_rounded,
              ),
              _summaryDivider(),
              _summaryRow(
                'Vehicle photos',
                '${UpgradeScreen.requestedPhotoCount} uploaded',
                icon: Icons.photo_camera_rounded,
              ),
              _summaryDivider(),
              _summaryRow(
                "Driver's license",
                UpgradeScreen.requestedHasLicense ? 'Uploaded' : 'Not provided',
                icon: Icons.badge_rounded,
              ),
              if (UpgradeScreen.requestedNotes.isNotEmpty) ...[
                _summaryDivider(),
                _summaryRow(
                  'Notes',
                  UpgradeScreen.requestedNotes,
                  icon: Icons.notes_rounded,
                ),
              ],
            ],
          ),
        ),

        const SizedBox(height: 20),

        SizedBox(
          width: double.infinity,
          child: OutlinedButton.icon(
            onPressed: _confirmCancelRequest,
            icon: const Icon(Icons.close_rounded, size: 18),
            label: const Text(
              'Cancel Request',
              style: TextStyle(fontWeight: FontWeight.w700),
            ),
            style: OutlinedButton.styleFrom(
              foregroundColor: danger,
              side: BorderSide(color: danger.withValues(alpha: 0.4)),
              padding: const EdgeInsets.symmetric(vertical: 15),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(radiusMd),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _summaryRow(String label, String value, {required IconData icon}) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: darkMaroon.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(11),
          ),
          child: Icon(icon, color: darkMaroon, size: 18),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(
                  color: textMuted,
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                value.isEmpty ? '-' : value,
                style: const TextStyle(
                  color: darkMaroon,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _summaryDivider() {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Divider(height: 1, color: darkMaroon.withValues(alpha: 0.07)),
    );
  }

  // ============================================================
  // FORM VIEW
  // ============================================================

  Widget _buildFormView() {
    return Form(
      key: _formKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // INTRO CARD
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: maroon.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(radiusLg),
              border: Border.all(color: maroon.withValues(alpha: 0.22)),
            ),
            child: Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: maroon,
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: const Icon(
                    Icons.workspace_premium_rounded,
                    color: Colors.white,
                    size: 20,
                  ),
                ),
                const SizedBox(width: 14),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Upgrade your vehicle',
                        style: TextStyle(
                          fontSize: 15.5,
                          fontWeight: FontWeight.w800,
                          color: darkMaroon,
                        ),
                      ),
                      SizedBox(height: 3),
                      Text(
                        'Fill out the form and upload your documents. The admin will review your request.',
                        style: TextStyle(
                          fontSize: 12,
                          color: textMuted,
                          height: 1.35,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 24),

          // VEHICLE SECTION
          _sectionLabel('Vehicle'),
          const SizedBox(height: 12),

          Container(
            padding: const EdgeInsets.all(16),
            decoration: cardDecoration,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _fieldLabel('Current vehicle'),
                const SizedBox(height: 8),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 14,
                  ),
                  decoration: BoxDecoration(
                    color: cream,
                    borderRadius: BorderRadius.circular(radiusSm),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        _vehicleIcon(_currentVehicle),
                        color: darkMaroon,
                        size: 19,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          RiderProfile.plateNumber.trim().isEmpty
                              ? _currentVehicle
                              : '$_currentVehicle • ${RiderProfile.plateNumber.trim()}',
                          style: const TextStyle(
                            color: darkMaroon,
                            fontWeight: FontWeight.w700,
                            fontSize: 13.5,
                          ),
                        ),
                      ),
                      const Icon(
                        Icons.lock_outline_rounded,
                        color: textMuted,
                        size: 16,
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 16),

                _fieldLabel('Upgrade to *'),
                const SizedBox(height: 8),
                DropdownButtonFormField<String>(
                  initialValue: _selectedVehicle,
                  isExpanded: true,
                  icon: const Icon(
                    Icons.keyboard_arrow_down_rounded,
                    color: darkMaroon,
                  ),
                  dropdownColor: surface,
                  borderRadius: BorderRadius.circular(radiusSm),
                  decoration: _inputDecoration(
                    hint: 'Select new vehicle type',
                    icon: Icons.swap_horiz_rounded,
                  ),
                  items: _upgradeOptions
                      .map(
                        (v) => DropdownMenuItem<String>(
                          value: v,
                          child: Text(
                            v,
                            style: const TextStyle(
                              color: darkMaroon,
                              fontWeight: FontWeight.w600,
                              fontSize: 14,
                            ),
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: (value) {
                    setState(() => _selectedVehicle = value);
                  },
                  validator: (value) =>
                      value == null ? 'Please select a vehicle type' : null,
                ),

                const SizedBox(height: 16),

                _fieldLabel('Plate number *'),
                const SizedBox(height: 8),
                TextFormField(
                  controller: _plateController,
                  textCapitalization: TextCapitalization.characters,
                  textInputAction: TextInputAction.done,
                  style: _inputTextStyle,
                  decoration: _inputDecoration(
                    hint: 'e.g. ABC 1234',
                    icon: Icons.pin_outlined,
                  ),
                  validator: (value) {
                    final String v = (value ?? '').trim();
                    if (v.isEmpty) return 'Plate number is required';
                    if (v.length < 5) return 'Enter a valid plate number';
                    return null;
                  },
                ),
              ],
            ),
          ),

          const SizedBox(height: 26),

          // DOCUMENTS SECTION
          _sectionLabel('Documents'),
          const SizedBox(height: 4),
          const Text(
            'Upload clear files. Make sure all details are readable.',
            style: TextStyle(fontSize: 12, color: textMuted),
          ),
          const SizedBox(height: 12),

          _buildOrCrCard(),
          _buildVehiclePhotosCard(),
          _buildLicenseCard(),

          const SizedBox(height: 16),

          // NOTES SECTION
          _sectionLabel('Notes'),
          const SizedBox(height: 12),

          Container(
            padding: const EdgeInsets.all(16),
            decoration: cardDecoration,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _fieldLabel('Reason for upgrade (optional)'),
                const SizedBox(height: 8),
                TextFormField(
                  controller: _notesController,
                  maxLines: 4,
                  maxLength: 250,
                  textCapitalization: TextCapitalization.sentences,
                  style: _inputTextStyle,
                  decoration: _inputDecoration(
                    hint: 'Tell the admin why you want to upgrade...',
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 22),

          // SUBMIT BUTTON
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _isSubmitting ? null : _submit,
              style: ElevatedButton.styleFrom(
                backgroundColor: darkMaroon,
                foregroundColor: Colors.white,
                disabledBackgroundColor: darkMaroon.withValues(alpha: 0.6),
                disabledForegroundColor: Colors.white70,
                elevation: 0,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(radiusMd),
                ),
              ),
              child: _isSubmitting
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.2,
                        color: Colors.white,
                      ),
                    )
                  : const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.send_rounded, size: 18),
                        SizedBox(width: 8),
                        Text(
                          'Submit Upgrade Request',
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 14.5,
                          ),
                        ),
                      ],
                    ),
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // DOCUMENT CARD SHELL
  // ============================================================

  Widget _docCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required bool hasError,
    required String errorText,
    required Widget child,
    String? badge,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: surface,
          borderRadius: BorderRadius.circular(radiusLg),
          border: Border.all(
            color: hasError
                ? danger.withValues(alpha: 0.6)
                : darkMaroon.withValues(alpha: 0.06),
            width: hasError ? 1.4 : 1,
          ),
          boxShadow: shadowSoft,
        ),
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
                          color: textMuted,
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
            if (hasError) ...[
              const SizedBox(height: 8),
              Text(
                errorText,
                style: const TextStyle(
                  color: danger,
                  fontSize: 11.5,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _addButton({
    required String label,
    required IconData icon,
    required VoidCallback onTap,
  }) {
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: onTap,
        icon: Icon(icon, size: 18),
        label: Text(label, style: const TextStyle(fontWeight: FontWeight.w700)),
        style: OutlinedButton.styleFrom(
          foregroundColor: darkMaroon,
          side: BorderSide(color: darkMaroon.withValues(alpha: 0.25)),
          padding: const EdgeInsets.symmetric(vertical: 13),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(radiusSm),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // OR/CR CARD — PDF / Word, multiple
  // ============================================================

  Widget _buildOrCrCard() {
    final bool hasError =
        _showDocumentErrors && _orCrFiles.length < _minOrCrFiles;

    return _docCard(
      title: 'OR/CR *',
      subtitle: 'Upload both the Official Receipt (OR) and Certificate of Registration (CR). PDF or Word only.',
      icon: Icons.description_rounded,
      badge: '${_orCrFiles.length}/$_maxOrCrFiles',
      hasError: hasError,
      errorText: _minOrCrFiles > 1
          ? 'Please upload at least $_minOrCrFiles files (OR and CR).'
          : 'Please upload your OR/CR.',
      child: Column(
        children: [
          for (int i = 0; i < _orCrFiles.length; i++) _orCrFileRow(i),
          if (_orCrFiles.length < _maxOrCrFiles)
            _addButton(
              label: _orCrFiles.isEmpty ? 'Upload files' : 'Add more files',
              icon: Icons.upload_file_rounded,
              onTap: _pickOrCrFiles,
            ),
        ],
      ),
    );
  }

  Widget _orCrFileRow(int index) {
    final File file = _orCrFiles[index];
    final String ext = _fileExt(file.path);
    final bool isPdf = ext == 'pdf';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.fromLTRB(12, 10, 6, 10),
      decoration: BoxDecoration(
        color: cream,
        borderRadius: BorderRadius.circular(radiusSm),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: (isPdf ? danger : const Color(0xFF1E5AA8)).withValues(
                alpha: 0.12,
              ),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              isPdf ? Icons.picture_as_pdf_rounded : Icons.description_rounded,
              color: isPdf ? danger : const Color(0xFF1E5AA8),
              size: 19,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _fileName(file),
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
                  '${ext.toUpperCase()} • ${_fileSize(file)}',
                  style: const TextStyle(color: textMuted, fontSize: 10.5),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: () => setState(() => _orCrFiles.removeAt(index)),
            icon: const Icon(Icons.close_rounded, color: danger, size: 20),
            tooltip: 'Remove',
          ),
        ],
      ),
    );
  }

  // ============================================================
  // VEHICLE PHOTOS CARD — camera / gallery, multiple
  // ============================================================

  Widget _buildVehiclePhotosCard() {
    final bool hasError = _showDocumentErrors && _vehiclePhotos.isEmpty;

    return _docCard(
      title: 'Vehicle Photos *',
      subtitle: 'Take or upload photos showing the whole vehicle and its plate. You can add multiple.',
      icon: Icons.photo_camera_rounded,
      badge: '${_vehiclePhotos.length}/$_maxVehiclePhotos',
      hasError: hasError,
      errorText: 'Please add at least one vehicle photo.',
      child: Wrap(
        spacing: 10,
        runSpacing: 10,
        children: [
          for (int i = 0; i < _vehiclePhotos.length; i++) _photoThumb(i),
          if (_vehiclePhotos.length < _maxVehiclePhotos) _addPhotoTile(),
        ],
      ),
    );
  }

  Widget _photoThumb(int index) {
    return SizedBox(
      width: 88,
      height: 88,
      child: Stack(
        children: [
          Positioned.fill(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(radiusSm),
              child: Image.file(_vehiclePhotos[index], fit: BoxFit.cover),
            ),
          ),
          Positioned(
            top: 4,
            right: 4,
            child: GestureDetector(
              onTap: () => setState(() => _vehiclePhotos.removeAt(index)),
              child: Container(
                width: 24,
                height: 24,
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.6),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.close_rounded,
                  color: Colors.white,
                  size: 15,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _addPhotoTile() {
    return GestureDetector(
      onTap: _addVehiclePhotos,
      child: Container(
        width: 88,
        height: 88,
        decoration: BoxDecoration(
          color: cream,
          borderRadius: BorderRadius.circular(radiusSm),
          border: Border.all(color: darkMaroon.withValues(alpha: 0.2)),
        ),
        child: const Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.add_a_photo_rounded, color: darkMaroon, size: 22),
            SizedBox(height: 4),
            Text(
              'Add',
              style: TextStyle(
                color: darkMaroon,
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // DRIVER'S LICENSE CARD — JPG / PNG only, optional
  // ============================================================

  Widget _buildLicenseCard() {
    return _docCard(
      title: "Driver's License (optional)",
      subtitle: 'Needed if the new vehicle requires a different restriction code. JPG or PNG only.',
      icon: Icons.badge_rounded,
      hasError: false,
      errorText: '',
      child: _licenseImage == null
          ? _addButton(
              label: 'Upload license',
              icon: Icons.upload_file_rounded,
              onTap: _pickLicense,
            )
          : Container(
              padding: const EdgeInsets.fromLTRB(10, 10, 6, 10),
              decoration: BoxDecoration(
                color: cream,
                borderRadius: BorderRadius.circular(radiusSm),
              ),
              child: Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: SizedBox(
                      width: 56,
                      height: 56,
                      child: Image.file(_licenseImage!, fit: BoxFit.cover),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _fileName(_licenseImage!),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: darkMaroon,
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Row(
                          children: [
                            const Icon(
                              Icons.check_circle_rounded,
                              color: success,
                              size: 13,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              '${_fileExt(_licenseImage!.path).toUpperCase()} • ${_fileSize(_licenseImage!)}',
                              style: const TextStyle(
                                color: textMuted,
                                fontSize: 10.5,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: _pickLicense,
                    icon: const Icon(
                      Icons.edit_rounded,
                      color: darkMaroon,
                      size: 19,
                    ),
                    tooltip: 'Replace',
                  ),
                ],
              ),
            ),
    );
  }

  // ============================================================
  // SHARED WIDGET HELPERS
  // ============================================================

  Widget _sectionLabel(String label) {
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

  TextStyle get _inputTextStyle => const TextStyle(
    color: darkMaroon,
    fontWeight: FontWeight.w600,
    fontSize: 14,
  );

  InputDecoration _inputDecoration({required String hint, IconData? icon}) {
    OutlineInputBorder border(Color color, [double width = 1]) =>
        OutlineInputBorder(
          borderRadius: BorderRadius.circular(radiusSm),
          borderSide: BorderSide(color: color, width: width),
        );

    return InputDecoration(
      hintText: hint,
      hintStyle: TextStyle(
        color: textMuted.withValues(alpha: 0.7),
        fontSize: 13.5,
        fontWeight: FontWeight.w500,
      ),
      prefixIcon: icon == null ? null : Icon(icon, color: textMuted, size: 19),
      filled: true,
      fillColor: cream,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      errorStyle: const TextStyle(fontSize: 11, color: danger),
      enabledBorder: border(Colors.transparent),
      focusedBorder: border(darkMaroon, 1.4),
      errorBorder: border(danger.withValues(alpha: 0.6)),
      focusedErrorBorder: border(danger, 1.4),
    );
  }
}

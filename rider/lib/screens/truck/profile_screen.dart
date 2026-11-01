import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_application_1/models/truck_profile_store.dart';
import 'package:flutter_application_1/models/truck_rider_profile.dart';
import 'package:flutter_application_1/screens/login_screen.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'change_password_screen.dart';
import 'personal_information_screen.dart';
import 'truck_vehicle_information_screen.dart';

/// Truck rider Profile tab.
/// Personal Information and Truck Information are separate screens.
/// Documents are managed inside Truck Information.
class ProfileScreen extends StatefulWidget {
  final TruckRiderProfile profile;
  final ValueChanged<TruckRiderProfile>? onProfileChanged;

  const ProfileScreen({
    super.key,
    required this.profile,
    this.onProfileChanged,
  });

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);

  String? _imagePath;
  bool _isPicking = false;
  DateTime? _deletionRequestedAt;
  bool _deletionLoading = true;

  String get _prefsKey => 'truck_profile_image_${widget.profile.riderId}';
  String get _deletionKey =>
      'truck_account_deletion_requested_at_${widget.profile.riderId}';

  DateTime? get _deletionDeadline {
    final requestedAt = _deletionRequestedAt;
    return requestedAt == null
        ? null
        : requestedAt.add(const Duration(days: 15));
  }

  int get _deletionDaysRemaining {
    final deadline = _deletionDeadline;
    if (deadline == null) return 15;
    final remaining = deadline.difference(DateTime.now());
    if (remaining.isNegative) return 0;
    return remaining.inDays + (remaining.inHours % 24 > 0 ? 1 : 0);
  }

  bool get _deletionGracePeriodActive {
    final deadline = _deletionDeadline;
    return _deletionRequestedAt != null &&
        deadline != null &&
        DateTime.now().isBefore(deadline);
  }

  @override
  void initState() {
    super.initState();
    _loadSavedImage();
    _loadDeletionRequest();
  }

  @override
  void dispose() {
    super.dispose();
  }

  Future<void> _loadSavedImage() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final path = prefs.getString(_prefsKey);
      if (path != null && await File(path).exists() && mounted) {
        setState(() => _imagePath = path);
      }
    } catch (_) {
      // Keep the default avatar if the saved image cannot be loaded.
    }
  }

  void _showMessage(String message, {bool success = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: darkMaroon,
          content: Row(
            children: [
              Icon(
                success ? Icons.check_circle_outline : Icons.info_outline,
                color: Colors.white,
              ),
              const SizedBox(width: 10),
              Expanded(child: Text(message)),
            ],
          ),
        ),
      );
  }

  Future<void> _showPhotoOptions() async {
    if (_isPicking) return;

    final choice = await showModalBottomSheet<String>(
      context: context,
      backgroundColor: cream,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (sheetContext) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Profile Photo',
                style: TextStyle(
                  color: darkMaroon,
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 8),
              _sheetOption(
                sheetContext,
                icon: Icons.photo_camera_outlined,
                label: 'Take a photo',
                value: 'camera',
              ),
              _sheetOption(
                sheetContext,
                icon: Icons.photo_library_outlined,
                label: 'Choose from gallery',
                value: 'gallery',
              ),
              if (_imagePath != null)
                _sheetOption(
                  sheetContext,
                  icon: Icons.delete_outline,
                  label: 'Remove photo',
                  value: 'remove',
                ),
            ],
          ),
        ),
      ),
    );

    switch (choice) {
      case 'camera':
        await _pickImage(ImageSource.camera);
        break;
      case 'gallery':
        await _pickImage(ImageSource.gallery);
        break;
      case 'remove':
        await _removeImage();
        break;
    }
  }

  Widget _sheetOption(
    BuildContext sheetContext, {
    required IconData icon,
    required String label,
    required String value,
  }) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: lightBeige.withValues(alpha: 0.55),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Icon(icon, color: darkMaroon),
      ),
      title: Text(
        label,
        style: const TextStyle(
          color: darkMaroon,
          fontWeight: FontWeight.w600,
          fontSize: 14,
        ),
      ),
      onTap: () => Navigator.pop(sheetContext, value),
    );
  }

  Future<void> _pickImage(ImageSource source) async {
    if (_isPicking) return;
    setState(() => _isPicking = true);

    try {
      final picked = await ImagePicker().pickImage(
        source: source,
        maxWidth: 1024,
        maxHeight: 1024,
        imageQuality: 85,
      );
      if (picked == null) return;

      final dir = await getApplicationDocumentsDirectory();
      final folder = Directory(p.join(dir.path, 'profile_pictures'));
      if (!await folder.exists()) {
        await folder.create(recursive: true);
      }

      var extension = p.extension(picked.path);
      if (extension.isEmpty) extension = '.jpg';

      final destPath = p.join(
        folder.path,
        '${widget.profile.riderId}_${DateTime.now().millisecondsSinceEpoch}'
        '$extension',
      );
      await File(picked.path).copy(destPath);

      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_prefsKey, destPath);

      final oldPath = _imagePath;
      if (!mounted) return;
      setState(() => _imagePath = destPath);

      if (oldPath != null && oldPath != destPath) {
        try {
          final oldFile = File(oldPath);
          if (await oldFile.exists()) await oldFile.delete();
        } catch (_) {}
      }
      _showMessage('Profile photo updated.', success: true);
    } catch (_) {
      _showMessage('Unable to update your photo. Please try again.');
    } finally {
      if (mounted) setState(() => _isPicking = false);
    }
  }

  Future<void> _removeImage() async {
    final oldPath = _imagePath;
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_prefsKey);
      if (oldPath != null) {
        final file = File(oldPath);
        if (await file.exists()) await file.delete();
      }
      if (!mounted) return;
      setState(() => _imagePath = null);
      _showMessage('Profile photo removed.', success: true);
    } catch (_) {
      _showMessage('Unable to remove your photo. Please try again.');
    }
  }

  Future<void> _loadDeletionRequest() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final savedDate = prefs.getString(_deletionKey);
      final parsedDate = savedDate == null
          ? null
          : DateTime.tryParse(savedDate);
      if (!mounted) return;
      setState(() {
        _deletionRequestedAt = parsedDate;
        _deletionLoading = false;
      });
    } catch (_) {
      if (mounted) setState(() => _deletionLoading = false);
    }
  }

  Future<void> _requestAccountDeletion() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: cream,
        title: const Text(
          'Request account deletion?',
          style: TextStyle(color: darkMaroon, fontWeight: FontWeight.bold),
        ),
        content: const Text(
          'Your account will enter a 15-day reconsideration period. You can '
          'cancel the request during those 15 days. This demo saves the request '
          'on this device and does not delete your server account.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Keep account'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            style: FilledButton.styleFrom(
              backgroundColor: Colors.red.shade800,
              foregroundColor: Colors.white,
            ),
            child: const Text('Request deletion'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    try {
      final now = DateTime.now();
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_deletionKey, now.toIso8601String());
      if (!mounted) return;
      setState(() => _deletionRequestedAt = now);
      _showMessage(
        'Deletion request saved. You have 15 days to change your mind.',
        success: true,
      );
    } catch (_) {
      _showMessage('Could not save the deletion request. Please try again.');
    }
  }

  Future<void> _cancelAccountDeletion() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: cream,
        title: const Text(
          'Cancel deletion request?',
          style: TextStyle(color: darkMaroon, fontWeight: FontWeight.bold),
        ),
        content: const Text(
          'Your account deletion request will be cancelled and your account '
          'will remain active.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Go back'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            style: FilledButton.styleFrom(
              backgroundColor: darkMaroon,
              foregroundColor: Colors.white,
            ),
            child: const Text('Cancel request'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_deletionKey);
      if (!mounted) return;
      setState(() => _deletionRequestedAt = null);
      _showMessage(
        'Deletion request cancelled. Your account remains active.',
        success: true,
      );
    } catch (_) {
      _showMessage('Could not cancel the request. Please try again.');
    }
  }

  Widget _buildAccountDeletionSection() {
    if (_deletionLoading) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 12),
        child: Center(
          child: SizedBox(
            width: 22,
            height: 22,
            child: CircularProgressIndicator(strokeWidth: 2, color: darkMaroon),
          ),
        ),
      );
    }

    if (_deletionRequestedAt != null) {
      final deadline = _deletionDeadline!;
      final deadlineText =
          '${deadline.day.toString().padLeft(2, '0')}/'
          '${deadline.month.toString().padLeft(2, '0')}/'
          '${deadline.year}';

      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: _deletionGracePeriodActive
                ? Colors.orange.shade300
                : Colors.red.shade300,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  _deletionGracePeriodActive
                      ? Icons.hourglass_top_rounded
                      : Icons.info_outline_rounded,
                  color: _deletionGracePeriodActive
                      ? Colors.orange.shade800
                      : Colors.red.shade800,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    _deletionGracePeriodActive
                        ? 'Account deletion requested'
                        : '15-day grace period ended',
                    style: const TextStyle(
                      color: darkMaroon,
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              _deletionGracePeriodActive
                  ? 'You have $_deletionDaysRemaining day(s) left to change '
                        'your mind. Scheduled deletion date: $deadlineText.'
                  : 'The reconsideration period ended on $deadlineText. '
                        'Final deletion must be completed by the Lumear backend.',
              style: const TextStyle(
                color: brownBeige,
                fontSize: 12,
                height: 1.5,
              ),
            ),
            if (_deletionGracePeriodActive) ...[
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton.icon(
                  onPressed: _cancelAccountDeletion,
                  icon: const Icon(Icons.undo_rounded),
                  label: const Text('Cancel Deletion Request'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: darkMaroon,
                    side: const BorderSide(color: darkMaroon),
                  ),
                ),
              ),
            ],
          ],
        ),
      );
    }

    return OutlinedButton.icon(
      onPressed: _requestAccountDeletion,
      icon: const Icon(Icons.delete_outline_rounded),
      label: const Text(
        'Delete Account',
        style: TextStyle(fontWeight: FontWeight.bold),
      ),
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(52),
        foregroundColor: Colors.red.shade800,
        side: BorderSide(color: Colors.red.shade300, width: 1.2),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),
    );
  }

  Future<void> _confirmLogout(BuildContext context) async {
    final shouldLogout = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: cream,
        title: const Text(
          'Log out?',
          style: TextStyle(color: darkMaroon, fontWeight: FontWeight.bold),
        ),
        content: const Text('You will return to the login screen.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton.icon(
            onPressed: () => Navigator.pop(dialogContext, true),
            icon: const Icon(Icons.logout_rounded, size: 18),
            label: const Text('Log out'),
            style: FilledButton.styleFrom(
              backgroundColor: darkMaroon,
              foregroundColor: Colors.white,
            ),
          ),
        ],
      ),
    );

    if (shouldLogout != true || !context.mounted) return;

    TruckProfileStore.clear();
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final name = widget.profile.fullName.trim();

    return Scaffold(
      backgroundColor: cream,
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(24, 24, 24, 130),
          children: [
            const Text(
              'Profile',
              style: TextStyle(
                color: darkMaroon,
                fontSize: 25,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 20),

            // Profile picture and rider name.
            Center(
              child: Column(
                children: [
                  GestureDetector(
                    onTap: _showPhotoOptions,
                    child: Stack(
                      children: [
                        CircleAvatar(
                          radius: 48,
                          backgroundColor: maroon,
                          backgroundImage: _imagePath != null
                              ? FileImage(File(_imagePath!))
                              : null,
                          child: _imagePath == null
                              ? const Icon(
                                  Icons.person,
                                  color: Colors.white,
                                  size: 50,
                                )
                              : null,
                        ),
                        if (_isPicking)
                          Positioned.fill(
                            child: Container(
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: Colors.black.withValues(alpha: 0.35),
                              ),
                              child: const Center(
                                child: SizedBox(
                                  width: 24,
                                  height: 24,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2.5,
                                    color: Colors.white,
                                  ),
                                ),
                              ),
                            ),
                          ),
                        Positioned(
                          right: 0,
                          bottom: 0,
                          child: Container(
                            width: 32,
                            height: 32,
                            decoration: BoxDecoration(
                              color: darkMaroon,
                              shape: BoxShape.circle,
                              border: Border.all(color: cream, width: 2),
                            ),
                            child: const Icon(
                              Icons.camera_alt_outlined,
                              color: Colors.white,
                              size: 16,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    name.isEmpty ? 'Rider' : name,
                    style: const TextStyle(
                      color: darkMaroon,
                      fontSize: 17,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 4),
                  TextButton(
                    onPressed: _showPhotoOptions,
                    child: const Text(
                      'Change photo',
                      style: TextStyle(
                        color: maroon,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // Personal information.
            _menuButton(
              context,
              icon: Icons.person_outline_rounded,
              title: 'Personal Information',
              subtitle: 'Name, contact details, and address',
              page: TruckPersonalInformationScreen(
                profile: widget.profile,
                onSaved: (updatedProfile) {
                  widget.onProfileChanged?.call(updatedProfile);
                },
              ),
            ),
            const SizedBox(height: 12),

            // Truck information and documents.
            _menuButton(
              context,
              icon: Icons.local_shipping_outlined,
              title: 'Truck Information',
              subtitle: 'Vehicle details, license, and documents',
              page: TruckVehicleInformationScreen(
                profile: widget.profile,
                onSaved: (updatedProfile) {
                  widget.onProfileChanged?.call(updatedProfile);
                },
              ),
            ),
            const SizedBox(height: 12),

            // CHANGE PASSWORD - newly added.
            _menuButton(
              context,
              icon: Icons.lock_reset_rounded,
              title: 'Change Password',
              subtitle: 'Update your account password',
              page: const ChangePasswordScreen(),
            ),

            const SizedBox(height: 24),
            const Text(
              'ACCOUNT DELETION',
              style: TextStyle(
                color: brownBeige,
                fontSize: 12,
                fontWeight: FontWeight.bold,
                letterSpacing: 1.1,
              ),
            ),
            const SizedBox(height: 10),
            _buildAccountDeletionSection(),
            const SizedBox(height: 24),

            // Log out.
            SizedBox(
              height: 52,
              child: OutlinedButton.icon(
                onPressed: () => _confirmLogout(context),
                icon: const Icon(Icons.logout_rounded),
                label: const Text(
                  'Log Out',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
                style: OutlinedButton.styleFrom(
                  foregroundColor: darkMaroon,
                  side: const BorderSide(color: darkMaroon, width: 1.3),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _menuButton(
    BuildContext context, {
    required IconData icon,
    required String title,
    required String subtitle,
    required Widget page,
  }) {
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: () {
        Navigator.push(context, MaterialPageRoute(builder: (_) => page));
      },
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.55),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: lightBeige),
        ),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: maroon,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: Colors.white, size: 22),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: darkMaroon,
                      fontSize: 14,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    subtitle,
                    style: const TextStyle(color: brownBeige, fontSize: 11),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: maroon),
          ],
        ),
      ),
    );
  }
}

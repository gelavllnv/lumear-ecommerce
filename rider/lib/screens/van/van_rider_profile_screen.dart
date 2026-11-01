import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../login_screen.dart';
import 'van_personal_information_screen.dart';
import 'van_rider_documents_screen.dart';
import 'van_vehicle_information_screen.dart';
import 'van_rider_delivery_reports_screen.dart';

const Color vanDarkMaroon = Color(0xFF62191C);
const Color vanMaroon = Color(0xFF873632);
const Color vanBrownBeige = Color(0xFF9E7161);
const Color vanLightBeige = Color(0xFFCAAE9F);
const Color vanCream = Color(0xFFF3E9E1);

class VanRiderProfileScreen extends StatefulWidget {
  final int totalParcels;
  final VoidCallback? onLogout;

  /// Saved photo path (e.g. loaded from storage or your API), if any.
  final String? photoPath;

  /// Called whenever the rider picks a new photo or removes it
  /// (null means removed). Use this to save the photo permanently.
  final ValueChanged<String?>? onPhotoChanged;

  /// If provided, the parent should load this from the backend on screen open.
  final DateTime? deletionScheduledFor;

  /// Connect these callbacks to your backend API to persist the request.
  final ValueChanged<DateTime>? onDeleteAccountRequested;
  final VoidCallback? onCancelAccountDeletion;

  /// Connect to the authenticated backend password-change endpoint.
  /// Return true when the backend successfully changes the password.
  final Future<bool> Function(String currentPassword, String newPassword)?
  onChangePassword;

  const VanRiderProfileScreen({
    super.key,
    this.totalParcels = 0,
    this.onLogout,
    this.photoPath,
    this.onPhotoChanged,
    this.deletionScheduledFor,
    this.onDeleteAccountRequested,
    this.onCancelAccountDeletion,
    this.onChangePassword,
  });

  @override
  State<VanRiderProfileScreen> createState() => _VanRiderProfileScreenState();
}

class _VanRiderProfileScreenState extends State<VanRiderProfileScreen> {
  final ImagePicker _imagePicker = ImagePicker();

  String? _photoPath;
  bool _photoLoading = false;
  DateTime? _deletionScheduledFor;

  @override
  void initState() {
    super.initState();
    _photoPath = widget.photoPath;
    _deletionScheduledFor = widget.deletionScheduledFor;
  }

  // ---------------------------------------------------------------------------
  // Photo handling
  // ---------------------------------------------------------------------------

  Future<void> _pickPhoto(ImageSource source) async {
    try {
      setState(() => _photoLoading = true);

      final picked = await _imagePicker.pickImage(
        source: source,
        maxWidth: 1024,
        maxHeight: 1024,
        imageQuality: 85,
      );

      if (!mounted) return;

      if (picked == null) {
        // User cancelled.
        setState(() => _photoLoading = false);
        return;
      }

      setState(() {
        _photoPath = picked.path;
        _photoLoading = false;
      });

      widget.onPhotoChanged?.call(picked.path);
      _showMessage('Profile photo updated.');
    } catch (e) {
      if (!mounted) return;
      setState(() => _photoLoading = false);
      _showMessage(
        source == ImageSource.camera
            ? 'Unable to open the camera. Please check app permissions.'
            : 'Unable to open your gallery. Please check app permissions.',
      );
    }
  }

  void _removePhoto() {
    setState(() => _photoPath = null);
    widget.onPhotoChanged?.call(null);
    _showMessage('Profile photo removed.');
  }

  void _showPhotoOptions() {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 42,
                    height: 4,
                    decoration: BoxDecoration(
                      color: vanLightBeige,
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Profile Photo',
                  style: TextStyle(
                    color: vanDarkMaroon,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Choose a clear photo of yourself.',
                  style: TextStyle(color: vanBrownBeige, fontSize: 12),
                ),
                const SizedBox(height: 12),
                _sheetOption(
                  icon: Icons.photo_camera_outlined,
                  label: 'Take a photo',
                  onTap: () {
                    Navigator.pop(sheetContext);
                    _pickPhoto(ImageSource.camera);
                  },
                ),
                _sheetOption(
                  icon: Icons.photo_library_outlined,
                  label: 'Choose from gallery',
                  onTap: () {
                    Navigator.pop(sheetContext);
                    _pickPhoto(ImageSource.gallery);
                  },
                ),
                if (_photoPath != null)
                  _sheetOption(
                    icon: Icons.delete_outline,
                    label: 'Remove photo',
                    color: Colors.red.shade700,
                    onTap: () {
                      Navigator.pop(sheetContext);
                      _removePhoto();
                    },
                  ),
              ],
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
    Color color = vanDarkMaroon,
  }) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      onTap: onTap,
      leading: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: vanCream,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Icon(icon, color: color),
      ),
      title: Text(
        label,
        style: TextStyle(color: color, fontWeight: FontWeight.w600),
      ),
    );
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: vanDarkMaroon,
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  // ---------------------------------------------------------------------------
  // Navigation
  // ---------------------------------------------------------------------------

  void _openScreen(BuildContext context, Widget screen) {
    Navigator.push(context, MaterialPageRoute(builder: (_) => screen));
  }

  Future<void> _confirmLogout() async {
    final shouldLogout = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: vanCream,
        title: const Text(
          'Log out?',
          style: TextStyle(color: vanDarkMaroon, fontWeight: FontWeight.bold),
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
              backgroundColor: vanDarkMaroon,
              foregroundColor: Colors.white,
            ),
          ),
        ],
      ),
    );

    if (shouldLogout != true || !mounted) return;

    widget.onLogout?.call();
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  Future<void> _openChangePassword() async {
    await Navigator.push<void>(
      context,
      MaterialPageRoute<void>(
        builder: (_) => VanRiderChangePasswordScreen(
          onChangePassword: widget.onChangePassword,
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // Account deletion (15-day grace period)
  // ---------------------------------------------------------------------------

  Future<void> _requestAccountDeletion() async {
    if (_deletionScheduledFor != null) {
      _showMessage('An account deletion request is already pending.');
      return;
    }

    final confirm = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: vanCream,
        title: const Text(
          'Request account deletion?',
          style: TextStyle(color: vanDarkMaroon, fontWeight: FontWeight.bold),
        ),
        content: const Text(
          'Your account will be scheduled for deletion after 15 days. '
          'During this period, you can change your mind and cancel the request. '
          'If you have active trips, pending payouts, or unresolved deliveries, '
          'contact Logistics before requesting deletion.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Keep Account'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            style: FilledButton.styleFrom(
              backgroundColor: Colors.red.shade700,
              foregroundColor: Colors.white,
            ),
            child: const Text('Continue'),
          ),
        ],
      ),
    );

    if (confirm != true || !mounted) return;

    final scheduledFor = DateTime.now().add(const Duration(days: 15));
    try {
      // The parent can call the backend here and persist the scheduled date.
      widget.onDeleteAccountRequested?.call(scheduledFor);
      setState(() => _deletionScheduledFor = scheduledFor);
      _showMessage('Deletion request scheduled. You have 15 days to cancel.');
    } catch (_) {
      _showMessage('Could not submit the deletion request. Please try again.');
    }
  }

  Future<void> _cancelAccountDeletion() async {
    if (_deletionScheduledFor == null) return;

    final confirm = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: vanCream,
        title: const Text(
          'Cancel deletion request?',
          style: TextStyle(color: vanDarkMaroon, fontWeight: FontWeight.bold),
        ),
        content: const Text(
          'Your account will remain active and the scheduled deletion request '
          'will be cancelled.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Go Back'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            style: FilledButton.styleFrom(
              backgroundColor: vanDarkMaroon,
              foregroundColor: Colors.white,
            ),
            child: const Text('Cancel Deletion'),
          ),
        ],
      ),
    );

    if (confirm != true || !mounted) return;

    try {
      widget.onCancelAccountDeletion?.call();
      setState(() => _deletionScheduledFor = null);
      _showMessage('Account deletion request cancelled.');
    } catch (_) {
      _showMessage('Could not cancel the request. Please try again.');
    }
  }

  Widget _buildDeletionStatusCard() {
    final scheduledFor = _deletionScheduledFor;
    if (scheduledFor == null) return const SizedBox.shrink();

    final remaining = scheduledFor.difference(DateTime.now());
    final daysLeft = remaining.isNegative
        ? 0
        : (remaining.inSeconds / Duration.secondsPerDay).ceil();
    final dateText =
        '${scheduledFor.day.toString().padLeft(2, '0')}/'
        '${scheduledFor.month.toString().padLeft(2, '0')}/'
        '${scheduledFor.year}';

    return Card(
      color: const Color(0xFFFFF4E5),
      elevation: 0,
      margin: const EdgeInsets.only(top: 12, bottom: 4),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(color: Colors.orange.shade200),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.schedule_rounded, color: Colors.orange.shade900),
                const SizedBox(width: 10),
                const Expanded(
                  child: Text(
                    'Account deletion is pending',
                    style: TextStyle(
                      color: vanDarkMaroon,
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              '$daysLeft day(s) remaining. Scheduled date: $dateText.',
              style: const TextStyle(color: Colors.black87, height: 1.4),
            ),
            const SizedBox(height: 6),
            const Text(
              'Your account is not deleted yet. Cancel the request within '
              'the 15-day grace period if you want to keep your account.',
              style: TextStyle(
                color: Colors.black54,
                fontSize: 12,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: _cancelAccountDeletion,
                style: OutlinedButton.styleFrom(
                  foregroundColor: vanDarkMaroon,
                  side: const BorderSide(color: vanDarkMaroon),
                ),
                child: const Text('Cancel Deletion Request'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // UI
  // ---------------------------------------------------------------------------

  Widget _buildAvatar() {
    const double size = 78;
    final path = _photoPath;

    const Widget fallback = Icon(Icons.person, size: 43, color: vanDarkMaroon);

    return GestureDetector(
      onTap: _photoLoading ? null : _showPhotoOptions,
      child: SizedBox(
        width: size + 6,
        height: size + 6,
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Container(
              width: size,
              height: size,
              margin: const EdgeInsets.all(3),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: vanLightBeige,
                border: Border.all(color: Colors.white, width: 3),
              ),
              child: ClipOval(
                child: _photoLoading
                    ? const Center(
                        child: SizedBox(
                          width: 24,
                          height: 24,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.5,
                            color: vanDarkMaroon,
                          ),
                        ),
                      )
                    : path == null
                    ? const Center(child: fallback)
                    : Image.file(
                        File(path),
                        width: size,
                        height: size,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) =>
                            const Center(child: fallback),
                      ),
              ),
            ),
            Positioned(
              right: 0,
              bottom: 0,
              child: Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(
                  color: vanMaroon,
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: 2),
                ),
                child: const Icon(
                  Icons.camera_alt_rounded,
                  size: 14,
                  color: Colors.white,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildProfileHeader() {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [vanDarkMaroon, vanMaroon],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(25),
      ),
      child: Row(
        children: [
          _buildAvatar(),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Van Rider',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 21,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 5),
                const Text(
                  'Lumear Logistics',
                  style: TextStyle(color: vanLightBeige, fontSize: 13),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Van Rider Account',
                  style: TextStyle(color: Colors.white70, fontSize: 12),
                ),
                const SizedBox(height: 8),
                GestureDetector(
                  onTap: _photoLoading ? null : _showPhotoOptions,
                  child: Text(
                    _photoPath == null ? 'Add profile photo' : 'Change photo',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      decoration: TextDecoration.underline,
                      decorationColor: Colors.white,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: vanCream,
      appBar: AppBar(
        automaticallyImplyLeading: false,
        backgroundColor: vanCream,
        foregroundColor: vanDarkMaroon,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'My Profile',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
      ),
      body: ListView(
        // Bottom 130 = room for the floating nav bar, so Log Out and the
        // footer text can scroll fully above it.
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 130),
        children: [
          _buildProfileHeader(),

          const SizedBox(height: 28),

          const Text(
            'ACCOUNT SETTINGS',
            style: TextStyle(
              color: vanBrownBeige,
              fontSize: 12,
              fontWeight: FontWeight.bold,
              letterSpacing: 1.2,
            ),
          ),

          const SizedBox(height: 12),

          _menuItem(
            context,
            icon: Icons.person_outline,
            title: 'Personal Information',
            subtitle: 'Manage your name, contact details, and address',
            onTap: () => _openScreen(
              context,
              VanPersonalInformationScreen(photoPath: _photoPath),
            ),
          ),

          _menuItem(
            context,
            icon: Icons.folder_open_outlined,
            title: 'My Documents',
            subtitle: 'View and update your uploaded OR/CR',
            onTap: () => _openScreen(context, const VanRiderDocumentsScreen()),
          ),

          _menuItem(
            context,
            icon: Icons.local_shipping_outlined,
            title: 'Vehicle Information',
            subtitle: 'View your vehicle details',
            onTap: () =>
                _openScreen(context, const VanVehicleInformationScreen()),
          ),

          _menuItem(
            context,
            icon: Icons.inventory_2_outlined,
            title: 'Reports',
            subtitle: 'View your delivery summary and generate PDF reports',
            onTap: () =>
                _openScreen(context, const VanRiderDeliveryReportsScreen()),
          ),

          _menuItem(
            context,
            icon: Icons.lock_outline_rounded,
            title: 'Change Password',
            subtitle: 'Update your account password securely',
            onTap: _openChangePassword,
          ),

          _buildDeletionStatusCard(),

          const SizedBox(height: 10),

          _menuItem(
            context,
            icon: Icons.delete_forever_outlined,
            title: 'Delete Account',
            subtitle: _deletionScheduledFor == null
                ? 'Request deletion with a 15-day grace period'
                : 'Deletion request pending — cancel it above',
            onTap: _deletionScheduledFor == null
                ? _requestAccountDeletion
                : () => _showMessage(
                    'Your deletion request is pending. You can cancel it above.',
                  ),
          ),

          const SizedBox(height: 24),

          SizedBox(
            height: 52,
            child: OutlinedButton.icon(
              onPressed: _confirmLogout,
              icon: const Icon(Icons.logout),
              label: const Text(
                'Log Out',
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
              style: OutlinedButton.styleFrom(
                foregroundColor: vanDarkMaroon,
                side: const BorderSide(color: vanDarkMaroon, width: 1.3),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
              ),
            ),
          ),

          const SizedBox(height: 24),

          const Center(
            child: Text(
              'Lumear Logistics \u2022 Van Rider',
              style: TextStyle(color: Colors.black45, fontSize: 12),
            ),
          ),
        ],
      ),
    );
  }

  Widget _menuItem(
    BuildContext context, {
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return Card(
      color: Colors.white,
      elevation: 0,
      margin: const EdgeInsets.only(bottom: 11),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(color: vanLightBeige.withValues(alpha: 0.55)),
      ),
      child: ListTile(
        onTap: onTap,
        contentPadding: const EdgeInsets.symmetric(horizontal: 15, vertical: 9),
        leading: Container(
          width: 47,
          height: 47,
          decoration: BoxDecoration(
            color: vanCream,
            borderRadius: BorderRadius.circular(14),
          ),
          child: Icon(icon, color: vanDarkMaroon, size: 25),
        ),
        title: Text(
          title,
          style: const TextStyle(
            color: vanDarkMaroon,
            fontWeight: FontWeight.bold,
            fontSize: 14,
          ),
        ),
        subtitle: Padding(
          padding: const EdgeInsets.only(top: 5),
          child: Text(
            subtitle,
            style: const TextStyle(
              color: Colors.black54,
              fontSize: 11,
              height: 1.4,
            ),
          ),
        ),
        trailing: const Icon(Icons.chevron_right, color: vanBrownBeige),
      ),
    );
  }
}

class VanPickupSummaryScreen extends StatelessWidget {
  final int totalParcels;

  const VanPickupSummaryScreen({super.key, this.totalParcels = 0});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: vanCream,
      appBar: AppBar(
        backgroundColor: vanCream,
        foregroundColor: vanDarkMaroon,
        elevation: 0,
        title: const Text('Pickup Summary'),
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Card(
            color: Colors.white,
            elevation: 0,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(18),
              side: BorderSide(color: vanLightBeige.withValues(alpha: 0.6)),
            ),
            child: Padding(
              padding: const EdgeInsets.all(28),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(
                    Icons.inventory_2_outlined,
                    size: 52,
                    color: vanDarkMaroon,
                  ),
                  const SizedBox(height: 18),
                  const Text(
                    'Delivery Summary',
                    style: TextStyle(
                      color: vanDarkMaroon,
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    '$totalParcels',
                    style: const TextStyle(
                      color: vanMaroon,
                      fontSize: 42,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Password change form for the van rider profile.
/// A backend callback is required to actually update the account password.
class VanRiderChangePasswordScreen extends StatefulWidget {
  final Future<bool> Function(String currentPassword, String newPassword)?
  onChangePassword;

  const VanRiderChangePasswordScreen({super.key, this.onChangePassword});

  @override
  State<VanRiderChangePasswordScreen> createState() =>
      _VanRiderChangePasswordScreenState();
}

class _VanRiderChangePasswordScreenState
    extends State<VanRiderChangePasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _currentController = TextEditingController();
  final _newController = TextEditingController();
  final _confirmController = TextEditingController();

  bool _hideCurrent = true;
  bool _hideNew = true;
  bool _hideConfirm = true;
  bool _saving = false;

  @override
  void dispose() {
    _currentController.dispose();
    _newController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: isError ? Colors.red.shade700 : vanDarkMaroon,
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!_formKey.currentState!.validate()) return;

    final changePassword = widget.onChangePassword;
    if (changePassword == null) {
      _showMessage(
        'Password change is not connected to the backend yet.',
        isError: true,
      );
      return;
    }

    setState(() => _saving = true);
    try {
      final success = await changePassword(
        _currentController.text,
        _newController.text,
      );
      if (!mounted) return;
      setState(() => _saving = false);

      if (success) {
        _currentController.clear();
        _newController.clear();
        _confirmController.clear();
        _showMessage('Password changed successfully.');
        Navigator.pop(context);
      } else {
        _showMessage(
          'Password was not changed. Check your current password and try again.',
          isError: true,
        );
      }
    } catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      _showMessage(
        'Unable to change password. Please try again.',
        isError: true,
      );
    }
  }

  InputDecoration _decoration({
    required String label,
    required String hint,
    required bool obscure,
    required VoidCallback onToggle,
  }) {
    return InputDecoration(
      labelText: label,
      hintText: hint,
      filled: true,
      fillColor: Colors.white,
      prefixIcon: const Icon(Icons.lock_outline_rounded, color: vanBrownBeige),
      suffixIcon: IconButton(
        tooltip: obscure ? 'Show password' : 'Hide password',
        onPressed: onToggle,
        icon: Icon(
          obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined,
          color: vanBrownBeige,
        ),
      ),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: vanLightBeige.withValues(alpha: 0.7)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: vanLightBeige.withValues(alpha: 0.7)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: vanDarkMaroon, width: 1.5),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: vanCream,
      appBar: AppBar(
        backgroundColor: vanCream,
        foregroundColor: vanDarkMaroon,
        elevation: 0,
        title: const Text(
          'Change Password',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: vanLightBeige.withValues(alpha: 0.6)),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.shield_outlined, color: vanDarkMaroon, size: 28),
                  SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'Choose a strong password that you do not use for other accounts.',
                      style: TextStyle(height: 1.45, color: Colors.black87),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 22),
            Form(
              key: _formKey,
              child: Column(
                children: [
                  TextFormField(
                    controller: _currentController,
                    obscureText: _hideCurrent,
                    autofillHints: const [AutofillHints.password],
                    decoration: _decoration(
                      label: 'Current Password',
                      hint: 'Enter your current password',
                      obscure: _hideCurrent,
                      onToggle: () =>
                          setState(() => _hideCurrent = !_hideCurrent),
                    ),
                    validator: (value) => value == null || value.isEmpty
                        ? 'Enter your current password.'
                        : null,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _newController,
                    obscureText: _hideNew,
                    autofillHints: const [AutofillHints.newPassword],
                    decoration: _decoration(
                      label: 'New Password',
                      hint: 'At least 8 characters',
                      obscure: _hideNew,
                      onToggle: () => setState(() => _hideNew = !_hideNew),
                    ),
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Enter a new password.';
                      }
                      if (value.length < 8) {
                        return 'Use at least 8 characters.';
                      }
                      if (value == _currentController.text) {
                        return 'Your new password must be different.';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _confirmController,
                    obscureText: _hideConfirm,
                    decoration: _decoration(
                      label: 'Confirm New Password',
                      hint: 'Re-enter your new password',
                      obscure: _hideConfirm,
                      onToggle: () =>
                          setState(() => _hideConfirm = !_hideConfirm),
                    ),
                    validator: (value) => value != _newController.text
                        ? 'Passwords do not match.'
                        : null,
                  ),
                  const SizedBox(height: 24),
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: FilledButton.icon(
                      onPressed: _saving ? null : _submit,
                      icon: _saving
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Icon(Icons.lock_reset_rounded),
                      label: Text(
                        _saving ? 'Updating...' : 'Update Password',
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                      style: FilledButton.styleFrom(
                        backgroundColor: vanDarkMaroon,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(15),
                        ),
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
}

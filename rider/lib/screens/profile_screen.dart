import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutter_application_1/models/rider_profile.dart';

import 'personal_information_screen.dart';
import 'vehicle_information_screen.dart';
import 'documents_screen.dart';
import 'app_settings_screen.dart';
import 'help_support_screen.dart';
import 'rider_performance_screen.dart';
import 'login_screen.dart';
import 'assignment_screen.dart';
import 'buyer_chat_list_screen.dart';
import 'upgrade_screen.dart';

// ============================================================
// ACCOUNT DELETION (GRACE PERIOD)
// Lipat mo to sa RiderProfile / backend kapag may API na.
// ============================================================

class AccountDeletion {
  static const int graceDays = 15;

  static DateTime? requestedAt;

  static bool get isPending => requestedAt != null;

  static DateTime? get deletionDate =>
      requestedAt?.add(const Duration(days: graceDays));

  static int get daysLeft {
    if (deletionDate == null) return 0;
    final int hours = deletionDate!.difference(DateTime.now()).inHours;
    return (hours / 24).ceil().clamp(0, graceDays).toInt();
  }

  static String formatDate(DateTime d) {
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
    return '${months[d.month - 1]} ${d.day}, ${d.year}';
  }
}

// ============================================================
// PASSWORD CHANGE SERVICE
// Placeholder logic only. Palitan mo ng totoong API call
// (e.g. POST /rider/change-password) kapag may backend na.
// Dapat ang SERVER ang magve-verify ng current password.
// ============================================================

enum PasswordChangeResult { success, wrongCurrentPassword, error }

class PasswordService {
  // TODO: remove this mock. Never store or compare passwords on the device.
  static String _mockCurrentPassword = 'rider123';

  static Future<PasswordChangeResult> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    try {
      // TODO: replace with real API call, e.g.
      // final res = await http.post(
      //   Uri.parse('$baseUrl/rider/change-password'),
      //   headers: {'Authorization': 'Bearer $token'},
      //   body: {
      //     'current_password': currentPassword,
      //     'new_password': newPassword,
      //   },
      // );
      // if (res.statusCode == 200) return PasswordChangeResult.success;
      // if (res.statusCode == 401 || res.statusCode == 403) {
      //   return PasswordChangeResult.wrongCurrentPassword;
      // }
      // return PasswordChangeResult.error;

      await Future.delayed(const Duration(milliseconds: 900));

      if (currentPassword != _mockCurrentPassword) {
        return PasswordChangeResult.wrongCurrentPassword;
      }

      _mockCurrentPassword = newPassword;
      return PasswordChangeResult.success;
    } catch (_) {
      return PasswordChangeResult.error;
    }
  }
}

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
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
  static const Color danger = Color(0xFFB3261E);

  static const double radiusSm = 14;
  static const double radiusMd = 18;
  static const double radiusLg = 22;
  static const double radiusXl = 26;

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

  final ImagePicker _picker = ImagePicker();

  bool _isUpdatingPhoto = false;

  OverlayEntry? _topNotificationEntry;

  @override
  void dispose() {
    _topNotificationEntry?.remove();
    super.dispose();
  }

  // ============================================
  // RIDER DATA HELPERS
  // ============================================

  bool get _isApproved => RiderProfile.status.toLowerCase() == 'approved';

  String get _displayName =>
      RiderProfile.name.isEmpty ? 'Rider' : RiderProfile.name;

  String get _displayRiderId =>
      RiderProfile.riderId.isEmpty ? '-' : RiderProfile.riderId;

  String get _vehicleSubtitle {
    final String vehicle = RiderProfile.vehicleType.trim();
    final String plate = RiderProfile.plateNumber.trim();

    if (vehicle.isEmpty && plate.isEmpty) {
      return 'Vehicle and plate details';
    }
    if (plate.isEmpty) return vehicle;
    if (vehicle.isEmpty) return plate;

    return '$vehicle • $plate';
  }

  IconData get _vehicleMenuIcon {
    switch (RiderProfile.vehicleType.trim().toLowerCase()) {
      case 'van':
        return Icons.airport_shuttle_rounded;
      case 'truck':
        return Icons.local_shipping_rounded;
      case 'tricycle':
        return Icons.electric_rickshaw_outlined;
      default:
        return Icons.two_wheeler_rounded;
    }
  }

  // ============================================
  // UPGRADE
  // ============================================

  Future<void> _openUpgrade() async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => const UpgradeScreen()),
    );

    // Refresh so the button reflects a pending request.
    if (mounted) setState(() {});
  }

  // ============================================
  // BUYER CHAT
  // ============================================

  void _openBuyerChat() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (context) => const BuyerChatListScreen()),
    );
  }

  // ============================================
  // CHANGE PASSWORD
  // ============================================

  Future<void> _showChangePasswordSheet() async {
    final bool? changed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) => const _ChangePasswordSheet(),
    );

    if (changed == true && mounted) {
      _showTopNotification(
        'Password changed successfully.',
        icon: Icons.check_circle_outline,
        duration: const Duration(seconds: 3),
      );
    }
  }

  // ============================================
  // PROFILE PHOTO — OPTIONS SHEET
  // ============================================

  Future<void> _showProfilePhotoOptions() async {
    final String? action = await showModalBottomSheet<String>(
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

                  Row(
                    children: [
                      Container(
                        width: 40,
                        height: 40,
                        decoration: BoxDecoration(
                          color: darkMaroon,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Icon(
                          Icons.photo_camera_rounded,
                          color: Colors.white,
                          size: 19,
                        ),
                      ),
                      const SizedBox(width: 12),
                      const Text(
                        'Update Profile Picture',
                        style: TextStyle(
                          color: darkMaroon,
                          fontSize: 16.5,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.2,
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 16),

                  _sheetOption(
                    icon: Icons.camera_alt_rounded,
                    label: 'Take Photo',
                    onTap: () => Navigator.pop(sheetContext, 'camera'),
                  ),

                  const SizedBox(height: 10),

                  _sheetOption(
                    icon: Icons.photo_library_rounded,
                    label: 'Choose from Gallery',
                    onTap: () => Navigator.pop(sheetContext, 'gallery'),
                  ),

                  if (RiderProfile.profileImage != null) ...[
                    const SizedBox(height: 10),
                    _sheetOption(
                      icon: Icons.delete_outline_rounded,
                      label: 'Remove Photo',
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

    if (action == null) {
      return;
    }

    if (action == 'remove') {
      setState(() {
        RiderProfile.profileImage = null;
      });

      _showTopNotification(
        'Profile picture removed.',
        icon: Icons.check_circle_outline,
      );

      return;
    }

    final ImageSource source = action == 'camera'
        ? ImageSource.camera
        : ImageSource.gallery;

    await _pickProfileImage(source);
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
              Text(
                label,
                style: TextStyle(
                  color: color,
                  fontWeight: FontWeight.w700,
                  fontSize: 14,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================
  // PROFILE PHOTO — PICK
  // ============================================

  Future<void> _pickProfileImage(ImageSource source) async {
    setState(() {
      _isUpdatingPhoto = true;
    });

    try {
      final XFile? pickedImage = await _picker.pickImage(
        source: source,
        imageQuality: 80,
      );

      if (pickedImage == null) {
        if (mounted) {
          setState(() {
            _isUpdatingPhoto = false;
          });
        }

        return;
      }

      if (!mounted) {
        return;
      }

      setState(() {
        RiderProfile.profileImage = File(pickedImage.path);

        _isUpdatingPhoto = false;
      });

      _showTopNotification(
        'Profile picture updated.',
        icon: Icons.check_circle_outline,
      );
    } catch (e) {
      if (!mounted) {
        return;
      }

      setState(() {
        _isUpdatingPhoto = false;
      });

      _showTopNotification('Unable to update profile picture.');
    }
  }

  // ============================================
  // TOP NOTIFICATION
  // ============================================

  void _showTopNotification(
    String message, {
    IconData? icon,
    Duration duration = const Duration(seconds: 2),
  }) {
    _topNotificationEntry?.remove();
    _topNotificationEntry = null;

    final OverlayState overlay = Overlay.of(context);

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
                color: darkMaroon,
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
                      icon ?? Icons.info_outline,
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

    Future.delayed(duration, () {
      if (_topNotificationEntry == entry) {
        entry.remove();

        _topNotificationEntry = null;
      }
    });
  }

  // ============================================
  // LOG OUT
  // ============================================

  void _showLogoutDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          backgroundColor: cream,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(22),
          ),
          titlePadding: const EdgeInsets.fromLTRB(22, 22, 22, 0),
          contentPadding: const EdgeInsets.fromLTRB(22, 14, 22, 10),
          actionsPadding: const EdgeInsets.fromLTRB(22, 0, 22, 18),
          title: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: danger.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.logout_rounded,
                  color: danger,
                  size: 19,
                ),
              ),

              const SizedBox(width: 12),

              const Text(
                'Log Out',
                style: TextStyle(
                  color: darkMaroon,
                  fontWeight: FontWeight.w800,
                  fontSize: 17,
                ),
              ),
            ],
          ),
          content: const Text(
            'Are you sure you want to log out of your rider account?',
            style: TextStyle(color: textMuted, fontSize: 13.5, height: 1.4),
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext);
              },
              style: TextButton.styleFrom(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 12,
                ),
              ),
              child: const Text(
                'Cancel',
                style: TextStyle(color: textMuted, fontWeight: FontWeight.w700),
              ),
            ),

            ElevatedButton(
              onPressed: () {
                Navigator.of(context).pushAndRemoveUntil(
                  MaterialPageRoute(builder: (context) => const LoginScreen()),
                  (route) => false,
                );
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: darkMaroon,
                foregroundColor: Colors.white,
                elevation: 0,
                padding: const EdgeInsets.symmetric(
                  horizontal: 18,
                  vertical: 12,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(radiusSm),
                ),
              ),
              child: const Text(
                'Yes, Log Out',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        );
      },
    );
  }

  // ============================================
  // DELETE ACCOUNT
  // ============================================

  Future<void> _showDeleteAccountDialog() async {
    final bool? confirmed = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          backgroundColor: cream,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(22),
          ),
          titlePadding: const EdgeInsets.fromLTRB(22, 22, 22, 0),
          contentPadding: const EdgeInsets.fromLTRB(22, 14, 22, 10),
          actionsPadding: const EdgeInsets.fromLTRB(22, 0, 22, 18),
          title: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: danger.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.delete_forever_rounded,
                  color: danger,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Text(
                  'Delete Account',
                  style: TextStyle(
                    color: darkMaroon,
                    fontWeight: FontWeight.w800,
                    fontSize: 17,
                  ),
                ),
              ),
            ],
          ),
          content: Text(
            'Your account will be scheduled for deletion in '
            '${AccountDeletion.graceDays} days.\n\n'
            'Changed your mind? You can cancel anytime within this period '
            'and your account will stay as it is. After '
            '${AccountDeletion.graceDays} days, your profile, documents, and '
            'delivery history will be permanently deleted.',
            style: const TextStyle(
              color: textMuted,
              fontSize: 13.5,
              height: 1.45,
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              style: TextButton.styleFrom(
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 12,
                ),
              ),
              child: const Text(
                'Keep Account',
                style: TextStyle(color: textMuted, fontWeight: FontWeight.w700),
              ),
            ),
            ElevatedButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              style: ElevatedButton.styleFrom(
                backgroundColor: danger,
                foregroundColor: Colors.white,
                elevation: 0,
                padding: const EdgeInsets.symmetric(
                  horizontal: 18,
                  vertical: 12,
                ),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(radiusSm),
                ),
              ),
              child: const Text(
                'Delete',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        );
      },
    );

    if (confirmed != true || !mounted) return;

    // TODO: call backend here (e.g. POST /rider/delete-request)
    setState(() {
      AccountDeletion.requestedAt = DateTime.now();
    });

    _showTopNotification(
      'Account scheduled for deletion on '
      '${AccountDeletion.formatDate(AccountDeletion.deletionDate!)}. '
      'You can cancel anytime before then.',
      icon: Icons.schedule_rounded,
      duration: const Duration(seconds: 4),
    );
  }

  void _cancelAccountDeletion() {
    // TODO: call backend here (e.g. POST /rider/cancel-delete-request)
    setState(() {
      AccountDeletion.requestedAt = null;
    });

    _showTopNotification(
      'Account deletion cancelled. Welcome back!',
      icon: Icons.check_circle_outline,
      duration: const Duration(seconds: 3),
    );
  }

  // ============================================
  // BUILD
  // ============================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      body: SafeArea(
        child: SingleChildScrollView(
          // EXTRA SPACE FOR FLOATING NAVBAR
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 125),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ============================================
              // HEADER
              // ============================================

              _EntranceAnimation(
                delay: 30,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Profile',
                      style: TextStyle(
                        fontSize: 27,
                        fontWeight: FontWeight.w800,
                        color: darkMaroon,
                        letterSpacing: -0.4,
                      ),
                    ),

                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        // UPGRADE BUTTON
                        _upgradeButton(),

                        const SizedBox(width: 10),

                        // SETTINGS BUTTON
                        Container(
                          decoration: BoxDecoration(
                            color: surface,
                            borderRadius: BorderRadius.circular(14),
                            boxShadow: shadowSoft,
                          ),
                          child: IconButton(
                            onPressed: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (context) =>
                                      const AppSettingsScreen(),
                                ),
                              );
                            },
                            icon: const Icon(
                              Icons.settings_outlined,
                              color: darkMaroon,
                              size: 22,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 20),

              // ============================================
              // PROFILE CARD
              // ============================================
              _EntranceAnimation(
                delay: 80,
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.fromLTRB(22, 26, 22, 22),
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
                  child: Column(
                    children: [
                      GestureDetector(
                        onTap: _isUpdatingPhoto
                            ? null
                            : _showProfilePhotoOptions,
                        child: Stack(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(3),
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                border: Border.all(
                                  color: Colors.white.withValues(alpha: 0.25),
                                  width: 2,
                                ),
                              ),
                              child: CircleAvatar(
                                radius: 46,
                                backgroundColor: lightBeige,
                                backgroundImage:
                                    RiderProfile.profileImage != null
                                    ? FileImage(RiderProfile.profileImage!)
                                    : null,
                                child: RiderProfile.profileImage == null
                                    ? const Icon(
                                        Icons.person,
                                        size: 50,
                                        color: darkMaroon,
                                      )
                                    : null,
                              ),
                            ),

                            if (_isUpdatingPhoto)
                              Positioned.fill(
                                child: Container(
                                  margin: const EdgeInsets.all(3),
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    color: Colors.black.withValues(alpha: 0.4),
                                  ),
                                  child: const Center(
                                    child: SizedBox(
                                      width: 22,
                                      height: 22,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: Colors.white,
                                      ),
                                    ),
                                  ),
                                ),
                              ),

                            Positioned(
                              bottom: 2,
                              right: 2,
                              child: Container(
                                width: 32,
                                height: 32,
                                decoration: BoxDecoration(
                                  color: cream,
                                  shape: BoxShape.circle,
                                  border: Border.all(
                                    color: darkMaroon,
                                    width: 2,
                                  ),
                                ),
                                child: const Icon(
                                  Icons.camera_alt_rounded,
                                  color: darkMaroon,
                                  size: 16,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),

                      const SizedBox(height: 10),

                      TextButton.icon(
                        onPressed: _isUpdatingPhoto
                            ? null
                            : _showProfilePhotoOptions,
                        icon: const Icon(
                          Icons.photo_library_outlined,
                          size: 16,
                        ),
                        label: const Text(
                          'Change Profile Picture',
                          style: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        style: TextButton.styleFrom(
                          foregroundColor: Colors.white.withValues(alpha: 0.85),
                          disabledForegroundColor: Colors.white.withValues(
                            alpha: 0.4,
                          ),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 6,
                          ),
                        ),
                      ),

                      const SizedBox(height: 2),

                      GestureDetector(
                        onTap: () async {
                          await Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (context) =>
                                  const PersonalInformationScreen(),
                            ),
                          );
                          // Refresh name / ID after possible edits.
                          if (mounted) setState(() {});
                        },
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Flexible(
                              child: Text(
                                _displayName,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: 21,
                                  fontWeight: FontWeight.w800,
                                  color: Colors.white,
                                  letterSpacing: -0.3,
                                ),
                              ),
                            ),

                            const SizedBox(width: 6),

                            Icon(
                              Icons.edit_rounded,
                              size: 14,
                              color: Colors.white.withValues(alpha: 0.7),
                            ),
                          ],
                        ),
                      ),

                      const SizedBox(height: 4),

                      Text(
                        'Rider ID: $_displayRiderId',
                        style: TextStyle(
                          fontSize: 12.5,
                          color: Colors.white.withValues(alpha: 0.75),
                        ),
                      ),

                      const SizedBox(height: 14),

                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 7,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.14),
                          borderRadius: BorderRadius.circular(30),
                        ),
                        child: const Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.circle,
                              size: 8,
                              color: Color(0xFFF3E9E1),
                            ),

                            SizedBox(width: 7),

                            Text(
                              'Online',
                              style: TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w700,
                                fontSize: 12.5,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 20),

              // ============================================
              // STATS
              // ============================================
              _EntranceAnimation(
                delay: 140,
                child: Row(
                  children: [
                    Expanded(
                      child: _statCard(
                        icon: Icons.star_rounded,
                        iconColor: const Color(0xFFC77700),
                        value: '4.8',
                        label: 'Rating',
                      ),
                    ),

                    const SizedBox(width: 11),

                    Expanded(
                      child: _statCard(
                        icon: Icons.delivery_dining_rounded,
                        iconColor: darkMaroon,
                        value: '256',
                        label: 'Deliveries',
                      ),
                    ),

                    const SizedBox(width: 11),

                    Expanded(
                      child: _statCard(
                        icon: Icons.calendar_month_rounded,
                        iconColor: maroon,
                        value: '3 Mos.',
                        label: 'Member',
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 20),

              // ============================================
              // VERIFIED / PENDING RIDER
              // ============================================
              _EntranceAnimation(
                delay: 190,
                child: Material(
                  color: Colors.transparent,
                  borderRadius: BorderRadius.circular(radiusLg),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(radiusLg),
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => const DocumentsScreen(),
                        ),
                      );
                    },
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: const Color(0xFF873632).withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(radiusLg),
                        border: Border.all(
                          color: const Color(0xFF873632)
                              .withValues(alpha: 0.22),
                        ),
                      ),
                      child: Row(
                        children: [
                          Container(
                            width: 42,
                            height: 42,
                            decoration: BoxDecoration(
                              color: const Color(0xFF873632),
                              borderRadius: BorderRadius.circular(13),
                            ),
                            child: Icon(
                              _isApproved
                                  ? Icons.verified_rounded
                                  : Icons.hourglass_top_rounded,
                              color: Colors.white,
                              size: 20,
                            ),
                          ),

                          const SizedBox(width: 14),

                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  _isApproved
                                      ? 'Verified Rider'
                                      : 'Pending Verification',
                                  style: const TextStyle(
                                    fontSize: 15.5,
                                    fontWeight: FontWeight.w800,
                                    color: darkMaroon,
                                  ),
                                ),

                                const SizedBox(height: 3),

                                Text(
                                  _isApproved
                                      ? 'Your rider account has been verified.'
                                      : 'Your documents are under review by the admin.',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: textMuted,
                                  ),
                                ),
                              ],
                            ),
                          ),

                          const Icon(
                            Icons.chevron_right_rounded,
                            color: Color(0xFF873632),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),

              const SizedBox(height: 26),

              _EntranceAnimation(delay: 220, child: _sectionLabel('Work')),

              const SizedBox(height: 12),

              _EntranceAnimation(
                delay: 240,
                child: Container(
                  padding: const EdgeInsets.all(16),
                  decoration: cardDecoration,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            width: 42,
                            height: 42,
                            decoration: BoxDecoration(
                              color: darkMaroon.withValues(alpha: 0.08),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: const Icon(
                              Icons.assignment_rounded,
                              color: darkMaroon,
                              size: 20,
                            ),
                          ),
                          const SizedBox(width: 12),
                          const Expanded(
                            child: Text(
                              'Current Assignment',
                              style: TextStyle(
                                color: darkMaroon,
                                fontSize: 15,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 6,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFF873632)
                                  .withValues(alpha: 0.08),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: const Text(
                              'Active',
                              style: TextStyle(
                                color: Color(0xFF873632),
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          Expanded(
                            child: _AssignmentInfoItem(
                              icon: Icons.warehouse_outlined,
                              label: 'Assigned hub',
                              value: AssignmentScreen.assignedHub,
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: _AssignmentInfoItem(
                              icon: Icons.location_on_outlined,
                              label: 'Delivery area',
                              value: AssignmentScreen.assignedArea,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          Expanded(
                            child: _AssignmentInfoItem(
                              icon: Icons.schedule_rounded,
                              label: 'Shift',
                              value: AssignmentScreen.assignedShift,
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: _AssignmentInfoItem(
                              icon: Icons.inventory_2_outlined,
                              label: 'Assigned parcels',
                              value: '${AssignmentScreen.assignedParcels}',
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      TextButton.icon(
                        onPressed: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (context) => const AssignmentScreen(),
                            ),
                          );
                        },
                        icon: const Icon(Icons.arrow_forward_rounded, size: 17),
                        label: const Text('View assignment details'),
                        style: TextButton.styleFrom(
                          foregroundColor: darkMaroon,
                          padding: const EdgeInsets.symmetric(horizontal: 4),
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 14),

              // ============================================
              // ACCOUNT
              // ============================================
              _EntranceAnimation(delay: 240, child: _sectionLabel('Account')),

              const SizedBox(height: 12),

              _EntranceAnimation(
                delay: 280,
                child: _profileMenu(
                  icon: Icons.person_rounded,
                  title: 'Personal Information',
                  subtitle: 'Name, contact number, and address',
                  onTap: () async {
                    await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const PersonalInformationScreen(),
                      ),
                    );
                    if (mounted) setState(() {});
                  },
                ),
              ),

              _EntranceAnimation(
                delay: 320,
                child: _profileMenu(
                  icon: _vehicleMenuIcon,
                  title: 'Vehicle Information',
                  subtitle: _vehicleSubtitle,
                  onTap: () async {
                    await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const VehicleInformationScreen(),
                      ),
                    );
                    if (mounted) setState(() {});
                  },
                ),
              ),

              _EntranceAnimation(
                delay: 360,
                child: _profileMenu(
                  icon: Icons.description_rounded,
                  title: 'Documents',
                  subtitle: 'License and verification documents',
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const DocumentsScreen(),
                      ),
                    );
                  },
                ),
              ),

              // ============================================
              // CHANGE PASSWORD
              // ============================================
              _EntranceAnimation(
                delay: 400,
                child: _profileMenu(
                  icon: Icons.lock_reset_rounded,
                  title: 'Change Password',
                  subtitle: 'Update your account password',
                  onTap: _showChangePasswordSheet,
                ),
              ),

              const SizedBox(height: 12),

              // ============================================
              // SETTINGS & SUPPORT
              // ============================================
              _EntranceAnimation(
                delay: 440,
                child: _sectionLabel('Settings & Support'),
              ),

              const SizedBox(height: 12),

              _EntranceAnimation(
                delay: 480,
                child: _profileMenu(
                  icon: Icons.settings_rounded,
                  title: 'App Settings',
                  subtitle: 'Notifications and preferences',
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const AppSettingsScreen(),
                      ),
                    );
                  },
                ),
              ),

              _EntranceAnimation(
                delay: 520,
                child: _profileMenu(
                  icon: Icons.support_agent_rounded,
                  title: 'Help & Support',
                  subtitle: 'Chat with Customer Service',
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const HelpSupportScreen(),
                      ),
                    );
                  },
                ),
              ),

              _EntranceAnimation(
                delay: 540,
                child: _profileMenu(
                  icon: Icons.forum_rounded,
                  title: 'Buyer Chat',
                  subtitle: 'Message your customers about their orders',
                  onTap: _openBuyerChat,
                ),
              ),

              _EntranceAnimation(
                delay: 560,
                child: _profileMenu(
                  icon: Icons.bar_chart_rounded,
                  title: 'Rider Performance',
                  subtitle: 'Ratings and delivery performance',
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const RiderPerformanceScreen(),
                      ),
                    );
                  },
                ),
              ),

              const SizedBox(height: 12),

              // ============================================
              // LOG OUT
              // ============================================
              _EntranceAnimation(
                delay: 600,
                child: _profileMenu(
                  icon: Icons.logout_rounded,
                  title: 'Log Out',
                  subtitle: 'Sign out of your rider account',
                  onTap: _showLogoutDialog,
                  isDanger: true,
                ),
              ),

              // ============================================
              // DELETE ACCOUNT / CANCEL DELETION
              // ============================================
              _EntranceAnimation(
                delay: 640,
                child: AccountDeletion.isPending
                    ? _profileMenu(
                        icon: Icons.restore_rounded,
                        title: 'Cancel Account Deletion',
                        subtitle:
                            'Deletes on ${AccountDeletion.formatDate(AccountDeletion.deletionDate!)} • ${AccountDeletion.daysLeft} days left',
                        onTap: _cancelAccountDeletion,
                      )
                    : _profileMenu(
                        icon: Icons.delete_forever_rounded,
                        title: 'Delete Account',
                        subtitle:
                            'Permanently deleted after ${AccountDeletion.graceDays} days',
                        onTap: _showDeleteAccountDialog,
                        isDanger: true,
                      ),
              ),

              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================
  // UPGRADE BUTTON
  // Shows "Pending" while an upgrade request is under review.
  // ============================================

  Widget _upgradeButton() {
    final bool pending = UpgradeScreen.isPending;

    return Material(
      color: pending ? lightBeige : darkMaroon,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: _openUpgrade,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                pending
                    ? Icons.hourglass_top_rounded
                    : Icons.workspace_premium_rounded,
                color: pending ? darkMaroon : Colors.white,
                size: 18,
              ),
              const SizedBox(width: 6),
              Text(
                pending ? 'Pending' : 'Upgrade',
                style: TextStyle(
                  color: pending ? darkMaroon : Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 12.5,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================
  // SECTION LABEL
  // ============================================

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

  // ============================================
  // STAT CARD
  // ============================================

  Widget _statCard({
    required IconData icon,
    required Color iconColor,
    required String value,
    required String label,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 18, horizontal: 8),
      decoration: cardDecoration,
      child: Column(
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

          const SizedBox(height: 10),

          Text(
            value,
            style: const TextStyle(
              fontSize: 17.5,
              fontWeight: FontWeight.w800,
              color: darkMaroon,
              letterSpacing: -0.3,
            ),
          ),

          const SizedBox(height: 2),

          Text(
            label,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 11,
              color: textMuted,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================
  // PROFILE MENU
  // ============================================

  Widget _profileMenu({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
    bool isDanger = false,
  }) {
    final Color accent = isDanger ? danger : darkMaroon;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: surface,
        borderRadius: BorderRadius.circular(radiusMd),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(radiusMd),
          child: Container(
            padding: const EdgeInsets.all(15),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(radiusMd),
              border: Border.all(color: darkMaroon.withValues(alpha: 0.05)),
              boxShadow: shadowSoft,
            ),
            child: Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: accent.withValues(alpha: 0.10),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: Icon(icon, color: accent, size: 20),
                ),

                const SizedBox(width: 13),

                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: TextStyle(
                          fontSize: 14.5,
                          fontWeight: FontWeight.w700,
                          color: isDanger ? danger : darkMaroon,
                        ),
                      ),

                      const SizedBox(height: 3),

                      Text(
                        subtitle,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 11, color: textMuted),
                      ),
                    ],
                  ),
                ),

                Icon(
                  Icons.chevron_right_rounded,
                  color: accent.withValues(alpha: 0.5),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ============================================================
// CHANGE PASSWORD SHEET
// Requires the current password before setting a new one.
// Pops with `true` when the password was changed successfully.
// ============================================================

class _ChangePasswordSheet extends StatefulWidget {
  const _ChangePasswordSheet();

  @override
  State<_ChangePasswordSheet> createState() => _ChangePasswordSheetState();
}

class _ChangePasswordSheetState extends State<_ChangePasswordSheet> {
  static const Color _darkMaroon = _ProfileScreenState.darkMaroon;
  static const Color _brownBeige = _ProfileScreenState.brownBeige;
  static const Color _cream = _ProfileScreenState.cream;
  static const Color _surface = _ProfileScreenState.surface;
  static const Color _textMuted = _ProfileScreenState.textMuted;
  static const Color _danger = _ProfileScreenState.danger;

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  final TextEditingController _currentController = TextEditingController();
  final TextEditingController _newController = TextEditingController();
  final TextEditingController _confirmController = TextEditingController();

  bool _showCurrent = false;
  bool _showNew = false;
  bool _showConfirm = false;

  bool _isSaving = false;

  // Error returned by the server when the current password is wrong.
  String? _currentServerError;

  // Error shown when something else fails (network, server error...).
  String? _generalError;

  @override
  void dispose() {
    _currentController.dispose();
    _newController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  // ============================================
  // VALIDATORS
  // ============================================

  String? _validateCurrent(String? value) {
    if (value == null || value.isEmpty) {
      return 'Enter your current password.';
    }
    if (_currentServerError != null) {
      return _currentServerError;
    }
    return null;
  }

  String? _validateNew(String? value) {
    if (value == null || value.isEmpty) {
      return 'Enter a new password.';
    }
    if (value.length < 8) {
      return 'Password must be at least 8 characters.';
    }
    if (!RegExp(r'[A-Za-z]').hasMatch(value) ||
        !RegExp(r'[0-9]').hasMatch(value)) {
      return 'Use both letters and numbers.';
    }
    if (value == _currentController.text) {
      return 'New password must be different from the current one.';
    }
    return null;
  }

  String? _validateConfirm(String? value) {
    if (value == null || value.isEmpty) {
      return 'Confirm your new password.';
    }
    if (value != _newController.text) {
      return 'Passwords do not match.';
    }
    return null;
  }

  // ============================================
  // SUBMIT
  // ============================================

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();

    setState(() {
      _currentServerError = null;
      _generalError = null;
    });

    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _isSaving = true;
    });

    final PasswordChangeResult result = await PasswordService.changePassword(
      currentPassword: _currentController.text,
      newPassword: _newController.text,
    );

    if (!mounted) return;

    switch (result) {
      case PasswordChangeResult.success:
        Navigator.pop(context, true);
        return;

      case PasswordChangeResult.wrongCurrentPassword:
        setState(() {
          _isSaving = false;
          _currentServerError = 'Current password is incorrect.';
        });
        _formKey.currentState?.validate();
        return;

      case PasswordChangeResult.error:
        setState(() {
          _isSaving = false;
          _generalError = 'Something went wrong. Please try again.';
        });
        return;
    }
  }

  // ============================================
  // UI
  // ============================================

  @override
  Widget build(BuildContext context) {
    final double keyboard = MediaQuery.of(context).viewInsets.bottom;

    return Padding(
      padding: EdgeInsets.only(bottom: keyboard),
      child: Container(
        decoration: const BoxDecoration(
          color: _cream,
          borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
        ),
        child: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
            child: Form(
              key: _formKey,
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: _brownBeige.withValues(alpha: 0.35),
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                  ),

                  const SizedBox(height: 18),

                  Row(
                    children: [
                      Container(
                        width: 40,
                        height: 40,
                        decoration: BoxDecoration(
                          color: _darkMaroon,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Icon(
                          Icons.lock_reset_rounded,
                          color: Colors.white,
                          size: 20,
                        ),
                      ),
                      const SizedBox(width: 12),
                      const Expanded(
                        child: Text(
                          'Change Password',
                          style: TextStyle(
                            color: _darkMaroon,
                            fontSize: 16.5,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.2,
                          ),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 8),

                  const Text(
                    'For your security, enter your current password first.',
                    style: TextStyle(
                      color: _textMuted,
                      fontSize: 12.5,
                      height: 1.4,
                    ),
                  ),

                  const SizedBox(height: 18),

                  // CURRENT PASSWORD
                  _passwordField(
                    controller: _currentController,
                    label: 'Current Password',
                    obscure: !_showCurrent,
                    onToggle: () =>
                        setState(() => _showCurrent = !_showCurrent),
                    validator: _validateCurrent,
                    textInputAction: TextInputAction.next,
                    onChanged: (_) {
                      if (_currentServerError != null ||
                          _generalError != null) {
                        setState(() {
                          _currentServerError = null;
                          _generalError = null;
                        });
                      }
                    },
                  ),

                  const SizedBox(height: 14),

                  // NEW PASSWORD
                  _passwordField(
                    controller: _newController,
                    label: 'New Password',
                    obscure: !_showNew,
                    onToggle: () => setState(() => _showNew = !_showNew),
                    validator: _validateNew,
                    textInputAction: TextInputAction.next,
                    onChanged: (_) => setState(() {}),
                  ),

                  const SizedBox(height: 8),

                  _requirementsHint(),

                  const SizedBox(height: 14),

                  // CONFIRM PASSWORD
                  _passwordField(
                    controller: _confirmController,
                    label: 'Confirm New Password',
                    obscure: !_showConfirm,
                    onToggle: () =>
                        setState(() => _showConfirm = !_showConfirm),
                    validator: _validateConfirm,
                    textInputAction: TextInputAction.done,
                    onSubmitted: (_) => _isSaving ? null : _submit(),
                  ),

                  if (_generalError != null) ...[
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        const Icon(
                          Icons.error_outline_rounded,
                          color: _danger,
                          size: 16,
                        ),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            _generalError!,
                            style: const TextStyle(
                              color: _danger,
                              fontSize: 12.5,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],

                  const SizedBox(height: 20),

                  Row(
                    children: [
                      Expanded(
                        child: TextButton(
                          onPressed: _isSaving
                              ? null
                              : () => Navigator.pop(context, false),
                          style: TextButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(
                                _ProfileScreenState.radiusSm,
                              ),
                            ),
                          ),
                          child: const Text(
                            'Cancel',
                            style: TextStyle(
                              color: _textMuted,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        flex: 2,
                        child: ElevatedButton(
                          onPressed: _isSaving ? null : _submit,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: _darkMaroon,
                            foregroundColor: Colors.white,
                            disabledBackgroundColor: _darkMaroon.withValues(
                              alpha: 0.6,
                            ),
                            disabledForegroundColor: Colors.white,
                            elevation: 0,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(
                                _ProfileScreenState.radiusSm,
                              ),
                            ),
                          ),
                          child: _isSaving
                              ? const SizedBox(
                                  width: 20,
                                  height: 20,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                  ),
                                )
                              : const Text(
                                  'Update Password',
                                  style: TextStyle(fontWeight: FontWeight.w700),
                                ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  // ============================================
  // PASSWORD REQUIREMENTS HINT
  // ============================================

  Widget _requirementsHint() {
    final String value = _newController.text;

    final bool hasLength = value.length >= 8;
    final bool hasMix =
        RegExp(r'[A-Za-z]').hasMatch(value) && RegExp(r'[0-9]').hasMatch(value);

    Widget item(bool ok, String text) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 3),
        child: Row(
          children: [
            Icon(
              ok ? Icons.check_circle_rounded : Icons.circle_outlined,
              size: 14,
              color: ok ? _ProfileScreenState.success : _textMuted,
            ),
            const SizedBox(width: 6),
            Text(
              text,
              style: TextStyle(
                fontSize: 11.5,
                color: ok ? _ProfileScreenState.success : _textMuted,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        item(hasLength, 'At least 8 characters'),
        item(hasMix, 'Contains letters and numbers'),
      ],
    );
  }

  // ============================================
  // PASSWORD FIELD
  // ============================================

  Widget _passwordField({
    required TextEditingController controller,
    required String label,
    required bool obscure,
    required VoidCallback onToggle,
    required String? Function(String?) validator,
    TextInputAction textInputAction = TextInputAction.next,
    ValueChanged<String>? onChanged,
    ValueChanged<String>? onSubmitted,
  }) {
    OutlineInputBorder border(Color color, [double width = 1]) {
      return OutlineInputBorder(
        borderRadius: BorderRadius.circular(_ProfileScreenState.radiusMd),
        borderSide: BorderSide(color: color, width: width),
      );
    }

    return TextFormField(
      controller: controller,
      obscureText: obscure,
      enabled: !_isSaving,
      enableSuggestions: false,
      autocorrect: false,
      textInputAction: textInputAction,
      onChanged: onChanged,
      onFieldSubmitted: onSubmitted,
      validator: validator,
      style: const TextStyle(
        color: _darkMaroon,
        fontWeight: FontWeight.w600,
        fontSize: 14,
      ),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: const TextStyle(color: _textMuted, fontSize: 13.5),
        floatingLabelStyle: const TextStyle(
          color: _darkMaroon,
          fontWeight: FontWeight.w700,
        ),
        filled: true,
        fillColor: _surface,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 16,
        ),
        prefixIcon: const Icon(
          Icons.lock_outline_rounded,
          color: _darkMaroon,
          size: 20,
        ),
        suffixIcon: IconButton(
          onPressed: onToggle,
          icon: Icon(
            obscure ? Icons.visibility_off_outlined : Icons.visibility_outlined,
            color: _textMuted,
            size: 20,
          ),
        ),
        errorMaxLines: 2,
        errorStyle: const TextStyle(
          color: _danger,
          fontSize: 11.5,
          fontWeight: FontWeight.w600,
        ),
        enabledBorder: border(_darkMaroon.withValues(alpha: 0.10)),
        disabledBorder: border(_darkMaroon.withValues(alpha: 0.06)),
        focusedBorder: border(_darkMaroon, 1.6),
        errorBorder: border(_danger),
        focusedErrorBorder: border(_danger, 1.6),
      ),
    );
  }
}

// ============================================================
// CURRENT ASSIGNMENT INFO ITEM
// ============================================================

class _AssignmentInfoItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;

  const _AssignmentInfoItem({
    required this.icon,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    const darkMaroon = Color(0xFF62191C);
    const cream = Color(0xFFF3E9E1);
    const textMuted = Color(0xFF8B6F63);

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: cream,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: darkMaroon.withValues(alpha: 0.09),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: darkMaroon, size: 17),
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: textMuted,
                    fontSize: 9.5,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ============================================================
// SUBTLE ENTRANCE ANIMATION
// Fade + small rise + tiny pop
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
      if (!mounted) {
        return;
      }

      setState(() {
        _show = true;
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedOpacity(
      opacity: _show ? 1 : 0,
      duration: const Duration(milliseconds: 380),
      curve: Curves.easeOutCubic,

      child: AnimatedSlide(
        offset: _show ? Offset.zero : const Offset(0, 0.025),
        duration: const Duration(milliseconds: 380),
        curve: Curves.easeOutCubic,

        child: AnimatedScale(
          scale: _show ? 1 : 0.985,
          duration: const Duration(milliseconds: 380),
          curve: Curves.easeOutCubic,
          child: widget.child,
        ),
      ),
    );
  }
}

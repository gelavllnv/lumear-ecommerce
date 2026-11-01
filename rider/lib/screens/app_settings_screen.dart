import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/material.dart';

import '../app_settings_controller.dart';
import '../app_strings.dart';

class AppSettingsScreen extends StatefulWidget {
  const AppSettingsScreen({super.key});

  @override
  State<AppSettingsScreen> createState() => _AppSettingsScreenState();
}

class _AppSettingsScreenState extends State<AppSettingsScreen> {
  static const Color darkMaroon = Color(0xFF62191C);

  static const Color brownBeige = Color(0xFF9E7161);

  static const Color lightBeige = Color(0xFFCAAE9F);

  static const Color cream = Color(0xFFE0CFC2);

  final AudioPlayer _audioPlayer = AudioPlayer();

  @override
  void initState() {
    super.initState();

    appSettings.addListener(_refresh);
  }

  @override
  void dispose() {
    appSettings.removeListener(_refresh);

    _audioPlayer.dispose();

    super.dispose();
  }

  void _refresh() {
    if (!mounted) return;

    setState(() {});
  }

  // ============================================================
  // NORMAL NOTIFICATION SOUND
  // ============================================================

  Future<void> _playNotificationSound() async {
    try {
      await _audioPlayer.stop();

      await _audioPlayer.play(AssetSource('sounds/notification.mp3'));
    } catch (e) {
      debugPrint('Notification sound error: $e');

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: darkMaroon,
          behavior: SnackBarBehavior.floating,
          content: Text(
            tr(
              english: 'Unable to play notification sound.',
              filipino: 'Hindi ma-play ang notification sound.',
            ),
          ),
        ),
      );
    }
  }

  // ============================================================
  // DELIVERY NOTIFICATION SOUND
  // ============================================================

  Future<void> _playDeliverySound() async {
    try {
      await _audioPlayer.stop();

      await _audioPlayer.play(AssetSource('sounds/delivery_notification.mp3'));
    } catch (e) {
      debugPrint('Delivery notification sound error: $e');

      if (!mounted) return;

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: darkMaroon,
          behavior: SnackBarBehavior.floating,
          content: Text(
            tr(
              english: 'Unable to play delivery notification sound.',
              filipino: 'Hindi ma-play ang tunog ng delivery notification.',
            ),
          ),
        ),
      );
    }
  }

  // ============================================================
  // DELIVERY NOTIFICATION SWITCH
  // ============================================================

  Future<void> _testDeliveryNotification(bool value) async {
    await appSettings.setDeliveryNotifications(value);

    if (!value) return;

    await _playDeliverySound();

    if (!mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: darkMaroon,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        content: Row(
          children: [
            const Icon(Icons.delivery_dining, color: Colors.white),

            const SizedBox(width: 12),

            Expanded(
              child: Text(
                tr(
                  english: 'Click the sound of lumear!',
                  filipino: 'May bagong delivery request!',
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // NORMAL NOTIFICATION SOUND SWITCH
  // ============================================================

  Future<void> _testNotificationSound(bool value) async {
    await appSettings.setSoundEnabled(value);

    if (!value) return;

    await _playNotificationSound();
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    final Color backgroundColor = cream;

    final Color cardColor = Colors.white.withValues(alpha: 0.75);

    const Color primaryText = darkMaroon;

    const Color secondaryText = brownBeige;

    const Color accentColor = lightBeige;

    return Scaffold(
      backgroundColor: backgroundColor,

      appBar: AppBar(
        backgroundColor: backgroundColor,

        elevation: 0,

        surfaceTintColor: Colors.transparent,

        leading: IconButton(
          onPressed: () {
            Navigator.pop(context);
          },

          icon: Icon(Icons.arrow_back, color: primaryText),
        ),

        title: Text(
          tr(english: 'App Settings', filipino: 'Mga Setting ng App'),

          style: TextStyle(fontWeight: FontWeight.bold, color: primaryText),
        ),
      ),

      body: SingleChildScrollView(
        padding: const EdgeInsets.all(18),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            // ====================================================
            // HEADER
            // ====================================================

            Container(
              width: double.infinity,

              padding: const EdgeInsets.all(22),

              decoration: BoxDecoration(
                color: darkMaroon,

                borderRadius: BorderRadius.circular(24),
              ),

              child: Column(
                children: [
                  CircleAvatar(
                    radius: 42,

                    backgroundColor: accentColor,

                    child: Icon(
                      Icons.settings_outlined,

                      size: 44,

                      color: primaryText,
                    ),
                  ),

                  const SizedBox(height: 12),

                  Text(
                    tr(english: 'Preferences', filipino: 'Mga Kagustuhan'),

                    style: const TextStyle(
                      fontSize: 20,

                      fontWeight: FontWeight.bold,

                      color: Colors.white,
                    ),
                  ),

                  const SizedBox(height: 5),

                  Text(
                    tr(
                      english: 'Customize how your rider app works',

                      filipino: 'I-customize kung paano gumagana ang rider app',
                    ),

                    textAlign: TextAlign.center,

                    style: TextStyle(fontSize: 13, color: lightBeige),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // ====================================================
            // NOTIFICATIONS
            // ====================================================
            Text(
              tr(english: 'Notifications', filipino: 'Mga Abiso'),

              style: TextStyle(
                fontSize: 20,

                fontWeight: FontWeight.bold,

                color: primaryText,
              ),
            ),

            const SizedBox(height: 12),

            // DELIVERY NOTIFICATION
            _switchTile(
              icon: Icons.delivery_dining,

              title: tr(
                english: 'Notification Sound',

                filipino: 'Mga Abiso sa Delivery',
              ),

              subtitle: tr(
                english: 'This Notification Sound.',

                filipino: 'Gamitin ang special LumeaR sound kapag may bagong delivery request',
              ),

              value: appSettings.deliveryNotifications,

              onChanged: _testDeliveryNotification,

              cardColor: cardColor,

              primaryText: primaryText,

              secondaryText: secondaryText,

              accentColor: accentColor,
            ),

            // NORMAL NOTIFICATION SOUND
            _switchTile(
              icon: Icons.volume_up_outlined,

              title: tr(
                english: 'QR Notification Sound',

                filipino: 'Tunog ng Abiso',
              ),

              subtitle: tr(
                english: 'This is Notification Sound for successfully Scan',

                filipino: 'Patugtugin ang normal na notification sound',
              ),

              value: appSettings.soundEnabled,

              onChanged: _testNotificationSound,

              cardColor: cardColor,

              primaryText: primaryText,

              secondaryText: secondaryText,

              accentColor: accentColor,
            ),

            const SizedBox(height: 22),

            // ====================================================
            // APP PREFERENCES
            // ====================================================
            Text(
              tr(english: 'App Preferences', filipino: 'Mga Kagustuhan sa App'),

              style: TextStyle(
                fontSize: 20,

                fontWeight: FontWeight.bold,

                color: primaryText,
              ),
            ),

            const SizedBox(height: 12),

            // LOCATION
            _switchTile(
              icon: Icons.location_on_outlined,

              title: tr(
                english: 'Location Services',

                filipino: 'Serbisyo ng Lokasyon',
              ),

              subtitle: tr(
                english: 'Allow location features for delivery navigation',

                filipino:
                    'Payagan ang location features para sa delivery navigation',
              ),

              value: appSettings.locationEnabled,

              onChanged: (value) async {
                await appSettings.setLocationEnabled(value);
              },

              cardColor: cardColor,

              primaryText: primaryText,

              secondaryText: secondaryText,

              accentColor: accentColor,
            ),

            const SizedBox(height: 22),

            // ====================================================
            // LANGUAGE
            // ====================================================
            Text(
              tr(english: 'Language', filipino: 'Wika'),

              style: TextStyle(
                fontSize: 20,

                fontWeight: FontWeight.bold,

                color: primaryText,
              ),
            ),

            const SizedBox(height: 12),

            Container(
              width: double.infinity,

              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),

              decoration: BoxDecoration(
                color: cardColor,

                borderRadius: BorderRadius.circular(18),
              ),

              child: Row(
                children: [
                  Container(
                    width: 45,
                    height: 45,

                    decoration: BoxDecoration(
                      color: accentColor,

                      borderRadius: BorderRadius.circular(13),
                    ),

                    child: Icon(Icons.language, color: primaryText),
                  ),

                  const SizedBox(width: 14),

                  Expanded(
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<String>(
                        value: appSettings.language,

                        isExpanded: true,

                        dropdownColor: cardColor,

                        style: TextStyle(
                          color: primaryText,

                          fontWeight: FontWeight.w600,

                          fontSize: 14,
                        ),

                        icon: Icon(
                          Icons.keyboard_arrow_down,

                          color: primaryText,
                        ),

                        items: const [
                          DropdownMenuItem(
                            value: 'English',

                            child: Text('English'),
                          ),

                          DropdownMenuItem(
                            value: 'Filipino',

                            child: Text('Filipino'),
                          ),
                        ],

                        onChanged: (value) async {
                          if (value == null) {
                            return;
                          }

                          await appSettings.setLanguage(value);
                        },
                      ),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 25),

            // ====================================================
            // TEST INFORMATION
            // ====================================================
            Container(
              width: double.infinity,

              padding: const EdgeInsets.all(16),

              decoration: BoxDecoration(
                color: accentColor,

                borderRadius: BorderRadius.circular(18),
              ),

              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  Icon(Icons.info_outline, color: primaryText),

                  const SizedBox(width: 12),

                  Expanded(
                    child: Text(
                      tr(
                        english: 'For testing: turning Delivery Notifications on will play the delivery sound once. Turning Notification Sound on will play the normal sound once.',

                        filipino: 'Para sa testing: kapag in-on ang Delivery Notifications, isang beses tutunog ang delivery sound. Kapag in-on ang Notification Sound, isang beses tutunog ang normal sound.',
                      ),

                      style: TextStyle(
                        fontSize: 12,

                        height: 1.45,

                        color: secondaryText,
                      ),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 25),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // REUSABLE SWITCH TILE
  // ============================================================

  Widget _switchTile({
    required IconData icon,

    required String title,

    required String subtitle,

    required bool value,

    required ValueChanged<bool> onChanged,

    required Color cardColor,

    required Color primaryText,

    required Color secondaryText,

    required Color accentColor,
  }) {
    return Container(
      width: double.infinity,

      margin: const EdgeInsets.only(bottom: 10),

      decoration: BoxDecoration(
        color: cardColor,

        borderRadius: BorderRadius.circular(18),
      ),

      child: SwitchListTile(
        value: value,

        onChanged: onChanged,

        activeThumbColor: darkMaroon,

        activeTrackColor: accentColor,

        secondary: Container(
          width: 45,

          height: 45,

          decoration: BoxDecoration(
            color: accentColor,

            borderRadius: BorderRadius.circular(13),
          ),

          child: Icon(icon, color: primaryText),
        ),

        title: Text(
          title,

          style: TextStyle(
            fontSize: 15,

            fontWeight: FontWeight.bold,

            color: primaryText,
          ),
        ),

        subtitle: Text(
          subtitle,

          style: TextStyle(fontSize: 11, color: secondaryText, height: 1.3),
        ),
      ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

class AppSettingsController extends ChangeNotifier {
  static const String _keyDeliveryNotifications = 'delivery_notifications';

  static const String _keySoundEnabled = 'sound_enabled';

  static const String _keyVibrationEnabled = 'vibration_enabled';

  static const String _keyLocationEnabled = 'location_enabled';

  static const String _keyLanguage = 'language';

  bool deliveryNotifications = true;
  bool soundEnabled = true;
  bool vibrationEnabled = true;
  bool locationEnabled = true;

  String language = 'English';

  bool _isLoaded = false;

  bool get isLoaded => _isLoaded;

  // ============================================================
  // LOAD SETTINGS
  // ============================================================

  Future<void> loadSettings() async {
    final prefs = await SharedPreferences.getInstance();

    deliveryNotifications = prefs.getBool(_keyDeliveryNotifications) ?? true;

    soundEnabled = prefs.getBool(_keySoundEnabled) ?? true;

    vibrationEnabled = prefs.getBool(_keyVibrationEnabled) ?? true;

    locationEnabled = prefs.getBool(_keyLocationEnabled) ?? true;

    language = prefs.getString(_keyLanguage) ?? 'English';

    _isLoaded = true;

    notifyListeners();
  }

  // ============================================================
  // DELIVERY NOTIFICATIONS
  // ============================================================

  Future<void> setDeliveryNotifications(bool value) async {
    deliveryNotifications = value;

    notifyListeners();

    final prefs = await SharedPreferences.getInstance();

    await prefs.setBool(_keyDeliveryNotifications, value);
  }

  // ============================================================
  // SOUND
  // ============================================================

  Future<void> setSoundEnabled(bool value) async {
    soundEnabled = value;

    notifyListeners();

    final prefs = await SharedPreferences.getInstance();

    await prefs.setBool(_keySoundEnabled, value);
  }

  // ============================================================
  // VIBRATION
  // ============================================================

  Future<void> setVibrationEnabled(bool value) async {
    vibrationEnabled = value;

    notifyListeners();

    final prefs = await SharedPreferences.getInstance();

    await prefs.setBool(_keyVibrationEnabled, value);
  }

  // ============================================================
  // LOCATION
  // ============================================================

  Future<void> setLocationEnabled(bool value) async {
    locationEnabled = value;

    notifyListeners();

    final prefs = await SharedPreferences.getInstance();

    await prefs.setBool(_keyLocationEnabled, value);
  }

  // ============================================================
  // LANGUAGE
  // ============================================================

  Future<void> setLanguage(String value) async {
    language = value;

    notifyListeners();

    final prefs = await SharedPreferences.getInstance();

    await prefs.setString(_keyLanguage, value);
  }
}

// GLOBAL SETTINGS CONTROLLER
final AppSettingsController appSettings = AppSettingsController();

import 'dart:io';

import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Keeps the documents a rider uploaded during sign up in permanent app
/// storage, so the Documents screen can still show them later
/// (after logging in, after restarting the app, or after the OS clears
/// the image picker's temporary cache).
///
/// Files are remembered per rider email.
class RiderDocumentStore {
  RiderDocumentStore._();

  static const String license = 'license';
  static const String or = 'or';
  static const String cr = 'cr';

  static String _key(String email, String type) =>
      'signup_doc_${email.trim().toLowerCase()}_$type';

  static String _safe(String text) =>
      text.trim().toLowerCase().replaceAll(RegExp(r'[^a-z0-9]'), '_');

  /// Copies [sourcePath] into permanent storage and remembers it for [email].
  static Future<File> saveSignupDocument({
    required String email,
    required String type,
    required String sourcePath,
  }) async {
    final dir = await getApplicationDocumentsDirectory();
    final folder = Directory(p.join(dir.path, 'signup_documents'));

    if (!await folder.exists()) {
      await folder.create(recursive: true);
    }

    String extension = p.extension(sourcePath);
    if (extension.isEmpty) extension = '.jpg';

    final String destPath = p.join(
      folder.path,
      '${_safe(email)}_${type}_${DateTime.now().millisecondsSinceEpoch}'
      '$extension',
    );

    final File saved = await File(sourcePath).copy(destPath);

    final prefs = await SharedPreferences.getInstance();
    final String key = _key(email, type);
    final String? oldPath = prefs.getString(key);

    await prefs.setString(key, saved.path);

    // Remove the previous copy so storage does not pile up.
    if (oldPath != null && oldPath != saved.path) {
      try {
        final oldFile = File(oldPath);
        if (await oldFile.exists()) await oldFile.delete();
      } catch (_) {}
    }

    return saved;
  }

  /// Returns the saved document for [email], or null if there is none
  /// (or the file no longer exists).
  static Future<File?> load(String email, String type) async {
    if (email.trim().isEmpty) return null;

    try {
      final prefs = await SharedPreferences.getInstance();
      final String? path = prefs.getString(_key(email, type));

      if (path == null || path.isEmpty) return null;

      final file = File(path);
      return await file.exists() ? file : null;
    } catch (_) {
      return null;
    }
  }
}

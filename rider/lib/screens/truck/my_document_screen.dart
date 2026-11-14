import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_application_1/models/rider_profile.dart';
import 'package:image_picker/image_picker.dart';
import 'package:open_filex/open_filex.dart';

import '../../services/rider_api_service.dart';

enum _DocumentSource { camera, gallery, file }

/// Documents only: Driver's License (with expiration date picker), OR, CR.
/// Files come from sign up; the rider can replace them here.
class MyDocumentsScreen extends StatefulWidget {
  const MyDocumentsScreen({super.key});

  @override
  State<MyDocumentsScreen> createState() => _MyDocumentsScreenState();
}

class _MyDocumentsScreenState extends State<MyDocumentsScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);

  final ImagePicker _picker = ImagePicker();
  File? _licenseFile;
  File? _orFile;
  File? _crFile;
  bool _isLoadingDocuments = true;

  @override
  void initState() {
    super.initState();
    _loadDocuments();
  }

  Future<void> _loadDocuments() async {
    try {
      final files = await Future.wait([
        RiderApiService.downloadDocument('driver_license'),
        RiderApiService.downloadDocument('or_document'),
        RiderApiService.downloadDocument('cr_document'),
      ]);
      if (!mounted) return;
      setState(() {
        _licenseFile = files[0] ?? _existingFile(RiderProfile.localDriverLicenseFile);
        _orFile = files[1] ?? _existingFile(RiderProfile.localOrFile);
        _crFile = files[2] ?? _existingFile(RiderProfile.localCrFile);
        _isLoadingDocuments = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _licenseFile = _existingFile(RiderProfile.localDriverLicenseFile);
        _orFile = _existingFile(RiderProfile.localOrFile);
        _crFile = _existingFile(RiderProfile.localCrFile);
        _isLoadingDocuments = false;
      });
    }
  }

  File? _existingFile(File? file) =>
      file != null && file.existsSync() ? file : null;

  static const List<String> _months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
  ];

  String _fmt(DateTime d) => '${_months[d.month - 1]} ${d.day}, ${d.year}';

  Future<void> _pickExpiry() async {
    final DateTime now = DateTime.now();
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: RiderProfile.licenseExpiry ?? now,
      firstDate: DateTime(now.year - 5),
      lastDate: DateTime(now.year + 15),
      helpText: 'License expiration date',
      builder: (context, child) => Theme(
        data: Theme.of(context).copyWith(
          colorScheme: const ColorScheme.light(
            primary: maroon,
            onPrimary: Colors.white,
            onSurface: darkMaroon,
          ),
        ),
        child: child!,
      ),
    );
    if (picked == null || !mounted) return;

    try {
      await RiderApiService.updateDocuments(licenseExpiry: picked);
      if (!mounted) return;
      setState(() => RiderProfile.licenseExpiry = picked);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('License expiration date saved.')),
      );
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not save expiration date: $error')),
      );
    }
  }

  Future<void> _replace(String type) async {
    final _DocumentSource? source = await showModalBottomSheet<_DocumentSource>(
      context: context,
      backgroundColor: cream,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt_outlined, color: darkMaroon),
              title: const Text('Take Photo'),
              onTap: () => Navigator.pop(ctx, _DocumentSource.camera),
            ),
            ListTile(
              leading: const Icon(
                Icons.photo_library_outlined,
                color: darkMaroon,
              ),
              title: const Text('Choose from Gallery'),
              onTap: () => Navigator.pop(ctx, _DocumentSource.gallery),
            ),
            ListTile(
              leading: const Icon(Icons.insert_drive_file_outlined, color: darkMaroon),
              title: const Text('Choose PDF or document file'),
              onTap: () => Navigator.pop(ctx, _DocumentSource.file),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;

    File? file;
    if (source == _DocumentSource.file) {
      try {
        final PlatformFile? picked = await FilePicker.pickFile(
          type: FileType.custom,
          allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
        );
        if (picked == null || picked.path == null || picked.path!.isEmpty) {
          return;
        }
        file = File(picked.path!);
      } catch (error) {
        _showMessage('Could not select the document: $error');
        return;
      }
    } else {
      final XFile? image = await _picker.pickImage(
        source: source == _DocumentSource.camera
            ? ImageSource.camera
            : ImageSource.gallery,
        imageQuality: 80,
      );
      if (image == null) return;
      file = File(image.path);
    }
    final selectedFile = file;
    if (!mounted || !await selectedFile.exists()) return;
    final fileSize = await selectedFile.length();
    if (!mounted) return;
    if (fileSize > 5 * 1024 * 1024) {
      _showMessage('The file must be 5 MB or smaller.');
      return;
    }

    try {
      await RiderApiService.updateDocuments(
        driverLicense: type == 'license' ? selectedFile : null,
        orDocument: type == 'or' ? selectedFile : null,
        crDocument: type == 'cr' ? selectedFile : null,
      );
      if (!mounted) return;
      setState(() {
        switch (type) {
          case 'license':
            RiderProfile.localDriverLicenseFile = selectedFile;
            _licenseFile = selectedFile;
            break;
          case 'or':
            RiderProfile.localOrFile = selectedFile;
            _orFile = selectedFile;
            break;
          case 'cr':
            RiderProfile.localCrFile = selectedFile;
            _crFile = selectedFile;
            break;
        }
      });
      _showMessage('Document uploaded successfully.');
    } catch (error) {
      if (!mounted) return;
      _showMessage('Could not upload document: $error');
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _viewDocument(File? file) async {
    if (file == null || !file.existsSync()) return;
    if (_isImage(file)) {
      await showDialog<void>(
        context: context,
        builder: (context) => Dialog(
          child: InteractiveViewer(child: Image.file(file, fit: BoxFit.contain)),
        ),
      );
      return;
    }
    await OpenFilex.open(file.path);
  }

  @override
  Widget build(BuildContext context) {
    final DateTime? expiry = RiderProfile.licenseExpiry;

    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: cream,
        elevation: 0,
        foregroundColor: darkMaroon,
        title: const Text(
          'My Documents',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 17),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
        children: [
          if (_isLoadingDocuments)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 24),
              child: Center(child: CircularProgressIndicator()),
            ),
          _docCard(
            title: "Driver's License",
            file: _licenseFile,
            icon: Icons.badge_outlined,
            onTap: () => _replace('license'),
            onView: () => _viewDocument(_licenseFile),
            extra: _expiryField(expiry),
          ),
          const SizedBox(height: 14),
          _docCard(
            title: 'Official Receipt (OR)',
            file: _orFile,
            icon: Icons.description_outlined,
            onTap: () => _replace('or'),
            onView: () => _viewDocument(_orFile),
          ),
          const SizedBox(height: 14),
          _docCard(
            title: 'Certificate of Registration (CR)',
            file: _crFile,
            icon: Icons.article_outlined,
            onTap: () => _replace('cr'),
            onView: () => _viewDocument(_crFile),
          ),
        ],
      ),
    );
  }

  Widget _expiryField(DateTime? expiry) {
    final DateTime now = DateTime.now();
    final bool expired = expiry != null && expiry.isBefore(now);
    final bool soon =
        expiry != null && !expired && expiry.difference(now).inDays <= 30;

    final Color statusColor = expired
        ? Colors.red
        : (soon ? Colors.orange : Colors.green);
    final String? status = expiry == null
        ? null
        : expired
        ? 'Expired'
        : soon
        ? 'Expiring soon'
        : 'Valid';

    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: InkWell(
        onTap: _pickExpiry,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          height: 54,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          decoration: BoxDecoration(
            color: lightBeige.withValues(alpha: 0.30),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            children: [
              const Icon(Icons.event_outlined, color: maroon, size: 20),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  expiry == null ? 'License expiration date' : _fmt(expiry),
                  style: TextStyle(
                    color: expiry == null ? brownBeige : darkMaroon,
                    fontSize: 12,
                    fontWeight: expiry == null
                        ? FontWeight.normal
                        : FontWeight.w600,
                  ),
                ),
              ),
              if (status != null)
                Text(
                  status,
                  style: TextStyle(
                    color: statusColor,
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              const SizedBox(width: 8),
              const Icon(
                Icons.calendar_month_outlined,
                color: maroon,
                size: 20,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _docCard({
    required String title,
    required File? file,
    required IconData icon,
    required VoidCallback onTap,
    required VoidCallback onView,
    Widget? extra,
  }) {
    final bool uploaded = file != null;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: uploaded ? Colors.green.withValues(alpha: 0.5) : lightBeige,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: maroon, size: 20),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              TextButton(
                onPressed: uploaded ? onView : onTap,
                child: Text(
                  uploaded ? 'View file' : 'Upload',
                  style: const TextStyle(
                    color: maroon,
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: uploaded && _isImage(file)
                ? Image.file(
                    file,
                    height: 160,
                    width: double.infinity,
                    fit: BoxFit.cover,
                  )
                : Container(
                    height: 100,
                    width: double.infinity,
                    color: lightBeige.withValues(alpha: 0.4),
                    child: Center(
                      child: Text(
                        uploaded ? file.path.split(RegExp(r'[/\\]')).last : 'No file uploaded',
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: brownBeige, fontSize: 11),
                      ),
                    ),
                  ),
          ),
          if (uploaded)
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(onPressed: onTap, child: const Text('Replace')),
            ),
          ?extra,
        ],
      ),
    );
  }

  bool _isImage(File? file) {
    if (file == null) return false;
    final separator = file.path.lastIndexOf('.');
    if (separator < 0) return false;
    final extension = file.path.substring(separator).toLowerCase();
    return ['.jpg', '.jpeg', '.png'].contains(extension);
  }
}

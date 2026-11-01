import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_application_1/models/rider_profile.dart';
import 'package:image_picker/image_picker.dart';

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
    if (picked == null) return;
    setState(() => RiderProfile.licenseExpiry = picked);
  }

  Future<void> _replace(String type) async {
    final ImageSource? source = await showModalBottomSheet<ImageSource>(
      context: context,
      backgroundColor: cream,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt_outlined, color: darkMaroon),
              title: const Text('Take Photo'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(
                Icons.photo_library_outlined,
                color: darkMaroon,
              ),
              title: const Text('Choose from Gallery'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;

    final XFile? image = await _picker.pickImage(
      source: source,
      imageQuality: 80,
    );
    if (image == null || !mounted) return;

    setState(() {
      final File f = File(image.path);
      switch (type) {
        case 'license':
          RiderProfile.localDriverLicenseFile = f;
        case 'or':
          RiderProfile.localOrFile = f;
        case 'cr':
          RiderProfile.localCrFile = f;
      }
    });
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
          _docCard(
            title: "Driver's License",
            file: RiderProfile.localDriverLicenseFile,
            icon: Icons.badge_outlined,
            onTap: () => _replace('license'),
            extra: _expiryField(expiry),
          ),
          const SizedBox(height: 14),
          _docCard(
            title: 'Official Receipt (OR)',
            file: RiderProfile.localOrFile,
            icon: Icons.description_outlined,
            onTap: () => _replace('or'),
          ),
          const SizedBox(height: 14),
          _docCard(
            title: 'Certificate of Registration (CR)',
            file: RiderProfile.localCrFile,
            icon: Icons.article_outlined,
            onTap: () => _replace('cr'),
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
                onPressed: onTap,
                child: Text(
                  uploaded ? 'Replace' : 'Upload',
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
            child: uploaded
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
                    child: const Center(
                      child: Text(
                        'No file uploaded',
                        style: TextStyle(color: brownBeige, fontSize: 11),
                      ),
                    ),
                  ),
          ),
          if (extra != null) extra,
        ],
      ),
    );
  }
}

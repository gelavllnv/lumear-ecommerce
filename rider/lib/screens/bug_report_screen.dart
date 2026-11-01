import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../models/bug_report.dart';
import '../services/bug_report_service.dart';

class BugReportScreen extends StatefulWidget {
  final String affectedScreen;

  const BugReportScreen({super.key, this.affectedScreen = 'Help & Support'});

  @override
  State<BugReportScreen> createState() => _BugReportScreenState();
}

class _BugReportScreenState extends State<BugReportScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color cream = Color(0xFFE0CFC2);
  static const Color background = Color(0xFFF7F3F1);
  static const Color borderColor = Color(0xFFE8DDDA);
  static const Color darkText = Color(0xFF3F2927);

  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();

  final TextEditingController _descriptionController = TextEditingController();

  final TextEditingController _expectedResultController =
      TextEditingController();

  final ImagePicker _imagePicker = ImagePicker();

  final List<String> _evidencePaths = [];

  bool _isSubmitting = false;

  // ============================================================
  // PROTOTYPE RIDER INFORMATION
  // Later kunin natin ito sa logged-in rider/session.
  // ============================================================
  final String _riderId = 'RDR-001';
  final String _riderName = 'Lumear Rider';
  final String _riderEmail = 'rider@lumear.test';

  final String _appVersion = '1.0.0';
  final String _deviceInfo = 'Android Device';

  @override
  void dispose() {
    _descriptionController.dispose();
    _expectedResultController.dispose();

    super.dispose();
  }

  // ============================================================
  // CAMERA
  // ============================================================
  Future<void> _takePhoto() async {
    try {
      final XFile? image = await _imagePicker.pickImage(
        source: ImageSource.camera,
        imageQuality: 80,
        maxWidth: 1600,
      );

      if (image == null || !mounted) {
        return;
      }

      setState(() {
        _evidencePaths.add(image.path);
      });
    } catch (error) {
      _showSnackBar('Unable to open the camera.');
    }
  }

  // ============================================================
  // GALLERY
  // ============================================================
  Future<void> _pickFromGallery() async {
    try {
      final List<XFile> images = await _imagePicker.pickMultiImage(
        imageQuality: 80,
        maxWidth: 1600,
      );

      if (images.isEmpty || !mounted) {
        return;
      }

      setState(() {
        for (final XFile image in images) {
          if (_evidencePaths.length >= 5) {
            break;
          }

          _evidencePaths.add(image.path);
        }
      });

      if (images.length + _evidencePaths.length > 5) {
        _showSnackBar('You can attach up to 5 screenshots or photos.');
      }
    } catch (error) {
      _showSnackBar('Unable to open the gallery.');
    }
  }

  // ============================================================
  // ADD EVIDENCE
  // ============================================================
  void _showEvidenceOptions() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return SafeArea(
          child: Container(
            margin: const EdgeInsets.all(12),
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(26),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(20),
                  ),
                ),

                const SizedBox(height: 20),

                const Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Add Evidence',
                    style: TextStyle(
                      color: darkMaroon,
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),

                const SizedBox(height: 6),

                const Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Add a screenshot or photo showing the bug.',
                    style: TextStyle(fontSize: 12, color: Colors.grey),
                  ),
                ),

                const SizedBox(height: 20),

                Row(
                  children: [
                    Expanded(
                      child: _evidenceOption(
                        icon: Icons.camera_alt_rounded,
                        label: 'Camera',
                        onTap: () {
                          Navigator.pop(sheetContext);

                          _takePhoto();
                        },
                      ),
                    ),

                    const SizedBox(width: 12),

                    Expanded(
                      child: _evidenceOption(
                        icon: Icons.photo_library_rounded,
                        label: 'Gallery',
                        onTap: () {
                          Navigator.pop(sheetContext);

                          _pickFromGallery();
                        },
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _evidenceOption({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 20),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: borderColor),
        ),
        child: Column(
          children: [
            CircleAvatar(
              radius: 25,
              backgroundColor: cream,
              child: Icon(icon, color: darkMaroon, size: 25),
            ),

            const SizedBox(height: 10),

            Text(
              label,
              style: const TextStyle(
                color: darkMaroon,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // REMOVE EVIDENCE
  // ============================================================
  void _removeEvidence(int index) {
    setState(() {
      _evidencePaths.removeAt(index);
    });
  }

  // ============================================================
  // SUBMIT
  // ============================================================
  Future<void> _submitReport() async {
    if (_isSubmitting) {
      return;
    }

    final bool valid = _formKey.currentState?.validate() ?? false;

    if (!valid) {
      return;
    }

    if (_evidencePaths.isEmpty) {
      _showSnackBar('Please add at least one screenshot or photo.');

      return;
    }

    setState(() {
      _isSubmitting = true;
    });

    await Future.delayed(const Duration(milliseconds: 650));

    if (!mounted) {
      return;
    }

    final BugReport report = BugReportService.instance.submitReport(
      riderId: _riderId,
      riderName: _riderName,
      riderEmail: _riderEmail,
      affectedScreen: widget.affectedScreen,
      appVersion: _appVersion,
      deviceInfo: _deviceInfo,
      description: _descriptionController.text.trim(),
      expectedResult: _expectedResultController.text.trim(),
      evidencePaths: _evidencePaths,
    );

    setState(() {
      _isSubmitting = false;
    });

    await _showSuccessDialog(report);
  }

  // ============================================================
  // SUCCESS
  // ============================================================
  Future<void> _showSuccessDialog(BugReport report) async {
    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24),
          ),
          contentPadding: const EdgeInsets.all(24),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 66,
                height: 66,
                decoration: const BoxDecoration(
                  color: cream,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.check_rounded,
                  color: darkMaroon,
                  size: 36,
                ),
              ),

              const SizedBox(height: 18),

              const Text(
                'Bug Report Submitted',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: darkMaroon,
                  fontWeight: FontWeight.w800,
                  fontSize: 19,
                ),
              ),

              const SizedBox(height: 8),

              const Text(
                'Your report has been sent for admin review.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Colors.grey,
                  fontSize: 12.5,
                  height: 1.4,
                ),
              ),

              const SizedBox(height: 20),

              _successInfo('Bug ID', report.id),

              const SizedBox(height: 8),

              _successInfo('Status', report.status),

              const SizedBox(height: 22),

              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.pop(dialogContext);
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: darkMaroon,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    padding: const EdgeInsets.symmetric(vertical: 13),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: const Text(
                    'Done',
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );

    if (!mounted) {
      return;
    }

    Navigator.pop(context);
  }

  Widget _successInfo(String label, String value) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Text(label, style: const TextStyle(color: Colors.grey, fontSize: 12)),

          const Spacer(),

          Text(
            value,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // DATE
  // ============================================================
  String _formatDateTime(DateTime dateTime) {
    const List<String> months = [
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

    int hour = dateTime.hour;

    final String period = hour >= 12 ? 'PM' : 'AM';

    if (hour == 0) {
      hour = 12;
    } else if (hour > 12) {
      hour -= 12;
    }

    final String minute = dateTime.minute.toString().padLeft(2, '0');

    return '${months[dateTime.month - 1]} '
        '${dateTime.day}, ${dateTime.year} • '
        '$hour:$minute $period';
  }

  // ============================================================
  // SNACKBAR
  // ============================================================
  void _showSnackBar(String message) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(behavior: SnackBarBehavior.floating, content: Text(message)),
    );
  }

  // ============================================================
  // BUILD
  // ============================================================
  @override
  Widget build(BuildContext context) {
    final DateTime now = DateTime.now();

    return Scaffold(
      backgroundColor: background,
      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        titleSpacing: 0,
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Report a Bug',
              style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700),
            ),
            Text(
              'Lumear Rider App',
              style: TextStyle(fontSize: 10, color: cream),
            ),
          ],
        ),
      ),

      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),
          children: [
            // ==================================================
            // HEADER
            // ==================================================
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: cream,
                borderRadius: BorderRadius.circular(18),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.bug_report_rounded, color: darkMaroon, size: 28),

                  SizedBox(width: 12),

                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Help us improve Lumear',
                          style: TextStyle(
                            color: darkMaroon,
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                          ),
                        ),

                        SizedBox(height: 4),

                        Text(
                          'Provide a screenshot and describe '
                          'what happened. Your rider and system '
                          'information will be included automatically.',
                          style: TextStyle(
                            color: darkText,
                            fontSize: 11.5,
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 22),

            _sectionTitle(
              icon: Icons.person_outline_rounded,
              title: 'Rider Information',
              subtitle: 'Automatically included with this report',
            ),

            const SizedBox(height: 12),

            _infoCard(
              children: [
                _infoRow('Rider ID', _riderId),
                _divider(),
                _infoRow('Name', _riderName),
                _divider(),
                _infoRow('Email', _riderEmail),
              ],
            ),

            const SizedBox(height: 22),

            _sectionTitle(
              icon: Icons.phone_android_rounded,
              title: 'System Information',
              subtitle: 'Included automatically for troubleshooting',
            ),

            const SizedBox(height: 12),

            _infoCard(
              children: [
                _infoRow('Affected Screen', widget.affectedScreen),
                _divider(),
                _infoRow('App Version', _appVersion),
                _divider(),
                _infoRow('Device', _deviceInfo),
                _divider(),
                _infoRow('Reported', _formatDateTime(now)),
              ],
            ),

            const SizedBox(height: 24),

            _sectionTitle(
              icon: Icons.edit_note_rounded,
              title: 'Bug Details',
              subtitle: 'Tell us what happened',
            ),

            const SizedBox(height: 12),

            _fieldLabel('What happened?', required: true),

            const SizedBox(height: 7),

            TextFormField(
              controller: _descriptionController,
              minLines: 4,
              maxLines: 7,
              textCapitalization: TextCapitalization.sentences,
              decoration: _inputDecoration(
                'Example: The Start Delivery button '
                'does not appear after scanning the QR code.',
              ),
              validator: (value) {
                if (value == null || value.trim().length < 10) {
                  return 'Please describe the problem.';
                }

                return null;
              },
            ),

            const SizedBox(height: 18),

            _fieldLabel('What did you expect to happen?', required: true),

            const SizedBox(height: 7),

            TextFormField(
              controller: _expectedResultController,
              minLines: 3,
              maxLines: 5,
              textCapitalization: TextCapitalization.sentences,
              decoration: _inputDecoration(
                'Example: The Start Delivery button '
                'should appear after successful QR verification.',
              ),
              validator: (value) {
                if (value == null || value.trim().length < 5) {
                  return 'Please tell us the expected result.';
                }

                return null;
              },
            ),

            const SizedBox(height: 24),

            _sectionTitle(
              icon: Icons.image_outlined,
              title: 'Evidence',
              subtitle: 'At least one screenshot or photo is required',
            ),

            const SizedBox(height: 12),

            if (_evidencePaths.isEmpty)
              InkWell(
                onTap: _showEvidenceOptions,
                borderRadius: BorderRadius.circular(18),
                child: Container(
                  height: 145,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(
                      color: brownBeige.withValues(alpha: 0.45),
                      width: 1.3,
                    ),
                  ),
                  child: const Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      CircleAvatar(
                        radius: 25,
                        backgroundColor: cream,
                        child: Icon(
                          Icons.add_a_photo_outlined,
                          color: darkMaroon,
                        ),
                      ),

                      SizedBox(height: 10),

                      Text(
                        'Add Screenshot / Photo',
                        style: TextStyle(
                          color: darkMaroon,
                          fontWeight: FontWeight.w700,
                        ),
                      ),

                      SizedBox(height: 3),

                      Text(
                        'Tap to use camera or gallery',
                        style: TextStyle(color: Colors.grey, fontSize: 10.5),
                      ),
                    ],
                  ),
                ),
              ),

            if (_evidencePaths.isNotEmpty) ...[
              SizedBox(
                height: 125,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: _evidencePaths.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 10),
                  itemBuilder: (context, index) {
                    return Stack(
                      clipBehavior: Clip.none,
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(15),
                          child: Image.file(
                            File(_evidencePaths[index]),
                            width: 125,
                            height: 125,
                            fit: BoxFit.cover,
                          ),
                        ),

                        Positioned(
                          top: 5,
                          right: 5,
                          child: GestureDetector(
                            onTap: () {
                              _removeEvidence(index);
                            },
                            child: Container(
                              width: 27,
                              height: 27,
                              decoration: const BoxDecoration(
                                color: darkMaroon,
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(
                                Icons.close_rounded,
                                color: Colors.white,
                                size: 17,
                              ),
                            ),
                          ),
                        ),
                      ],
                    );
                  },
                ),
              ),

              const SizedBox(height: 10),

              if (_evidencePaths.length < 5)
                OutlinedButton.icon(
                  onPressed: _showEvidenceOptions,
                  style: OutlinedButton.styleFrom(
                    foregroundColor: darkMaroon,
                    side: const BorderSide(color: brownBeige),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  icon: const Icon(Icons.add_photo_alternate_outlined),
                  label: const Text('Add Another'),
                ),
            ],

            const SizedBox(height: 26),

            SizedBox(
              height: 54,
              child: ElevatedButton(
                onPressed: _isSubmitting ? null : _submitReport,
                style: ElevatedButton.styleFrom(
                  backgroundColor: darkMaroon,
                  foregroundColor: Colors.white,
                  disabledBackgroundColor: darkMaroon.withValues(alpha: 0.55),
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
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
                          Icon(Icons.send_rounded, size: 19),
                          SizedBox(width: 9),
                          Text(
                            'Submit Bug Report',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
              ),
            ),

            const SizedBox(height: 10),

            const Text(
              'Reports are initially marked as Pending '
              'until reviewed by a Lumear administrator.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey, fontSize: 10.5, height: 1.4),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // UI HELPERS
  // ============================================================
  Widget _sectionTitle({
    required IconData icon,
    required String title,
    required String subtitle,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: darkMaroon, size: 20),

        const SizedBox(width: 9),

        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  color: darkMaroon,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
              Text(
                subtitle,
                style: const TextStyle(color: Colors.grey, fontSize: 10.5),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _infoCard({required List<Widget> children}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: borderColor),
      ),
      child: Column(children: children),
    );
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: const TextStyle(color: Colors.grey, fontSize: 11),
            ),
          ),

          const SizedBox(width: 10),

          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(
                color: darkText,
                fontSize: 11.5,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _divider() {
    return const Divider(height: 1, color: borderColor);
  }

  Widget _fieldLabel(String text, {bool required = false}) {
    return Row(
      children: [
        Text(
          text,
          style: const TextStyle(
            color: darkText,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),

        if (required)
          const Text(
            ' *',
            style: TextStyle(color: maroon, fontWeight: FontWeight.w800),
          ),
      ],
    );
  }

  InputDecoration _inputDecoration(String hint) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: Colors.grey, fontSize: 11, height: 1.4),
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.all(14),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: borderColor),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: darkMaroon, width: 1.4),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Colors.redAccent),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: Colors.redAccent),
      ),
    );
  }
}

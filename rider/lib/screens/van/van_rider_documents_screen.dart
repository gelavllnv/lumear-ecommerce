import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_application_1/models/rider_profile.dart';
import 'package:open_filex/open_filex.dart';

class VanRiderDocumentsScreen extends StatefulWidget {
  final String? driverLicensePath;
  final String? vehicleOrPath;
  final String? vehicleCrPath;
  final DateTime? initialLicenseExpiry;

  final Future<void> Function(
    String? driverLicensePath,
    String? vehicleOrPath,
    String? vehicleCrPath,
    DateTime? licenseExpiry,
  )?
  onSave;

  const VanRiderDocumentsScreen({
    super.key,
    this.driverLicensePath,
    this.vehicleOrPath,
    this.vehicleCrPath,
    this.initialLicenseExpiry,
    this.onSave,
  });

  @override
  State<VanRiderDocumentsScreen> createState() =>
      _VanRiderDocumentsScreenState();
}

class _VanRiderDocumentsScreenState extends State<VanRiderDocumentsScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color cardBorder = Color(0xFFEDE1D8);
  static const Color successGreen = Color(0xFF287544);
  static const Color warningAmber = Color(0xFF9B6A00);
  static const Color dangerRed = Color(0xFFB3261E);

  String? _driverLicensePath;
  String? _vehicleOrPath;
  String? _vehicleCrPath;
  DateTime? _licenseExpiry;

  bool _isSaving = false;
  String _approvalStatus = 'Not submitted';
  bool _hasUnsavedChanges = false;

  @override
  void initState() {
    super.initState();

    // Use the files uploaded during signup unless the caller passes its own.
    _driverLicensePath =
        widget.driverLicensePath ?? RiderProfile.localDriverLicenseFile?.path;
    _vehicleOrPath = widget.vehicleOrPath ?? RiderProfile.localOrFile?.path;
    _vehicleCrPath = widget.vehicleCrPath ?? RiderProfile.localCrFile?.path;
    _licenseExpiry = widget.initialLicenseExpiry;

    // These documents were already submitted at signup.
    if (_has(_driverLicensePath) &&
        _has(_vehicleOrPath) &&
        _has(_vehicleCrPath)) {
      _approvalStatus = 'Pending';
    }
  }

  // ---------------------------------------------------------------------------
  // STATE HELPERS
  // ---------------------------------------------------------------------------

  bool _has(String? path) => path != null && path.isNotEmpty;

  int get _completedCount => [
    _has(_driverLicensePath),
    _licenseExpiry != null,
    _has(_vehicleOrPath),
    _has(_vehicleCrPath),
  ].where((done) => done).length;

  static const int _requiredCount = 4;

  bool get _isComplete => _completedCount == _requiredCount;

  // Call inside setState whenever a document or the expiry date changes.
  void _markChanged() {
    _hasUnsavedChanges = true;

    if (_approvalStatus == 'Pending' ||
        _approvalStatus == 'Approved' ||
        _approvalStatus == 'Rejected') {
      _approvalStatus = 'Update required';
    }
  }

  DateTime _dateOnly(DateTime date) =>
      DateTime(date.year, date.month, date.day);

  // ---------------------------------------------------------------------------
  // FILE HELPERS
  // ---------------------------------------------------------------------------

  String _formatDate(DateTime date) {
    const months = [
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

    return '${months[date.month - 1]} ${date.day}, ${date.year}';
  }

  String _fileName(String path) {
    return path.split(Platform.pathSeparator).last;
  }

  String? _getExtension(String path) {
    final filename = _fileName(path);
    final dotIndex = filename.lastIndexOf('.');

    if (dotIndex < 0) return null;

    return filename.substring(dotIndex + 1).toLowerCase();
  }

  bool _isImage(String path) {
    final extension = _getExtension(path);
    return extension == 'png' || extension == 'jpg' || extension == 'jpeg';
  }

  bool _isPdf(String path) => _getExtension(path) == 'pdf';

  bool _isWord(String path) {
    final extension = _getExtension(path);
    return extension == 'doc' || extension == 'docx';
  }

  bool _isAcceptedLicense(String path) {
    return _isImage(path) || _isPdf(path);
  }

  // Images are accepted too, because the signup screen uploads OR/CR as photos.
  bool _isAcceptedVehicleDocument(String path) {
    return _isPdf(path) || _isWord(path) || _isImage(path);
  }

  List<String> _allowedExtensions(String documentType) {
    if (documentType == 'license') {
      return ['png', 'jpg', 'jpeg', 'pdf'];
    }

    return ['png', 'jpg', 'jpeg', 'pdf', 'doc', 'docx'];
  }

  bool _isAcceptedForType(String path, String documentType) {
    if (documentType == 'license') {
      return _isAcceptedLicense(path);
    }

    return _isAcceptedVehicleDocument(path);
  }

  String _formatNotice(String documentType) {
    if (documentType == 'license') {
      return 'Accepted formats: PNG, JPG, JPEG, and PDF.';
    }

    return 'Accepted formats: PNG, JPG, JPEG, PDF, DOC, and DOCX.';
  }

  String _fileSize(String path) {
    try {
      final bytes = File(path).lengthSync();

      if (bytes < 1024) return '$bytes B';
      if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(1)} KB';
      return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
    } catch (_) {
      return '';
    }
  }

  IconData _fileIcon(String path) {
    if (_isImage(path)) return Icons.image_outlined;
    if (_isPdf(path)) return Icons.picture_as_pdf_outlined;
    return Icons.description_outlined;
  }

  Color _fileColor(String path) {
    if (_isImage(path)) return const Color(0xFF245CA6);
    if (_isPdf(path)) return dangerRed;
    return const Color(0xFF2B579A);
  }

  // ---------------------------------------------------------------------------
  // ACTIONS
  // ---------------------------------------------------------------------------

  Future<void> _pickDocument(String documentType) async {
    try {
      final result = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: _allowedExtensions(documentType),
      );

      if (!mounted || result.isEmpty) {
        return;
      }

      final pickedFile = result.single;
      final path = pickedFile.path;
      final extension = pickedFile.extension?.toLowerCase();

      if (extension == null ||
          !_allowedExtensions(documentType).contains(extension)) {
        _showMessage(_formatNotice(documentType));
        return;
      }

      if (path == null || !_isAcceptedForType(path, documentType)) {
        _showMessage(
          'Unable to access this file or its format is not supported.',
        );
        return;
      }

      if (!await File(path).exists()) {
        if (mounted) {
          _showMessage('The selected file could not be accessed.');
        }
        return;
      }

      if (!mounted) return;

      setState(() {
        switch (documentType) {
          case 'license':
            _driverLicensePath = path;
            break;
          case 'or':
            _vehicleOrPath = path;
            break;
          case 'cr':
            _vehicleCrPath = path;
            break;
        }
        _markChanged();
      });

      _showMessage('File selected successfully.');
    } catch (e) {
      if (mounted) {
        _showMessage('Unable to select file: $e');
      }
    }
  }

  Future<void> _viewDocument(
    String path,
    String documentType,
    String title,
  ) async {
    if (!_isAcceptedForType(path, documentType)) {
      _showMessage(_formatNotice(documentType));
      return;
    }

    final file = File(path);

    if (!await file.exists()) {
      if (mounted) {
        _showMessage(
          'The file is no longer available. Please upload it again.',
        );
      }
      return;
    }

    if (!mounted) return;

    if (_isImage(path)) {
      await showDialog<void>(
        context: context,
        builder: (dialogContext) {
          return Dialog(
            backgroundColor: Colors.white,
            insetPadding: const EdgeInsets.all(18),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(18),
            ),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          title,
                          style: const TextStyle(
                            color: darkMaroon,
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                          ),
                        ),
                      ),
                      IconButton(
                        onPressed: () => Navigator.pop(dialogContext),
                        icon: const Icon(Icons.close),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  ConstrainedBox(
                    constraints: BoxConstraints(
                      maxHeight:
                          MediaQuery.of(dialogContext).size.height * 0.65,
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: InteractiveViewer(
                        child: Image.file(
                          file,
                          fit: BoxFit.contain,
                          errorBuilder: (context, error, stackTrace) =>
                              const Padding(
                                padding: EdgeInsets.all(20),
                                child: Text('Unable to display this image.'),
                              ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      );
      return;
    }

    final result = await OpenFilex.open(path);

    if (!mounted) return;

    if (result.type != ResultType.done) {
      _showMessage(
        _isPdf(path)
            ? 'No compatible PDF viewer was found on this device.'
            : 'No compatible Word document viewer was found on this device.',
      );
    }
  }

  Future<void> _selectExpiryDate() async {
    final now = DateTime.now();

    final picked = await showDatePicker(
      context: context,
      initialDate: _licenseExpiry ?? DateTime(now.year + 1, now.month, now.day),
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
      helpText: 'SELECT LICENSE EXPIRATION DATE',
      confirmText: 'SELECT',
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: darkMaroon,
              onPrimary: Colors.white,
              surface: Colors.white,
              onSurface: darkMaroon,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null && mounted) {
      setState(() {
        _licenseExpiry = picked;
        _markChanged();
      });
    }
  }

  void _clearExpiryDate() {
    setState(() {
      _licenseExpiry = null;
      _markChanged();
    });
  }

  Future<void> _saveDocuments() async {
    if (_isSaving) return;

    if (!_isComplete) {
      _showMessage(
        'Please upload all required documents and set the license '
        'expiration date before submitting.',
      );
      return;
    }

    setState(() => _isSaving = true);

    try {
      if (widget.onSave != null) {
        await widget.onSave!(
          _driverLicensePath,
          _vehicleOrPath,
          _vehicleCrPath,
          _licenseExpiry,
        );
      }

      // Keep the in-memory profile in sync with the latest files.
      RiderProfile.localDriverLicenseFile = File(_driverLicensePath!);
      RiderProfile.localOrFile = File(_vehicleOrPath!);
      RiderProfile.localCrFile = File(_vehicleCrPath!);

      if (!mounted) return;

      setState(() {
        _approvalStatus = 'Pending';
        _hasUnsavedChanges = false;
      });

      _showMessage(
        widget.onSave == null
            ? 'Update marked as pending in this screen only. Permanent saving is not connected yet.'
            : 'Documents updated successfully.',
      );
    } catch (e) {
      if (mounted) {
        _showMessage('Unable to save documents: $e');
      }
    } finally {
      if (mounted) {
        setState(() => _isSaving = false);
      }
    }
  }

  void _removeDocument(String documentType) {
    setState(() {
      switch (documentType) {
        case 'license':
          _driverLicensePath = null;
          break;
        case 'or':
          _vehicleOrPath = null;
          break;
        case 'cr':
          _vehicleCrPath = null;
          break;
      }
      _markChanged();
    });

    _showMessage('File removed from this screen.');
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: darkMaroon,
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  // ---------------------------------------------------------------------------
  // UI: SHARED PIECES
  // ---------------------------------------------------------------------------

  BoxDecoration get _cardDecoration => BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(18),
    border: Border.all(color: cardBorder),
  );

  Widget _sectionHeader(String title, String subtitle, IconData icon) {
    return Padding(
      padding: const EdgeInsets.only(top: 22, bottom: 12),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: maroon.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: maroon, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.bold,
                    color: darkMaroon,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: const TextStyle(fontSize: 12, color: brownBeige),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _pill(String label, Color color, {IconData? icon}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 12, color: color),
            const SizedBox(width: 4),
          ],
          Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  Widget _formatChip(String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: cream,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: brownBeige,
          fontSize: 10.5,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.3,
        ),
      ),
    );
  }

  List<String> _formatLabels(String documentType) {
    return _allowedExtensions(documentType)
        .map((extension) => extension.toUpperCase())
        .toList();
  }

  // ---------------------------------------------------------------------------
  // UI: OVERVIEW (PROGRESS + CHECKLIST)
  // ---------------------------------------------------------------------------

  Widget _buildOverviewCard() {
    final progress = _completedCount / _requiredCount;
    final progressColor = _isComplete ? successGreen : maroon;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: _cardDecoration,
      child: Row(
        children: [
          SizedBox(
            width: 76,
            height: 76,
            child: Stack(
              fit: StackFit.expand,
              children: [
                CircularProgressIndicator(
                  value: progress,
                  strokeWidth: 7,
                  strokeCap: StrokeCap.round,
                  backgroundColor: cream,
                  valueColor: AlwaysStoppedAnimation<Color>(progressColor),
                ),
                Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        '$_completedCount/$_requiredCount',
                        style: TextStyle(
                          color: progressColor,
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          height: 1,
                        ),
                      ),
                      const SizedBox(height: 2),
                      const Text(
                        'done',
                        style: TextStyle(color: brownBeige, fontSize: 10),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 18),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Verification Checklist',
                  style: TextStyle(
                    color: darkMaroon,
                    fontSize: 15,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 8),
                _checkRow("Driver's License", _has(_driverLicensePath)),
                _checkRow('License expiration date', _licenseExpiry != null),
                _checkRow('Official Receipt (OR)', _has(_vehicleOrPath)),
                _checkRow(
                  'Certificate of Registration (CR)',
                  _has(_vehicleCrPath),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _checkRow(String label, bool done) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2.5),
      child: Row(
        children: [
          Icon(
            done ? Icons.check_circle : Icons.radio_button_unchecked,
            size: 16,
            color: done ? successGreen : lightBeige,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: done ? darkMaroon : brownBeige,
                fontSize: 12,
                fontWeight: done ? FontWeight.w600 : FontWeight.w400,
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // UI: STATUS BANNER
  // ---------------------------------------------------------------------------

  Widget _buildStatusBanner() {
    late final Color color;
    late final IconData icon;
    late final String message;

    switch (_approvalStatus) {
      case 'Pending':
        color = warningAmber;
        icon = Icons.hourglass_top_rounded;
        message =
            'Your documents have been submitted and are waiting for review.';
        break;
      case 'Approved':
        color = successGreen;
        icon = Icons.verified_outlined;
        message = 'Your submitted documents have been marked as approved.';
        break;
      case 'Rejected':
        color = dangerRed;
        icon = Icons.cancel_outlined;
        message = 'Your submitted documents were rejected. Please review the feedback and update them.';
        break;
      case 'Update required':
        color = Colors.deepOrange.shade700;
        icon = Icons.edit_note_rounded;
        message = 'You changed a document. Select Update Documents to submit the latest version.';
        break;
      default:
        color = maroon;
        icon = Icons.assignment_outlined;
        message = 'Complete the checklist, then submit your documents for verification.';
    }

    return Container(
      margin: const EdgeInsets.only(top: 14),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withValues(alpha: 0.28)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(icon, color: color, size: 21),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Verification Status',
                        style: TextStyle(
                          color: darkMaroon,
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                        ),
                      ),
                    ),
                    _pill(_approvalStatus, color),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  message,
                  style: const TextStyle(
                    color: Colors.black54,
                    fontSize: 12,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // UI: DOCUMENT CARD
  // ---------------------------------------------------------------------------

  Widget _documentCard({
    required String title,
    required String subtitle,
    required String documentType,
    required String? path,
    required IconData icon,
  }) {
    final uploaded = _has(path);

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(16),
      decoration: _cardDecoration,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                height: 46,
                width: 46,
                decoration: BoxDecoration(
                  color: cream,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(icon, color: maroon, size: 24),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                        color: darkMaroon,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      style: const TextStyle(fontSize: 12, color: brownBeige),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              uploaded
                  ? _pill('Uploaded', successGreen, icon: Icons.check)
                  : _pill('Required', dangerRed),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: _formatLabels(documentType).map(_formatChip).toList(),
          ),
          const SizedBox(height: 14),
          if (uploaded)
            _buildFileSection(
              path: path!,
              documentType: documentType,
              title: title,
            )
          else
            _buildDropZone(documentType),
        ],
      ),
    );
  }

  Widget _buildDropZone(String documentType) {
    return InkWell(
      onTap: () => _pickDocument(documentType),
      borderRadius: BorderRadius.circular(14),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(vertical: 22),
        decoration: BoxDecoration(
          color: cream.withValues(alpha: 0.55),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: lightBeige, width: 1.2),
        ),
        child: const Column(
          children: [
            Icon(Icons.cloud_upload_outlined, color: maroon, size: 30),
            SizedBox(height: 8),
            Text(
              'Tap to upload file',
              style: TextStyle(
                color: darkMaroon,
                fontWeight: FontWeight.w700,
                fontSize: 13,
              ),
            ),
            SizedBox(height: 2),
            Text(
              'Choose a file from your device',
              style: TextStyle(color: brownBeige, fontSize: 11.5),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFileSection({
    required String path,
    required String documentType,
    required String title,
  }) {
    final size = _fileSize(path);
    final extension = (_getExtension(path) ?? 'file').toUpperCase();
    final fileColor = _fileColor(path);

    return Column(
      children: [
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: cream.withValues(alpha: 0.55),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: cardBorder),
          ),
          child: Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: SizedBox(
                  width: 46,
                  height: 46,
                  child: _isImage(path)
                      ? Image.file(
                          File(path),
                          fit: BoxFit.cover,
                          errorBuilder: (context, error, stackTrace) =>
                              _fileIconBox(path, fileColor),
                        )
                      : _fileIconBox(path, fileColor),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _fileName(path),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: darkMaroon,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      size.isEmpty ? extension : '$extension • $size',
                      style: const TextStyle(color: brownBeige, fontSize: 11.5),
                    ),
                  ],
                ),
              ),
              IconButton(
                tooltip: 'Remove file',
                onPressed: () => _removeDocument(documentType),
                icon: Icon(Icons.delete_outline, color: dangerRed),
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => _viewDocument(path, documentType, title),
                icon: const Icon(Icons.visibility_outlined, size: 18),
                label: const Text('View'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: maroon,
                  side: const BorderSide(color: maroon),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(11),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => _pickDocument(documentType),
                icon: const Icon(Icons.swap_horiz_rounded, size: 18),
                label: const Text('Replace'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: darkMaroon,
                  side: const BorderSide(color: lightBeige),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(11),
                  ),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _fileIconBox(String path, Color color) {
    return Container(
      color: color.withValues(alpha: 0.12),
      child: Icon(_fileIcon(path), color: color, size: 24),
    );
  }

  // ---------------------------------------------------------------------------
  // UI: EXPIRY DATE CARD
  // ---------------------------------------------------------------------------

  Widget _expiryDateCard() {
    final date = _licenseExpiry;
    final today = _dateOnly(DateTime.now());

    final daysLeft = date == null
        ? null
        : _dateOnly(date).difference(today).inDays;
    final expired = daysLeft != null && daysLeft < 0;
    final expiringSoon = daysLeft != null && !expired && daysLeft <= 30;

    Color accent = lightBeige;
    String badge = 'Required';
    Color badgeColor = dangerRed;
    String detail = 'Select the expiration date printed on your license.';

    if (daysLeft != null) {
      if (expired) {
        accent = dangerRed;
        badge = 'Expired';
        badgeColor = dangerRed;
        final ago = -daysLeft;
        detail =
            'Expired $ago ${ago == 1 ? 'day' : 'days'} ago. Please upload a renewed license.';
      } else if (expiringSoon) {
        accent = warningAmber;
        badge = 'Expiring soon';
        badgeColor = warningAmber;
        detail = daysLeft == 0
            ? 'Your license expires today.'
            : 'Expires in $daysLeft ${daysLeft == 1 ? 'day' : 'days'}.';
      } else {
        accent = successGreen;
        badge = 'Valid';
        badgeColor = successGreen;
        detail = 'Expires in $daysLeft days.';
      }
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(16),
      decoration: _cardDecoration,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                height: 46,
                width: 46,
                decoration: BoxDecoration(
                  color: cream,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: const Icon(
                  Icons.event_available_outlined,
                  color: maroon,
                  size: 24,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'License Expiration Date',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                        color: darkMaroon,
                      ),
                    ),
                    SizedBox(height: 3),
                    Text(
                      'As printed on your driver\'s license',
                      style: TextStyle(fontSize: 12, color: brownBeige),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              _pill(badge, badgeColor),
            ],
          ),
          const SizedBox(height: 14),
          InkWell(
            onTap: _selectExpiryDate,
            borderRadius: BorderRadius.circular(14),
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
              decoration: BoxDecoration(
                color: cream.withValues(alpha: 0.55),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: date == null
                      ? lightBeige
                      : accent.withValues(alpha: 0.6),
                ),
              ),
              child: Row(
                children: [
                  Icon(Icons.calendar_month_outlined, color: maroon, size: 22),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      date == null
                          ? 'Choose expiration date'
                          : _formatDate(date),
                      style: TextStyle(
                        color: date == null ? brownBeige : darkMaroon,
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  const Icon(
                    Icons.edit_calendar_outlined,
                    color: brownBeige,
                    size: 20,
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Icon(
                expired
                    ? Icons.error_outline
                    : expiringSoon
                    ? Icons.warning_amber_rounded
                    : Icons.info_outline,
                size: 15,
                color: date == null ? brownBeige : accent,
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  detail,
                  style: TextStyle(
                    color: date == null ? brownBeige : accent,
                    fontSize: 11.5,
                    height: 1.3,
                  ),
                ),
              ),
              if (date != null)
                TextButton(
                  onPressed: _clearExpiryDate,
                  style: TextButton.styleFrom(
                    foregroundColor: maroon,
                    minimumSize: const Size(0, 30),
                    padding: const EdgeInsets.symmetric(horizontal: 8),
                  ),
                  child: const Text('Clear', style: TextStyle(fontSize: 12)),
                ),
            ],
          ),
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // UI: BOTTOM BAR
  // ---------------------------------------------------------------------------

  Widget _buildBottomBar() {
    // The rider can submit when something changed. Documents from signup
    // are already Pending, so they only need re-submitting after a change.
    // The license expiry date is not collected at signup, so the rider can
    // also submit once to add it.
    final canSubmit =
        !_isSaving &&
        (_hasUnsavedChanges || _approvalStatus == 'Not submitted');

    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: cardBorder)),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (_hasUnsavedChanges)
                const Padding(
                  padding: EdgeInsets.only(bottom: 8),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.circle, size: 8, color: Colors.deepOrange),
                      SizedBox(width: 6),
                      Text(
                        'You have unsaved changes',
                        style: TextStyle(
                          color: Colors.deepOrange,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton.icon(
                  onPressed: canSubmit ? _saveDocuments : null,
                  icon: _isSaving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.cloud_done_outlined),
                  label: Text(
                    _isSaving ? 'Submitting Update...' : 'Update Documents',
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: darkMaroon,
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: lightBeige,
                    disabledForegroundColor: Colors.white,
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // BUILD
  // ---------------------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          'My Documents',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
      ),
      bottomNavigationBar: _buildBottomBar(),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 24),
        children: [
          const Text(
            'Required Documents',
            style: TextStyle(
              color: darkMaroon,
              fontSize: 22,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Upload your documents so Lumear Logistics can verify you as a '
            'van rider.',
            style: TextStyle(color: brownBeige, fontSize: 13, height: 1.4),
          ),
          const SizedBox(height: 16),
          _buildOverviewCard(),
          _buildStatusBanner(),

          _sectionHeader(
            "Driver's License",
            'Front photo or scan of your license',
            Icons.badge_outlined,
          ),
          _documentCard(
            title: "Driver's License",
            subtitle: 'Valid government-issued license',
            documentType: 'license',
            path: _driverLicensePath,
            icon: Icons.credit_card,
          ),
          _expiryDateCard(),

          _sectionHeader(
            'Vehicle Documents',
            'Registration papers of your delivery van',
            Icons.local_shipping_outlined,
          ),
          _documentCard(
            title: 'Official Receipt (OR)',
            subtitle: 'Proof of vehicle registration payment',
            documentType: 'or',
            path: _vehicleOrPath,
            icon: Icons.receipt_long_outlined,
          ),
          _documentCard(
            title: 'Certificate of Registration (CR)',
            subtitle: 'Vehicle ownership and registration details',
            documentType: 'cr',
            path: _vehicleCrPath,
            icon: Icons.description_outlined,
          ),
        ],
      ),
    );
  }
}

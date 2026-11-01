import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:open_filex/open_filex.dart';

import '../models/rider_document_store.dart';
import '../models/rider_profile.dart';

class DocumentsScreen extends StatefulWidget {
  const DocumentsScreen({super.key});

  @override
  State<DocumentsScreen> createState() => _DocumentsScreenState();
}

class _DocumentsScreenState extends State<DocumentsScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);

  // ---------------------------------------------------------------------------
  // ALLOWED FILE TYPES (same rules as the Sign Up screen)
  //   Driver's License -> PNG / JPG / JPEG
  //   OR and CR        -> PDF / DOC / DOCX
  // ---------------------------------------------------------------------------
  static const List<String> _licenseExtensions = ['png', 'jpg', 'jpeg'];
  static const List<String> _documentExtensions = ['pdf', 'doc', 'docx'];

  final ImagePicker _picker = ImagePicker();

  // Documents the rider uploaded during sign up (permanent storage fallback).
  File? _savedLicense;
  File? _savedOr;
  File? _savedCr;

  // New replacement drafts (not yet submitted).
  File? _draftLicense;
  File? _draftOr;
  File? _draftCr;

  // Submitted replacements waiting for Logistics approval.
  File? _pendingLicense;
  File? _pendingOr;
  File? _pendingCr;

  // Selected expiration date for the driver's license.
  DateTime? _licenseExpiryDate;
  bool _licenseExpiryChanged = false;

  bool _licenseDraftSaved = false;
  bool _orDraftSaved = false;
  bool _crDraftSaved = false;

  bool _licensePendingApproval = false;
  bool _orPendingApproval = false;
  bool _crPendingApproval = false;

  bool _isSavingDraft = false;
  bool _isSubmittingUpdate = false;

  @override
  void initState() {
    super.initState();
    _loadSignupDocuments();
  }

  // Loads the documents saved during sign up so they appear here.
  Future<void> _loadSignupDocuments() async {
    final String email = RiderProfile.email;

    final license = await RiderDocumentStore.load(
      email,
      RiderDocumentStore.license,
    );
    final or = await RiderDocumentStore.load(email, RiderDocumentStore.or);
    final cr = await RiderDocumentStore.load(email, RiderDocumentStore.cr);

    if (!mounted) return;

    setState(() {
      _savedLicense = license;
      _savedOr = or;
      _savedCr = cr;
    });
  }

  // ---------------------------------------------------------------------------
  // FILE HELPERS
  // ---------------------------------------------------------------------------
  String _extensionOf(String path) {
    final int dot = path.lastIndexOf('.');
    if (dot == -1 || dot == path.length - 1) return '';
    return path.substring(dot + 1).toLowerCase();
  }

  String _fileNameOf(String path) => path.split(RegExp(r'[/\\]')).last;

  String _sizeLabel(File file) {
    try {
      final int bytes = file.lengthSync();
      if (bytes < 1024) return '$bytes B';
      if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(1)} KB';
      return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
    } catch (_) {
      return 'Unknown size';
    }
  }

  File? _usable(File? file) =>
      (file != null && file.existsSync()) ? file : null;

  // Files selected during sign up. The in-memory copy is used first; the copy
  // saved in permanent storage is the fallback (e.g. after an app restart).
  File? get _licenseLocalFile =>
      _usable(RiderProfile.localDriverLicenseFile) ?? _usable(_savedLicense);

  File? get _orLocalFile =>
      _usable(RiderProfile.localOrFile) ?? _usable(_savedOr);

  File? get _crLocalFile =>
      _usable(RiderProfile.localCrFile) ?? _usable(_savedCr);

  // ---------------------------------------------------------------------------
  // LICENSE EXPIRY
  // ---------------------------------------------------------------------------
  DateTime _dateOnly(DateTime date) =>
      DateTime(date.year, date.month, date.day);

  String _formatDate(DateTime date) =>
      '${date.day.toString().padLeft(2, '0')}/'
      '${date.month.toString().padLeft(2, '0')}/${date.year}';

  String get _licenseExpiryStatus {
    if (_licenseExpiryDate == null) return 'Expiration date not set';
    final today = _dateOnly(DateTime.now());
    final expiry = _dateOnly(_licenseExpiryDate!);
    if (expiry.isBefore(today)) return 'Expired';
    final daysRemaining = expiry.difference(today).inDays;
    if (daysRemaining <= 30) return 'Expires Soon';
    return 'Valid';
  }

  Color get _licenseExpiryStatusColor {
    switch (_licenseExpiryStatus) {
      case 'Valid':
        return const Color(0xFF287A3D);
      case 'Expires Soon':
        return const Color(0xFF9A6700);
      case 'Expired':
        return const Color(0xFFC62828);
      default:
        return brownBeige;
    }
  }

  Color get _licenseExpiryStatusBackground {
    switch (_licenseExpiryStatus) {
      case 'Valid':
        return const Color(0xFFE8F3E9);
      case 'Expires Soon':
        return const Color(0xFFFFF0D5);
      case 'Expired':
        return const Color(0xFFFCE8E6);
      default:
        return const Color(0xFFECE3DE);
    }
  }

  Future<void> _pickLicenseExpiryDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _licenseExpiryDate ?? now,
      firstDate: DateTime(2000),
      lastDate: DateTime(now.year + 30),
      helpText: "Select driver's license expiration date",
      cancelText: 'CANCEL',
      confirmText: 'SELECT',
      builder: (context, child) => Theme(
        data: Theme.of(context).copyWith(
          colorScheme: const ColorScheme.light(
            primary: darkMaroon,
            onPrimary: Colors.white,
            surface: Colors.white,
            onSurface: darkMaroon,
          ),
        ),
        child: child!,
      ),
    );

    if (picked == null || !mounted) return;
    setState(() {
      _licenseExpiryDate = DateTime(picked.year, picked.month, picked.day);
      _licenseExpiryChanged = true;
    });
  }

  Widget _licenseExpiryCard() {
    final status = _licenseExpiryStatus;
    final hasDate = _licenseExpiryDate != null;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.72),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.event_available_outlined, color: darkMaroon, size: 22),
              SizedBox(width: 9),
              Expanded(
                child: Text(
                  'License Expiration Date',
                  style: TextStyle(
                    color: darkMaroon,
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          const Text(
            'Set the expiry date printed on the driver’s license.',
            style: TextStyle(color: brownBeige, fontSize: 12, height: 1.4),
          ),
          const SizedBox(height: 14),
          InkWell(
            onTap: _pickLicenseExpiryDate,
            borderRadius: BorderRadius.circular(14),
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 15),
              decoration: BoxDecoration(
                color: cream.withValues(alpha: 0.55),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: lightBeige),
              ),
              child: Row(
                children: [
                  const Icon(Icons.calendar_month_outlined, color: darkMaroon),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      hasDate
                          ? _formatDate(_licenseExpiryDate!)
                          : 'Choose expiration date',
                      style: TextStyle(
                        color: hasDate ? darkMaroon : brownBeige,
                        fontWeight: hasDate
                            ? FontWeight.w600
                            : FontWeight.normal,
                      ),
                    ),
                  ),
                  const Icon(Icons.arrow_drop_down_rounded, color: darkMaroon),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerLeft,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
              decoration: BoxDecoration(
                color: _licenseExpiryStatusBackground,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(
                    status == 'Valid'
                        ? Icons.check_circle_outline
                        : status == 'Expires Soon'
                        ? Icons.access_time_rounded
                        : status == 'Expired'
                        ? Icons.error_outline_rounded
                        : Icons.info_outline,
                    color: _licenseExpiryStatusColor,
                    size: 16,
                  ),
                  const SizedBox(width: 6),
                  Text(
                    status,
                    style: TextStyle(
                      color: _licenseExpiryStatusColor,
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (hasDate &&
              status != 'Expired' &&
              status != 'Expiration date not set') ...[
            const SizedBox(height: 6),
            Text(
              '${_dateOnly(_licenseExpiryDate!).difference(_dateOnly(DateTime.now())).inDays} days remaining',
              style: const TextStyle(color: brownBeige, fontSize: 12),
            ),
          ],
        ],
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // PICKERS
  // ---------------------------------------------------------------------------

  // Driver's license: PNG / JPG only.
  Future<void> _pickLicense() async {
    try {
      final XFile? image = await _picker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 85,
        maxWidth: 1800,
      );

      if (image == null || !mounted) return;

      String extension = _extensionOf(image.path);
      if (extension.isEmpty) extension = _extensionOf(image.name);

      if (!_licenseExtensions.contains(extension)) {
        _showMessage("Driver's license must be a PNG or JPG image.");
        return;
      }

      setState(() {
        _draftLicense = File(image.path);
        _licenseDraftSaved = false;
      });
    } catch (_) {
      _showMessage('Unable to select the driver\'s license image.');
    }
  }

  // OR / CR: PDF / DOC / DOCX only.
  Future<void> _pickDocumentFile({required bool isOr}) async {
    try {
      final PlatformFile? picked = await FilePicker.pickFile(
        type: FileType.custom,
        allowedExtensions: _documentExtensions,
      );

      if (picked == null || !mounted) return;

      final String? path = picked.path;
      if (path == null) {
        _showMessage('Unable to read the selected file.');
        return;
      }

      String extension = _extensionOf(path);
      if (extension.isEmpty) extension = _extensionOf(picked.name);

      if (!_documentExtensions.contains(extension)) {
        _showMessage('Only PDF, DOC or DOCX files are accepted.');
        return;
      }

      setState(() {
        if (isOr) {
          _draftOr = File(path);
          _orDraftSaved = false;
        } else {
          _draftCr = File(path);
          _crDraftSaved = false;
        }
      });
    } catch (_) {
      _showMessage('Unable to select the document file.');
    }
  }

  Future<void> _pickOr() => _pickDocumentFile(isOr: true);
  Future<void> _pickCr() => _pickDocumentFile(isOr: false);

  // ---------------------------------------------------------------------------
  // SAVE DRAFT / SUBMIT (local only, no API)
  // ---------------------------------------------------------------------------
  Future<void> _saveDrafts() async {
    if (_draftLicense == null && _draftOr == null && _draftCr == null) {
      _showMessage('Choose a new document first.');
      return;
    }

    setState(() => _isSavingDraft = true);

    await Future<void>.delayed(const Duration(milliseconds: 150));
    if (!mounted) return;

    setState(() {
      if (_draftLicense != null) _licenseDraftSaved = true;
      if (_draftOr != null) _orDraftSaved = true;
      if (_draftCr != null) _crDraftSaved = true;
      _isSavingDraft = false;
    });

    _showMessage(
      'Draft saved. Tap Update Documents to send it to Logistics.',
      success: true,
    );
  }

  Future<void> _submitUpdateRequest() async {
    if (_draftLicense == null &&
        _draftOr == null &&
        _draftCr == null &&
        !_licenseExpiryChanged) {
      _showMessage('Choose a document or update the license expiration date.');
      return;
    }

    if ((_draftLicense != null && !_licenseDraftSaved) ||
        (_draftOr != null && !_orDraftSaved) ||
        (_draftCr != null && !_crDraftSaved)) {
      _showMessage('Please tap Save New Document before updating.');
      return;
    }

    setState(() => _isSubmittingUpdate = true);

    // No server request. The request is kept on this device only.
    await Future<void>.delayed(const Duration(milliseconds: 300));
    if (!mounted) return;

    setState(() {
      if (_draftLicense != null) {
        _pendingLicense = _draftLicense;
        _licensePendingApproval = true;
      }
      if (_draftOr != null) {
        _pendingOr = _draftOr;
        _orPendingApproval = true;
      }
      if (_draftCr != null) {
        _pendingCr = _draftCr;
        _crPendingApproval = true;
      }
      if (_licenseExpiryChanged) {
        _licensePendingApproval = true;
        _licenseExpiryChanged = false;
      }

      // Keep the currently approved documents.
      // Clear only the local replacement drafts after submission.
      _draftLicense = null;
      _draftOr = null;
      _draftCr = null;
      _licenseDraftSaved = false;
      _orDraftSaved = false;
      _crDraftSaved = false;
      _isSubmittingUpdate = false;
    });

    _showMessage(
      'Update request submitted. Waiting for Logistics approval.',
      success: true,
    );
  }

  // ---------------------------------------------------------------------------
  // VIEW DOCUMENT
  // ---------------------------------------------------------------------------
  Future<void> _viewDocument({
    required String title,
    required bool isImage,
    required File? displayFile,
    required bool isDraft,
  }) async {
    if (displayFile == null || !displayFile.existsSync()) {
      _showMessage('No document is available to view.');
      return;
    }

    // PDF / DOC / DOCX: open with the phone's installed PDF or Word app.
    if (!isImage) {
      try {
        final OpenResult result = await OpenFilex.open(displayFile.path);

        if (!mounted) return;

        switch (result.type) {
          case ResultType.done:
            break;
          case ResultType.noAppToOpen:
            _showMessage(
              'No app found to open this file. Install a PDF or Word viewer.',
            );
            break;
          case ResultType.fileNotFound:
            _showMessage('The file could not be found on this device.');
            break;
          case ResultType.permissionDenied:
            _showMessage('Permission denied. Unable to open this file.');
            break;
          default:
            _showMessage('Unable to open this file.');
        }
      } catch (_) {
        _showMessage('Unable to open this file.');
      }
      return;
    }

    // Driver's license (PNG / JPG): zoomable preview dialog.
    showDialog<void>(
      context: context,
      builder: (dialogContext) => Dialog(
        backgroundColor: cream,
        insetPadding: const EdgeInsets.all(16),
        child: Padding(
          padding: const EdgeInsets.all(16),
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
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.pop(dialogContext),
                    icon: const Icon(Icons.close, color: darkMaroon),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              ConstrainedBox(
                constraints: BoxConstraints(
                  maxHeight: MediaQuery.of(dialogContext).size.height * 0.68,
                ),
                child: InteractiveViewer(
                  minScale: 0.8,
                  maxScale: 4,
                  child: Image.file(
                    displayFile,
                    fit: BoxFit.contain,
                    errorBuilder: (context, error, stackTrace) => const Padding(
                      padding: EdgeInsets.all(24),
                      child: Text('Unable to load this document.'),
                    ),
                  ),
                ),
              ),
              if (isDraft) ...[
                const SizedBox(height: 8),
                const Text(
                  'Previewing your replacement draft',
                  style: TextStyle(color: brownBeige, fontSize: 12),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  void _showMessage(String message, {bool success = false}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          backgroundColor: darkMaroon,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
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

  // ---------------------------------------------------------------------------
  // BUILD
  // ---------------------------------------------------------------------------
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: cream,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          onPressed: () => Navigator.pop(context),
          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            color: darkMaroon,
            size: 20,
          ),
        ),
        title: const Text(
          'Documents',
          style: TextStyle(
            fontWeight: FontWeight.bold,
            color: darkMaroon,
            fontSize: 21,
          ),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 34),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(22),
              decoration: BoxDecoration(
                color: darkMaroon,
                borderRadius: BorderRadius.circular(28),
              ),
              child: const Row(
                children: [
                  CircleAvatar(
                    radius: 38,
                    backgroundColor: lightBeige,
                    child: Icon(
                      Icons.description_outlined,
                      color: darkMaroon,
                      size: 38,
                    ),
                  ),
                  SizedBox(width: 18),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Rider Documents',
                          style: TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                            fontSize: 20,
                          ),
                        ),
                        SizedBox(height: 6),
                        Text(
                          'View your current documents or prepare updates for Logistics approval.',
                          style: TextStyle(
                            color: lightBeige,
                            fontSize: 12,
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // DRIVER'S LICENSE (PNG / JPG)
            _documentCard(
              title: "Driver's License",
              subtitle: RiderProfile.licenseNumber.isEmpty
                  ? 'License document'
                  : 'License No. ${RiderProfile.licenseNumber}',
              formatHint: 'Accepted formats: PNG or JPG',
              icon: Icons.badge_outlined,
              isImage: true,
              currentFile: _licenseLocalFile,
              pendingFile: _pendingLicense,
              draftFile: _draftLicense,
              draftSaved: _licenseDraftSaved,
              pendingApproval: _licensePendingApproval,
              onSelect: _pickLicense,
            ),
            const SizedBox(height: 14),
            _licenseExpiryCard(),
            const SizedBox(height: 18),

            // OFFICIAL RECEIPT (OR) (PDF / DOC / DOCX)
            _documentCard(
              title: 'Official Receipt (OR)',
              subtitle: RiderProfile.plateNumber.isEmpty
                  ? 'Vehicle registration document'
                  : 'Plate No. ${RiderProfile.plateNumber}',
              formatHint: 'Accepted formats: PDF, DOC or DOCX',
              icon: Icons.receipt_long_outlined,
              isImage: false,
              currentFile: _orLocalFile,
              pendingFile: _pendingOr,
              draftFile: _draftOr,
              draftSaved: _orDraftSaved,
              pendingApproval: _orPendingApproval,
              onSelect: _pickOr,
            ),
            const SizedBox(height: 18),

            // CERTIFICATE OF REGISTRATION (CR) (PDF / DOC / DOCX)
            _documentCard(
              title: 'Certificate of Registration (CR)',
              subtitle: RiderProfile.plateNumber.isEmpty
                  ? 'Vehicle registration document'
                  : 'Plate No. ${RiderProfile.plateNumber}',
              formatHint: 'Accepted formats: PDF, DOC or DOCX',
              icon: Icons.article_outlined,
              isImage: false,
              currentFile: _crLocalFile,
              pendingFile: _pendingCr,
              draftFile: _draftCr,
              draftSaved: _crDraftSaved,
              pendingApproval: _crPendingApproval,
              onSelect: _pickCr,
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              height: 54,
              child: ElevatedButton.icon(
                onPressed: _isSavingDraft || _isSubmittingUpdate
                    ? null
                    : _saveDrafts,
                icon: _isSavingDraft
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.save_outlined),
                label: Text(
                  _isSavingDraft ? 'Saving Draft...' : 'Save New Document',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: darkMaroon,
                  foregroundColor: Colors.white,
                  disabledBackgroundColor: brownBeige,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(28),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              height: 54,
              child: OutlinedButton.icon(
                onPressed: _isSavingDraft || _isSubmittingUpdate
                    ? null
                    : _submitUpdateRequest,
                icon: _isSubmittingUpdate
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.send_outlined),
                label: Text(
                  _isSubmittingUpdate
                      ? 'Submitting Request...'
                      : 'Update Documents',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                style: OutlinedButton.styleFrom(
                  foregroundColor: darkMaroon,
                  side: const BorderSide(color: darkMaroon, width: 1.5),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(28),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
            const Text(
              'Save New Document keeps your selected file as a draft in this screen. Update Documents sends the saved draft to Logistics for review.',
              style: TextStyle(color: brownBeige, fontSize: 12, height: 1.4),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  // ---------------------------------------------------------------------------
  // DOCUMENT CARD
  // Preview order: new draft -> pending replacement -> file from sign up.
  // ---------------------------------------------------------------------------
  Widget _documentCard({
    required String title,
    required String subtitle,
    required String formatHint,
    required IconData icon,
    required bool isImage,
    required File? currentFile,
    required File? pendingFile,
    required File? draftFile,
    required bool draftSaved,
    required bool pendingApproval,
    required VoidCallback onSelect,
  }) {
    final File? displayFile = draftFile ?? pendingFile ?? currentFile;
    final bool hasCurrentDocument = currentFile != null;

    final String statusText = draftFile != null
        ? (draftSaved ? 'Draft Saved' : 'Draft Not Saved')
        : pendingApproval
        ? 'Pending Logistics Approval'
        : hasCurrentDocument
        ? 'Current Document'
        : 'No Document Uploaded';

    final Color statusColor = draftFile != null
        ? darkMaroon
        : pendingApproval
        ? const Color(0xFF9A6700)
        : hasCurrentDocument
        ? const Color(0xFF287A3D)
        : brownBeige;

    final Color statusBackground = draftFile != null
        ? const Color(0xFFF1E4DD)
        : pendingApproval
        ? const Color(0xFFFFF0D5)
        : hasCurrentDocument
        ? const Color(0xFFE8F3E9)
        : const Color(0xFFECE3DE);

    final IconData statusIcon = draftFile != null
        ? Icons.edit_document
        : pendingApproval
        ? Icons.hourglass_top_rounded
        : Icons.description_outlined;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.72),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: lightBeige.withValues(alpha: 0.65),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(icon, color: darkMaroon),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        color: darkMaroon,
                        fontSize: 16,
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
            ],
          ),
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerLeft,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: statusBackground,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(statusIcon, size: 15, color: statusColor),
                  const SizedBox(width: 6),
                  Text(
                    statusText,
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: statusColor,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(18),
            child: Container(
              width: double.infinity,
              height: 190,
              color: cream.withValues(alpha: 0.6),
              child: _preview(displayFile, isImage),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            draftFile != null
                ? 'New file selected. Save it before submitting for approval.'
                : formatHint,
            style: const TextStyle(color: brownBeige, fontSize: 11),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _viewDocument(
                    title: title,
                    isImage: isImage,
                    displayFile: displayFile,
                    isDraft: draftFile != null,
                  ),
                  icon: const Icon(Icons.visibility_outlined, size: 18),
                  label: const Text('View'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: darkMaroon,
                    side: const BorderSide(color: darkMaroon),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _isSavingDraft || _isSubmittingUpdate
                      ? null
                      : onSelect,
                  icon: Icon(
                    isImage ? Icons.image_outlined : Icons.upload_file_outlined,
                    size: 18,
                  ),
                  label: Text(isImage ? 'Choose Image' : 'Choose File'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: darkMaroon,
                    side: const BorderSide(color: darkMaroon),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // Image preview for the license, file tile for PDF / DOC / DOCX.
  Widget _preview(File? file, bool isImage) {
    if (file == null) return _emptyDocumentPreview();

    if (isImage) {
      return Image.file(
        file,
        fit: BoxFit.contain,
        errorBuilder: (context, error, stackTrace) => _emptyDocumentPreview(),
      );
    }

    final String extension = _extensionOf(file.path);

    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              extension == 'pdf'
                  ? Icons.picture_as_pdf_outlined
                  : Icons.description_outlined,
              color: darkMaroon,
              size: 52,
            ),
            const SizedBox(height: 8),
            Text(
              _fileNameOf(file.path),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: darkMaroon,
                fontWeight: FontWeight.bold,
                fontSize: 13,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              '${extension.toUpperCase()}  •  ${_sizeLabel(file)}',
              style: const TextStyle(color: brownBeige, fontSize: 11),
            ),
          ],
        ),
      ),
    );
  }

  Widget _emptyDocumentPreview() {
    return const Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.image_not_supported_outlined, color: brownBeige, size: 42),
          SizedBox(height: 8),
          Text(
            'No document preview available',
            style: TextStyle(color: brownBeige, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

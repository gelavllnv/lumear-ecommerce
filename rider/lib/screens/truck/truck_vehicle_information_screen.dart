import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_application_1/models/truck_profile_store.dart';
import 'package:flutter_application_1/models/truck_rider_profile.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';

class TruckVehicleInformationScreen extends StatefulWidget {
  final TruckRiderProfile profile;
  final ValueChanged<TruckRiderProfile>? onSaved;

  const TruckVehicleInformationScreen({
    super.key,
    required this.profile,
    this.onSaved,
  });

  @override
  State<TruckVehicleInformationScreen> createState() =>
      _TruckVehicleInformationScreenState();
}

class _TruckVehicleInformationScreenState
    extends State<TruckVehicleInformationScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);

  late TruckRiderProfile _profile;

  final TextEditingController _licenseController = TextEditingController();
  final TextEditingController _plateController = TextEditingController();

  bool _isEditing = false;
  bool _isPickingFile = false;

  String? _licensePath;
  String? _orPath;
  String? _crPath;

  // Forces uppercase even when text is pasted.
  static final List<TextInputFormatter> _upperCaseFormatters = [
    TextInputFormatter.withFunction(
      (oldValue, newValue) =>
          newValue.copyWith(text: newValue.text.toUpperCase()),
    ),
  ];

  @override
  void initState() {
    super.initState();
    // Use the latest shared profile if one exists for this rider.
    _profile = TruckProfileStore.resolve(widget.profile);
    _loadProfile();
  }

  @override
  void dispose() {
    _licenseController.dispose();
    _plateController.dispose();
    super.dispose();
  }

  void _loadProfile() {
    _licenseController.text = _profile.licenseNumber;
    _plateController.text = _profile.plateNumber;

    _licensePath = _profile.licenseImagePath;
    _orPath = _profile.orImagePath;
    _crPath = _profile.crImagePath;
  }

  void _showMessage(String message, {bool success = false}) {
    if (!mounted) return;

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: darkMaroon,
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

  // ------------------------------------------------------------
  // PERSIST FILE (copy from temp cache to permanent app storage)
  // ------------------------------------------------------------

  Future<String> _persistFile(String sourcePath, String type) async {
    final dir = await getApplicationDocumentsDirectory();
    final folder = Directory(p.join(dir.path, 'rider_documents'));

    if (!await folder.exists()) {
      await folder.create(recursive: true);
    }

    final String fileName =
        '${_profile.riderId}_${type}_${DateTime.now().millisecondsSinceEpoch}'
        '${p.extension(sourcePath)}';
    final String destPath = p.join(folder.path, fileName);

    await File(sourcePath).copy(destPath);
    return destPath;
  }

  // ------------------------------------------------------------
  // DOCUMENT PICKER
  // ------------------------------------------------------------

  Future<void> _pickDocument(String type) async {
    if (_isPickingFile) return;

    List<String> extensions;

    switch (type) {
      case 'license':
        // Driver's license: image files only.
        extensions = ['png', 'jpg', 'jpeg'];
        break;
      case 'or':
      case 'cr':
        // OR and CR: PDF or Microsoft Word files.
        extensions = ['pdf', 'doc', 'docx'];
        break;
      default:
        return;
    }

    setState(() => _isPickingFile = true);

    try {
      // file_picker 12+/13: pickFile() returns a single PlatformFile?
      // (null when the user cancels).
      final PlatformFile? picked = await FilePicker.pickFile(
        type: FileType.custom,
        allowedExtensions: extensions,
      );

      if (picked == null || !mounted) return;

      final String? path = picked.path;

      if (path == null || path.isEmpty) {
        _showMessage('Unable to access this file. Please select it again.');
        return;
      }

      final String savedPath = await _persistFile(path, type);

      if (!mounted) return;

      setState(() {
        switch (type) {
          case 'license':
            _licensePath = savedPath;
            break;
          case 'or':
            _orPath = savedPath;
            break;
          case 'cr':
            _crPath = savedPath;
            break;
        }
      });

      _showMessage(
        'Document selected. Tap Save Changes to keep your update.',
        success: true,
      );
    } catch (e) {
      _showMessage('Unable to select the document. Please try again.');
    } finally {
      if (mounted) {
        setState(() => _isPickingFile = false);
      }
    }
  }

  // ------------------------------------------------------------
  // VIEW DOCUMENT
  // ------------------------------------------------------------

  Future<void> _viewDocument(String title, String? path) async {
    if (path == null || path.trim().isEmpty) {
      _showMessage('No document uploaded yet.');
      return;
    }

    final file = File(path);

    if (!await file.exists()) {
      _showMessage(
        'The file is no longer available on this device. Please upload it again.',
      );
      return;
    }

    final String extension = path.split('.').last.toLowerCase();

    if (['png', 'jpg', 'jpeg'].contains(extension)) {
      if (!mounted) return;

      await showDialog<void>(
        context: context,
        builder: (dialogContext) => Dialog(
          backgroundColor: cream,
          insetPadding: const EdgeInsets.all(16),
          child: Padding(
            padding: const EdgeInsets.all(12),
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
                          fontSize: 16,
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
                Flexible(
                  child: InteractiveViewer(
                    child: Image.file(
                      file,
                      fit: BoxFit.contain,
                      errorBuilder: (_, __, ___) => const Padding(
                        padding: EdgeInsets.all(24),
                        child: Text('Unable to display this image.'),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
      return;
    }

    // PDFs and Word files open in a compatible app on the device.
    final result = await OpenFilex.open(path);

    if (!mounted) return;

    if (result.type != ResultType.done) {
      _showMessage(
        'Could not open this file. Make sure your device has a compatible app.',
      );
    }
  }

  // ------------------------------------------------------------
  // SAVE CHANGES
  // ------------------------------------------------------------

  void _saveChanges() {
    FocusScope.of(context).unfocus();

    final String license = _licenseController.text.trim();
    final String plate = _plateController.text.trim();

    if (license.isEmpty) {
      _showMessage("Please enter your driver's license number.");
      return;
    }

    if (plate.isEmpty) {
      _showMessage('Please enter your vehicle plate number.');
      return;
    }

    // Start from the latest shared profile so personal-information edits
    // made elsewhere are never overwritten, then apply the vehicle changes.
    final TruckRiderProfile base = TruckProfileStore.resolve(_profile);

    final updated = base.copyWith(
      licenseNumber: license,
      plateNumber: plate,
      licenseImagePath: _licensePath,
      orImagePath: _orPath,
      crImagePath: _crPath,
    );

    // Share with Personal Information (and anything else listening).
    TruckProfileStore.update(updated);

    setState(() {
      _profile = updated;
      _isEditing = false;
    });

    widget.onSaved?.call(updated);

    _showMessage('Vehicle information updated.', success: true);
  }

  void _cancelEditing() {
    FocusScope.of(context).unfocus();

    setState(() {
      _loadProfile();
      _isEditing = false;
    });
  }

  // ------------------------------------------------------------
  // FIELD DECORATION
  // ------------------------------------------------------------

  InputDecoration _decoration(
    String label,
    IconData icon, {
    Widget? suffixIcon,
    bool enabled = true,
  }) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: brownBeige, fontSize: 13),
      floatingLabelStyle: const TextStyle(
        color: darkMaroon,
        fontWeight: FontWeight.w600,
      ),
      prefixIcon: Container(
        margin: const EdgeInsets.all(9),
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: lightBeige.withValues(alpha: 0.55),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Icon(icon, color: darkMaroon, size: 20),
      ),
      suffixIcon: suffixIcon,
      filled: true,
      fillColor: enabled && _isEditing
          ? Colors.white
          : cream.withValues(alpha: 0.45),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: BorderSide(color: lightBeige.withValues(alpha: 0.8)),
      ),
      disabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: BorderSide.none,
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(18),
        borderSide: const BorderSide(color: darkMaroon, width: 1.6),
      ),
    );
  }

  static const TextStyle _valueStyle = TextStyle(
    color: darkMaroon,
    fontSize: 14,
    fontWeight: FontWeight.w600,
  );

  Widget _editableField({
    required String label,
    required IconData icon,
    required TextEditingController controller,
    TextCapitalization capitalization = TextCapitalization.characters,
    List<TextInputFormatter>? inputFormatters,
  }) {
    return TextField(
      controller: controller,
      enabled: _isEditing,
      textCapitalization: capitalization,
      inputFormatters: inputFormatters ?? _upperCaseFormatters,
      style: _valueStyle,
      decoration: _decoration(label, icon, enabled: _isEditing),
    );
  }

  Widget _lockedField({
    required String label,
    required IconData icon,
    required String value,
  }) {
    return InputDecorator(
      decoration: _decoration(
        label,
        icon,
        enabled: false,
        suffixIcon: const Icon(Icons.lock_outline, color: brownBeige, size: 18),
      ),
      child: Text(value.trim().isEmpty ? '-' : value, style: _valueStyle),
    );
  }

  Widget _sectionTitle(String title, String subtitle) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            color: darkMaroon,
            fontSize: 20,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 6),
        Text(subtitle, style: const TextStyle(color: brownBeige, fontSize: 12)),
      ],
    );
  }

  Widget _card(List<Widget> children) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: children,
      ),
    );
  }

  Widget _gap() => const SizedBox(height: 16);

  // ------------------------------------------------------------
  // DOCUMENT CARD
  // ------------------------------------------------------------

  Widget _documentCard({
    required String title,
    required String? path,
    required IconData icon,
    required String type,
    required String acceptedFormats,
  }) {
    final bool uploaded = path != null && path.trim().isNotEmpty;
    final String fileName = uploaded
        ? path.split(Platform.pathSeparator).last
        : 'No file selected';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: cream.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: uploaded ? Colors.green.withValues(alpha: 0.5) : lightBeige,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: lightBeige.withValues(alpha: 0.55),
                  borderRadius: BorderRadius.circular(12),
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
                        fontWeight: FontWeight.bold,
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      uploaded ? 'File selected' : 'No file uploaded',
                      style: TextStyle(
                        color: uploaded ? Colors.green.shade800 : brownBeige,
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(
                uploaded ? Icons.check_circle : Icons.upload_file_outlined,
                color: uploaded ? Colors.green.shade800 : brownBeige,
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'Accepted: $acceptedFormats',
            style: const TextStyle(color: brownBeige, fontSize: 11),
          ),
          if (uploaded) ...[
            const SizedBox(height: 8),
            Text(
              fileName,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: darkMaroon, fontSize: 12),
            ),
          ],
          const SizedBox(height: 12),
          Row(
            children: [
              if (uploaded)
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _viewDocument(title, path),
                    icon: const Icon(Icons.visibility_outlined, size: 18),
                    label: const Text('View Document'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: darkMaroon,
                      side: const BorderSide(color: lightBeige),
                    ),
                  ),
                ),
              if (uploaded) const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: (!_isEditing || _isPickingFile)
                      ? null
                      : () => _pickDocument(type),
                  icon: Icon(
                    uploaded ? Icons.refresh_rounded : Icons.upload_file,
                    size: 18,
                  ),
                  label: Text(uploaded ? 'Replace' : 'Upload'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: darkMaroon,
                    foregroundColor: Colors.white,
                    disabledBackgroundColor: lightBeige,
                    disabledForegroundColor: brownBeige,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // ------------------------------------------------------------
  // BUILD SCREEN
  // ------------------------------------------------------------

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
          'Vehicle Information',
          style: TextStyle(
            color: darkMaroon,
            fontWeight: FontWeight.bold,
            fontSize: 21,
          ),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 34),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // HEADER
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 22),
              decoration: BoxDecoration(
                color: darkMaroon,
                borderRadius: BorderRadius.circular(28),
                boxShadow: [
                  BoxShadow(
                    color: darkMaroon.withValues(alpha: 0.15),
                    blurRadius: 16,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: Row(
                children: [
                  const CircleAvatar(
                    radius: 38,
                    backgroundColor: lightBeige,
                    child: Icon(
                      Icons.local_shipping_outlined,
                      size: 40,
                      color: darkMaroon,
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Vehicle Details',
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: Colors.white,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          _profile.fullName.trim().isEmpty
                              ? 'Registered Rider'
                              : _profile.fullName,
                          style: const TextStyle(
                            fontSize: 13,
                            color: lightBeige,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          _profile.riderId.trim().isEmpty
                              ? 'Lumear Rider'
                              : 'Rider ID: ${_profile.riderId}',
                          style: const TextStyle(
                            fontSize: 11,
                            color: lightBeige,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 30),

            // VEHICLE INFORMATION
            _sectionTitle(
              'Vehicle Information',
              'Vehicle type and account status are managed by Logistics.',
            ),
            const SizedBox(height: 18),
            _card([
              _lockedField(
                label: 'Vehicle Type',
                icon: Icons.local_shipping_outlined,
                value: _profile.vehicleType,
              ),
              _gap(),
              _lockedField(
                label: 'Account Status',
                icon: Icons.verified_user_outlined,
                value: _profile.accountStatus,
              ),
              _gap(),
              _editableField(
                label: "Driver's License Number",
                icon: Icons.badge_outlined,
                controller: _licenseController,
              ),
              _gap(),
              _editableField(
                label: 'Vehicle Plate Number',
                icon: Icons.pin_outlined,
                controller: _plateController,
              ),
            ]),

            const SizedBox(height: 28),

            // DOCUMENTS
            _sectionTitle(
              'My Documents',
              'Upload and view your license and vehicle registration files.',
            ),
            const SizedBox(height: 18),

            _card([
              _documentCard(
                title: "Driver's License",
                path: _licensePath,
                icon: Icons.badge_outlined,
                type: 'license',
                acceptedFormats: 'PNG, JPG, JPEG',
              ),
              _gap(),
              _documentCard(
                title: 'Official Receipt (OR)',
                path: _orPath,
                icon: Icons.receipt_long_outlined,
                type: 'or',
                acceptedFormats: 'PDF, DOC, DOCX',
              ),
              _gap(),
              _documentCard(
                title: 'Certificate of Registration (CR)',
                path: _crPath,
                icon: Icons.description_outlined,
                type: 'cr',
                acceptedFormats: 'PDF, DOC, DOCX',
              ),
              const SizedBox(height: 12),
              const Text(
                'Upload or replace documents while editing. '
                'Selected files are saved to your profile when you tap Save Changes.',
                style: TextStyle(color: brownBeige, fontSize: 12),
              ),
            ]),

            const SizedBox(height: 30),

            // EDIT / SAVE
            SizedBox(
              width: double.infinity,
              height: 56,
              child: ElevatedButton.icon(
                onPressed: _isEditing
                    ? _saveChanges
                    : () => setState(() => _isEditing = true),
                icon: Icon(
                  _isEditing ? Icons.save_outlined : Icons.edit_outlined,
                ),
                label: Text(
                  _isEditing ? 'Save Changes' : 'Edit Information',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: darkMaroon,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(28),
                  ),
                ),
              ),
            ),

            if (_isEditing) ...[
              const SizedBox(height: 8),
              Center(
                child: TextButton(
                  onPressed: _cancelEditing,
                  child: const Text(
                    'Cancel Editing',
                    style: TextStyle(
                      color: maroon,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

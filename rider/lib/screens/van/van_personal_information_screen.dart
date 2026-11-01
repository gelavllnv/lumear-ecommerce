import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_application_1/models/rider_profile.dart';

const Color vanDarkMaroon = Color(0xFF62191C);
const Color vanMaroon = Color(0xFF873632);
const Color vanBrownBeige = Color(0xFF9E7161);
const Color vanLightBeige = Color(0xFFCAAE9F);
const Color vanCream = Color(0xFFF3E9E1);

const List<String> _monthNames = [
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

/// Accepts both "2000-03-05" and "March 5, 2000" (the format the signup
/// screen stores in RiderProfile).
DateTime? _parseBirthday(String text) {
  final t = text.trim();
  if (t.isEmpty) return null;

  final iso = DateTime.tryParse(t);
  if (iso != null) return iso;

  final match = RegExp(r'^([A-Za-z]+)\s+(\d{1,2}),\s*(\d{4})$').firstMatch(t);
  if (match == null) return null;

  final monthIndex = _monthNames.indexWhere(
    (m) => m.toLowerCase() == match.group(1)!.toLowerCase(),
  );
  if (monthIndex < 0) return null;

  return DateTime(
    int.parse(match.group(3)!),
    monthIndex + 1,
    int.parse(match.group(2)!),
  );
}

String _isoDate(DateTime d) =>
    '${d.year.toString().padLeft(4, '0')}-'
    '${d.month.toString().padLeft(2, '0')}-'
    '${d.day.toString().padLeft(2, '0')}';

String _longDate(DateTime d) =>
    '${_monthNames[d.month - 1]} ${d.day}, ${d.year}';

class VanPersonalInformationScreen extends StatefulWidget {
  /// Path of the rider's uploaded profile photo, if any.
  final String? photoPath;

  const VanPersonalInformationScreen({super.key, this.photoPath});

  @override
  State<VanPersonalInformationScreen> createState() =>
      _VanPersonalInformationScreenState();
}

class _VanPersonalInformationScreenState
    extends State<VanPersonalInformationScreen> {
  bool _isEditing = false;
  bool _isSaving = false;

  late final TextEditingController _nameController;
  late final TextEditingController _phoneController;
  late final TextEditingController _emailController;
  late final TextEditingController _birthdayController;
  late final TextEditingController _ageController;
  late final TextEditingController _regionController;
  late final TextEditingController _provinceController;
  late final TextEditingController _municipalityController;
  late final TextEditingController _barangayController;
  late final TextEditingController _streetController;
  late final TextEditingController _houseUnitController;

  late Map<String, String> _originalValues;

  @override
  void initState() {
    super.initState();

    // Values come from the signup form, saved in RiderProfile.
    final String rawBirthday = RiderProfile.birthday ?? '';
    final DateTime? parsedBirthday = _parseBirthday(rawBirthday);

    _nameController = TextEditingController(text: RiderProfile.name ?? '');
    _phoneController = TextEditingController(text: RiderProfile.phone ?? '');
    _emailController = TextEditingController(text: RiderProfile.email ?? '');
    _birthdayController = TextEditingController(
      text: parsedBirthday == null ? rawBirthday : _isoDate(parsedBirthday),
    );
    _ageController = TextEditingController(
      text: RiderProfile.age?.toString() ?? '',
    );
    _regionController = TextEditingController(text: RiderProfile.region ?? '');
    _provinceController = TextEditingController(
      text: RiderProfile.province ?? '',
    );
    _municipalityController = TextEditingController(
      text: RiderProfile.municipality ?? '',
    );
    _barangayController = TextEditingController(
      text: RiderProfile.barangay ?? '',
    );
    _streetController = TextEditingController(text: RiderProfile.street ?? '');
    _houseUnitController = TextEditingController(
      text: RiderProfile.houseUnit ?? '',
    );

    _originalValues = _readValues();
  }

  Map<String, String> _readValues() {
    return {
      'name': _nameController.text,
      'phone': _phoneController.text,
      'email': _emailController.text,
      'birthday': _birthdayController.text,
      'age': _ageController.text,
      'region': _regionController.text,
      'province': _provinceController.text,
      'municipality': _municipalityController.text,
      'barangay': _barangayController.text,
      'street': _streetController.text,
      'houseUnit': _houseUnitController.text,
    };
  }

  void _restoreValues() {
    _nameController.text = _originalValues['name'] ?? '';
    _phoneController.text = _originalValues['phone'] ?? '';
    _emailController.text = _originalValues['email'] ?? '';
    _birthdayController.text = _originalValues['birthday'] ?? '';
    _ageController.text = _originalValues['age'] ?? '';
    _regionController.text = _originalValues['region'] ?? '';
    _provinceController.text = _originalValues['province'] ?? '';
    _municipalityController.text = _originalValues['municipality'] ?? '';
    _barangayController.text = _originalValues['barangay'] ?? '';
    _streetController.text = _originalValues['street'] ?? '';
    _houseUnitController.text = _originalValues['houseUnit'] ?? '';
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _birthdayController.dispose();
    _ageController.dispose();
    _regionController.dispose();
    _provinceController.dispose();
    _municipalityController.dispose();
    _barangayController.dispose();
    _streetController.dispose();
    _houseUnitController.dispose();
    super.dispose();
  }

  Future<void> _saveChanges() async {
    if (_nameController.text.trim().isEmpty ||
        _phoneController.text.trim().isEmpty ||
        _emailController.text.trim().isEmpty) {
      _showMessage('Please enter your name, phone number, and email.');
      return;
    }

    if (!_emailController.text.trim().contains('@')) {
      _showMessage('Please enter a valid email address.');
      return;
    }

    setState(() => _isSaving = true);

    // Demo-only save: writes back to the in-memory RiderProfile.
    // Connect to your Node.js API to persist across sessions.
    await Future<void>.delayed(const Duration(milliseconds: 300));

    if (!mounted) return;

    final String birthdayText = _birthdayController.text.trim();
    final DateTime? parsedBirthday = _parseBirthday(birthdayText);

    RiderProfile.name = _nameController.text.trim();
    RiderProfile.phone = _phoneController.text.trim();
    RiderProfile.email = _emailController.text.trim();
    // Same format the signup screen uses ("March 5, 2000").
    RiderProfile.birthday = parsedBirthday == null
        ? birthdayText
        : _longDate(parsedBirthday);
    RiderProfile.age = int.tryParse(_ageController.text.trim());
    RiderProfile.region = _regionController.text.trim();
    RiderProfile.province = _provinceController.text.trim();
    RiderProfile.municipality = _municipalityController.text.trim();
    RiderProfile.barangay = _barangayController.text.trim();
    RiderProfile.street = _streetController.text.trim();
    RiderProfile.houseUnit = _houseUnitController.text.trim();

    setState(() {
      _originalValues = _readValues();
      _isEditing = false;
      _isSaving = false;
    });

    _showMessage(
      'Information updated in this demo. API saving is not connected yet.',
      success: true,
    );
  }

  void _cancelEditing() {
    _restoreValues();
    setState(() => _isEditing = false);
  }

  Future<void> _selectBirthday() async {
    final now = DateTime.now();
    DateTime initialDate = DateTime(now.year - 25, now.month, now.day);

    final parsed = _parseBirthday(_birthdayController.text);
    if (parsed != null) initialDate = parsed;

    final selected = await showDatePicker(
      context: context,
      initialDate: initialDate,
      firstDate: DateTime(1940),
      lastDate: now,
      helpText: 'Select your birthday',
      builder: (context, child) => Theme(
        data: Theme.of(context).copyWith(
          colorScheme: const ColorScheme.light(
            primary: vanDarkMaroon,
            onPrimary: Colors.white,
            surface: Colors.white,
            onSurface: vanDarkMaroon,
          ),
        ),
        child: child!,
      ),
    );

    if (selected == null || !mounted) return;

    final today = DateTime.now();
    var age = today.year - selected.year;

    if (today.month < selected.month ||
        (today.month == selected.month && today.day < selected.day)) {
      age--;
    }

    setState(() {
      _isEditing = true;
      _birthdayController.text = _isoDate(selected);
      _ageController.text = age.toString();
    });
  }

  void _showMessage(String message, {bool success = false}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          backgroundColor: vanDarkMaroon,
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: vanCream,
      appBar: AppBar(
        backgroundColor: vanCream,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          onPressed: () => Navigator.pop(context),
          icon: const Icon(
            Icons.arrow_back_ios_new_rounded,
            color: vanDarkMaroon,
            size: 20,
          ),
        ),
        title: const Text(
          'Personal Information',
          style: TextStyle(
            fontWeight: FontWeight.bold,
            color: vanDarkMaroon,
            fontSize: 21,
          ),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 34),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildProfileHeader(),
            const SizedBox(height: 28),

            _sectionTitle(
              'Personal Details',
              'Your registered van rider information.',
            ),
            const SizedBox(height: 16),

            _card([
              _infoField('Full Name', Icons.person_outline, _nameController),
              _gap(),
              _infoField(
                'Phone Number',
                Icons.phone_outlined,
                _phoneController,
                keyboardType: TextInputType.phone,
              ),
              _gap(),
              _infoField(
                'Email Address',
                Icons.email_outlined,
                _emailController,
                keyboardType: TextInputType.emailAddress,
              ),
              _gap(),
              Row(
                children: [
                  Expanded(child: _birthdayField()),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _infoField(
                      'Age',
                      Icons.numbers_outlined,
                      _ageController,
                      readOnly: true,
                    ),
                  ),
                ],
              ),
            ]),

            const SizedBox(height: 28),

            _sectionTitle('Address', 'Your registered residential address.'),
            const SizedBox(height: 16),

            _card([
              _infoField('Region', Icons.map_outlined, _regionController),
              _gap(),
              _infoField(
                'Province',
                Icons.location_city_outlined,
                _provinceController,
              ),
              _gap(),
              _infoField(
                'Municipality / City',
                Icons.apartment_outlined,
                _municipalityController,
              ),
              _gap(),
              _infoField('Barangay', Icons.place_outlined, _barangayController),
              _gap(),
              _infoField('Street', Icons.route_outlined, _streetController),
              _gap(),
              _infoField(
                'House / Unit',
                Icons.home_outlined,
                _houseUnitController,
              ),
            ]),

            const SizedBox(height: 28),

            SizedBox(
              width: double.infinity,
              height: 54,
              child: ElevatedButton.icon(
                onPressed: _isSaving
                    ? null
                    : () {
                        if (_isEditing) {
                          _saveChanges();
                        } else {
                          setState(() => _isEditing = true);
                        }
                      },
                icon: _isSaving
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Icon(
                        _isEditing ? Icons.save_outlined : Icons.edit_outlined,
                      ),
                label: Text(
                  _isSaving
                      ? 'Saving...'
                      : _isEditing
                      ? 'Save Changes'
                      : 'Edit Information',
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: vanDarkMaroon,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(18),
                  ),
                ),
              ),
            ),

            if (_isEditing) ...[
              const SizedBox(height: 8),
              Center(
                child: TextButton(
                  onPressed: _isSaving ? null : _cancelEditing,
                  child: const Text(
                    'Cancel Editing',
                    style: TextStyle(
                      color: vanMaroon,
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

  Widget _buildAvatar() {
    const double size = 76;
    final path = widget.photoPath;
    const Widget fallback = Icon(Icons.person, size: 43, color: vanDarkMaroon);

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: vanLightBeige,
        border: Border.all(color: Colors.white, width: 3),
      ),
      child: ClipOval(
        child: path == null
            ? const Center(child: fallback)
            : Image.file(
                File(path),
                width: size,
                height: size,
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) =>
                    const Center(child: fallback),
              ),
      ),
    );
  }

  Widget _buildProfileHeader() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 22),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [vanDarkMaroon, vanMaroon],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(25),
        boxShadow: [
          BoxShadow(
            color: vanDarkMaroon.withValues(alpha: 0.14),
            blurRadius: 15,
            offset: const Offset(0, 7),
          ),
        ],
      ),
      child: Row(
        children: [
          _buildAvatar(),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _nameController.text.trim().isEmpty
                      ? 'Van Rider'
                      : _nameController.text.trim(),
                  style: const TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Lumear Logistics',
                  style: TextStyle(fontSize: 13, color: vanLightBeige),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Personal Account',
                  style: TextStyle(fontSize: 12, color: Colors.white70),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionTitle(String title, String subtitle) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: vanDarkMaroon,
          ),
        ),
        const SizedBox(height: 5),
        Text(
          subtitle,
          style: const TextStyle(
            color: vanBrownBeige,
            fontSize: 12,
            height: 1.4,
          ),
        ),
      ],
    );
  }

  Widget _card(List<Widget> children) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.85),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: vanLightBeige.withValues(alpha: 0.5)),
      ),
      child: Column(children: children),
    );
  }

  Widget _birthdayField() {
    return TextField(
      controller: _birthdayController,
      readOnly: true,
      onTap: _isSaving ? null : _selectBirthday,
      style: const TextStyle(
        color: vanDarkMaroon,
        fontSize: 13,
        fontWeight: FontWeight.w600,
      ),
      decoration: InputDecoration(
        labelText: 'Birthday',
        hintText: 'YYYY-MM-DD',
        labelStyle: const TextStyle(color: vanBrownBeige, fontSize: 12),
        prefixIcon: Container(
          margin: const EdgeInsets.all(8),
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: vanLightBeige.withValues(alpha: 0.35),
            borderRadius: BorderRadius.circular(12),
          ),
          child: const Icon(
            Icons.cake_outlined,
            color: vanDarkMaroon,
            size: 19,
          ),
        ),
        suffixIcon: const Icon(
          Icons.calendar_month_outlined,
          color: vanDarkMaroon,
          size: 19,
        ),
        filled: true,
        fillColor: _isEditing ? Colors.white : vanCream.withValues(alpha: 0.45),
        disabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: vanLightBeige.withValues(alpha: 0.8)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: const BorderSide(color: vanDarkMaroon, width: 1.6),
        ),
      ),
    );
  }

  Widget _infoField(
    String label,
    IconData icon,
    TextEditingController controller, {
    TextInputType keyboardType = TextInputType.text,
    bool readOnly = false,
  }) {
    return TextField(
      controller: controller,
      enabled: _isEditing,
      readOnly: readOnly,
      keyboardType: keyboardType,
      style: const TextStyle(
        color: vanDarkMaroon,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: const TextStyle(color: vanBrownBeige, fontSize: 12),
        floatingLabelStyle: const TextStyle(
          color: vanDarkMaroon,
          fontWeight: FontWeight.w600,
        ),
        prefixIcon: Container(
          margin: const EdgeInsets.all(9),
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: vanLightBeige.withValues(alpha: 0.35),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, color: vanDarkMaroon, size: 20),
        ),
        filled: true,
        fillColor: (_isEditing && !readOnly)
            ? Colors.white
            : vanCream.withValues(alpha: 0.45),
        disabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: vanLightBeige.withValues(alpha: 0.8)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: const BorderSide(color: vanDarkMaroon, width: 1.6),
        ),
      ),
    );
  }

  Widget _gap() => const SizedBox(height: 16);
}

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_application_1/models/truck_profile_store.dart';
import 'package:flutter_application_1/models/truck_rider_profile.dart';

class TruckPersonalInformationScreen extends StatefulWidget {
  final TruckRiderProfile profile;
  final ValueChanged<TruckRiderProfile>? onSaved;

  const TruckPersonalInformationScreen({
    super.key,
    required this.profile,
    this.onSaved,
  });

  @override
  State<TruckPersonalInformationScreen> createState() =>
      _TruckPersonalInformationScreenState();
}

class _TruckPersonalInformationScreenState
    extends State<TruckPersonalInformationScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);

  bool _isEditing = false;

  // Latest profile (kept in sync with whatever the parent passes in).
  late TruckRiderProfile _profile;

  // Controllers
  late final TextEditingController _nameController;
  late final TextEditingController _emailController;
  late final TextEditingController _phoneController;
  late final TextEditingController _birthdayController;
  late final TextEditingController _ageController;
  late final TextEditingController _regionController;
  late final TextEditingController _provinceController;
  late final TextEditingController _municipalityController;
  late final TextEditingController _barangayController;
  late final TextEditingController _streetController;
  late final TextEditingController _houseUnitController;
  late final TextEditingController _licenseController;
  late final TextEditingController _plateController;

  DateTime? _birthday;

  @override
  void initState() {
    super.initState();
    _profile = TruckProfileStore.resolve(widget.profile);
    _nameController = TextEditingController();
    _emailController = TextEditingController();
    _phoneController = TextEditingController();
    _birthdayController = TextEditingController();
    _ageController = TextEditingController();
    _regionController = TextEditingController();
    _provinceController = TextEditingController();
    _municipalityController = TextEditingController();
    _barangayController = TextEditingController();
    _streetController = TextEditingController();
    _houseUnitController = TextEditingController();
    _licenseController = TextEditingController();
    _plateController = TextEditingController();
    _loadProfile();
    TruckProfileStore.current.addListener(_onStoreChanged);
  }

  // Vehicle Information was saved: refresh only the locked vehicle fields,
  // so any unsaved personal edits on this screen are left untouched.
  void _onStoreChanged() {
    final s = TruckProfileStore.current.value;
    if (!mounted || s == null || s.riderId != _profile.riderId) return;

    setState(() {
      _profile = _profile.copyWith(
        licenseNumber: s.licenseNumber,
        plateNumber: s.plateNumber,
        licenseImagePath: s.licenseImagePath,
        orImagePath: s.orImagePath,
        crImagePath: s.crImagePath,
      );
      _licenseController.text = s.licenseNumber;
      _plateController.text = s.plateNumber;
    });
  }

  // When the parent rebuilds this screen with a newer profile
  // (e.g. vehicle info was edited elsewhere), refresh what is shown.
  @override
  void didUpdateWidget(covariant TruckPersonalInformationScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!identical(oldWidget.profile, widget.profile)) {
      _profile = TruckProfileStore.resolve(widget.profile);
      _loadProfile();
    }
  }

  @override
  void dispose() {
    TruckProfileStore.current.removeListener(_onStoreChanged);
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _birthdayController.dispose();
    _ageController.dispose();
    _regionController.dispose();
    _provinceController.dispose();
    _municipalityController.dispose();
    _barangayController.dispose();
    _streetController.dispose();
    _houseUnitController.dispose();
    _licenseController.dispose();
    _plateController.dispose();
    super.dispose();
  }

  // ------------------------------------------------------------
  // HELPERS
  // ------------------------------------------------------------

  void _loadProfile() {
    final p = _profile;
    final Object? age = p.age;

    _nameController.text = p.fullName;
    _emailController.text = p.email;
    _phoneController.text = p.phone;
    _regionController.text = p.region;
    _provinceController.text = p.province;
    _municipalityController.text = p.municipality;
    _barangayController.text = p.barangay;
    _streetController.text = p.street;
    _houseUnitController.text = p.houseUnit;
    _licenseController.text = p.licenseNumber;
    _plateController.text = p.plateNumber;

    _birthday = p.birthday;
    _birthdayController.text = _formatDate(_birthday);
    _ageController.text = _birthday != null
        ? calcAge(_birthday!).toString()
        : (age?.toString() ?? '');
  }

  String _formatDate(DateTime? date) {
    if (date == null) return '';
    final String m = date.month.toString().padLeft(2, '0');
    final String d = date.day.toString().padLeft(2, '0');
    return '${date.year}-$m-$d';
  }

  int calcAge(DateTime birthDate) {
    final now = DateTime.now();
    int age = now.year - birthDate.year;
    if (now.month < birthDate.month ||
        (now.month == birthDate.month && now.day < birthDate.day)) {
      age--;
    }
    return age;
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

  Future<void> _pickBirthday() async {
    if (!_isEditing) return;

    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _birthday ?? DateTime(now.year - 25, now.month, now.day),
      firstDate: DateTime(1900),
      lastDate: now,
      builder: (context, child) => Theme(
        data: Theme.of(context).copyWith(
          colorScheme: const ColorScheme.light(
            primary: darkMaroon,
            onPrimary: Colors.white,
            surface: cream,
            onSurface: darkMaroon,
          ),
        ),
        child: child!,
      ),
    );

    if (picked == null || !mounted) return;

    setState(() {
      _birthday = picked;
      _birthdayController.text = _formatDate(picked);
      _ageController.text = calcAge(picked).toString();
    });
  }

  // ------------------------------------------------------------
  // SAVE / CANCEL
  // ------------------------------------------------------------

  void _saveChanges() {
    FocusScope.of(context).unfocus();

    if (_nameController.text.trim().isEmpty) {
      _showMessage('Please enter your full name.');
      return;
    }
    // License and plate are NOT validated or changed here.
    // They are managed in Vehicle Information.

    // Build the updated profile object using copyWith
    final updated = _profile.copyWith(
      riderId: _profile.riderId,
      fullName: _nameController.text.trim(),
      email: _emailController.text.trim(),
      phone: _phoneController.text.trim(),
      birthday: _birthday,
      age: _birthday != null ? calcAge(_birthday!) : _profile.age,
      region: _regionController.text.trim(),
      province: _provinceController.text.trim(),
      municipality: _municipalityController.text.trim(),
      barangay: _barangayController.text.trim(),
      street: _streetController.text.trim(),
      houseUnit: _houseUnitController.text.trim(),
      vehicleType: _profile.vehicleType,
      licenseNumber: _profile.licenseNumber,
      plateNumber: _profile.plateNumber,
      accountStatus: _profile.accountStatus,
      licenseImagePath: _profile.licenseImagePath,
      orImagePath: _profile.orImagePath,
      crImagePath: _profile.crImagePath,
    );

    // Share with Vehicle Information (and anything else listening).
    TruckProfileStore.update(updated);

    widget.onSaved?.call(updated);

    _showMessage('Changes saved successfully!', success: true);
    Navigator.pop(context, updated);
  }

  void _cancelEditing() {
    FocusScope.of(context).unfocus();

    setState(() {
      _loadProfile();
      _isEditing = false;
    });
  }

  // ------------------------------------------------------------
  // BUILD SCREEN
  // ------------------------------------------------------------

  @override
  Widget build(BuildContext context) {
    final p = _profile;

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
          'Personal Information',
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
                    radius: 41,
                    backgroundColor: lightBeige,
                    child: Icon(Icons.person, size: 45, color: darkMaroon),
                  ),
                  const SizedBox(width: 18),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          p.fullName.trim().isEmpty
                              ? 'Rider Information'
                              : p.fullName,
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: Colors.white,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          p.riderId.trim().isEmpty
                              ? 'Registered Rider'
                              : 'Rider ID: ${p.riderId}',
                          style: const TextStyle(
                            fontSize: 12,
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

            // PERSONAL DETAILS
            _sectionTitle(
              'Personal Details',
              'Information from your rider account.',
            ),
            const SizedBox(height: 18),
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
                  Expanded(
                    child: _infoField(
                      'Birthday',
                      Icons.cake_outlined,
                      _birthdayController,
                      hintText: 'YYYY-MM-DD',
                      readOnly: true,
                      onTap: _pickBirthday,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _infoField(
                      'Age',
                      Icons.numbers_outlined,
                      _ageController,
                      readOnly: true,
                      locked: true,
                    ),
                  ),
                ],
              ),
            ]),

            const SizedBox(height: 28),

            // ADDRESS
            _sectionTitle(
              'Address',
              'Your registered delivery service address.',
            ),
            const SizedBox(height: 18),
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

            // VEHICLE DETAILS (locked: edit these in Vehicle Information)
            _sectionTitle(
              'Vehicle Details',
              'Managed in Vehicle Information. Edit them there.',
            ),
            const SizedBox(height: 18),
            _card([
              _infoField(
                "Driver's License Number",
                Icons.badge_outlined,
                _licenseController,
                readOnly: true,
                locked: true,
              ),
              _gap(),
              _infoField(
                'Plate Number',
                Icons.pin_outlined,
                _plateController,
                readOnly: true,
                locked: true,
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

  // ------------------------------------------------------------
  // REUSABLE WIDGETS
  // ------------------------------------------------------------

  Widget _sectionTitle(String title, String subtitle) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: darkMaroon,
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
      child: Column(children: children),
    );
  }

  Widget _gap() => const SizedBox(height: 16);

  Widget _infoField(
    String label,
    IconData icon,
    TextEditingController controller, {
    TextInputType keyboardType = TextInputType.text,
    TextCapitalization capitalization = TextCapitalization.none,
    List<TextInputFormatter>? inputFormatters,
    String? hintText,
    bool readOnly = false,
    bool locked = false,
    VoidCallback? onTap,
  }) {
    final bool canEdit = _isEditing && !locked;

    return TextField(
      controller: controller,
      enabled: canEdit,
      readOnly: readOnly,
      onTap: onTap,
      keyboardType: keyboardType,
      textCapitalization: capitalization,
      inputFormatters: inputFormatters,
      style: const TextStyle(
        color: darkMaroon,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      decoration: InputDecoration(
        labelText: label,
        hintText: hintText,
        labelStyle: const TextStyle(color: brownBeige, fontSize: 12),
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
        suffixIcon: locked
            ? const Icon(Icons.lock_outline, color: brownBeige, size: 18)
            : null,
        filled: true,
        fillColor: canEdit ? Colors.white : cream.withValues(alpha: 0.45),
        disabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide(color: lightBeige.withValues(alpha: 0.8)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: const BorderSide(color: darkMaroon, width: 1.6),
        ),
      ),
    );
  }
}
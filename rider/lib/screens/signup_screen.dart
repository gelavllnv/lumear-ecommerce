import 'dart:convert';
import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_application_1/models/rider_profile.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

import 'login_screen.dart' hide RiderProfile;
import 'terms_conditions_screen.dart';

class _PhLocalNumberFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    String digits = newValue.text.replaceAll(RegExp(r'[^0-9]'), '');

    // Since +63 is already shown as a fixed prefix, remove the habitual
    // Philippine local-format leading 0 (e.g. 09171234567 -> 9171234567).
    if (digits.startsWith('0')) {
      digits = digits.substring(1);
    }

    // PH mobile numbers after +63 should contain 10 digits.
    if (digits.length > 10) {
      digits = digits.substring(0, 10);
    }

    return TextEditingValue(
      text: digits,
      selection: TextSelection.collapsed(offset: digits.length),
    );
  }
}

// ============================================================
// NAME INPUT FORMATTER
//
// Only letters (including accented ones such as ñ), spaces,
// apostrophes, hyphens and periods are allowed. Numbers and other
// symbols are removed as the user types or pastes, and leading or
// double spaces are cleaned up automatically.
// ============================================================
class _NameInputFormatter extends TextInputFormatter {
  static final RegExp _allowedChar = RegExp(r"[A-Za-zÀ-ÖØ-öø-ÿ .'\-]");

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    String text = newValue.text
        .split('')
        .where((char) => _allowedChar.hasMatch(char))
        .join();

    // No leading space and no double spaces.
    text = text.replaceFirst(RegExp(r'^\s+'), '');
    text = text.replaceAll(RegExp(r'\s{2,}'), ' ');

    if (text == newValue.text) {
      return newValue;
    }

    final int removed = newValue.text.length - text.length;
    final int offset = (newValue.selection.end - removed).clamp(0, text.length);

    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: offset),
    );
  }
}

class SignUpScreen extends StatefulWidget {
  const SignUpScreen({super.key});

  @override
  State<SignUpScreen> createState() => _SignUpScreenState();
}

class _SignUpScreenState extends State<SignUpScreen> {
  int _currentStep = 0;

  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;
  bool _isLoading = false;
  bool _agreeToTerms = false;

  // ============================================================
  // NAME FIELDS (first, middle, last)
  // ============================================================
  final TextEditingController _firstNameController = TextEditingController();
  final TextEditingController _middleNameController = TextEditingController();
  final TextEditingController _lastNameController = TextEditingController();

  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _ageController = TextEditingController();

  final TextEditingController _streetController = TextEditingController();
  final TextEditingController _houseUnitController = TextEditingController();

  final TextEditingController _passwordController = TextEditingController();
  final TextEditingController _confirmPasswordController =
      TextEditingController();

  final TextEditingController _licenseNumberController =
      TextEditingController();
  final TextEditingController _plateNumberController = TextEditingController();

  DateTime? _selectedBirthday;

  // ============================================================
  // NAME VALIDATION
  //
  // Accepted : Juan, Maria Clara, Dela Cruz, O'Brien, Ana-Lyn, Cruz Jr.
  //            S, S. (middle initial only)
  // Rejected : Juan2, 123, Juan_, anything with digits or symbols
  // ============================================================
  static final RegExp _nameRegex = RegExp(
    r"^[A-Za-zÀ-ÖØ-öø-ÿ]+(?:[ '.\-]+[A-Za-zÀ-ÖØ-öø-ÿ]+)*\.?$",
  );

  // A single letter with an optional dot, e.g. "S" or "S.".
  static final RegExp _initialRegex = RegExp(r'^[A-Za-zÀ-ÖØ-öø-ÿ]\.?$');

  /// Full name built from the three name fields, e.g. "Juan Santos Dela Cruz".
  /// A one-letter middle name is shown as an initial with a dot ("S.").
  String get _fullName {
    final String first = _firstNameController.text.trim();
    String middle = _middleNameController.text.trim();
    final String last = _lastNameController.text.trim();

    if (middle.length == 1) {
      middle = '$middle.';
    }

    return [first, middle, last].where((part) => part.isNotEmpty).join(' ');
  }

  // ============================================================
  // SEX
  // ============================================================
  String? _selectedSex;

  final List<Map<String, dynamic>> _sexOptions = const [
    {'label': 'Male', 'icon': Icons.male_rounded},
    {'label': 'Female', 'icon': Icons.female_rounded},
    {'label': 'Other', 'icon': Icons.transgender_rounded},
  ];

  // ============================================================
  // STRICT EMAIL VALIDATION
  //
  // Accepted : Example@gmail.com, first.last@mail.example.com
  // Rejected : @gmail.com        (nothing before the @)
  //            example@ gmail.com (space)
  //            example@gmail      (no domain extension)
  //            example@gmail..com (double dot)
  //            .example@gmail.com (starts with a dot)
  // ============================================================
  static final RegExp _emailRegex = RegExp(
    r'^[A-Za-z0-9]+([._%+\-][A-Za-z0-9]+)*'
    r'@'
    r'[A-Za-z0-9]+(-[A-Za-z0-9]+)*(\.[A-Za-z0-9]+(-[A-Za-z0-9]+)*)*'
    r'\.[A-Za-z]{2,}$',
  );

  // ============================================================
  // ALLOWED DOCUMENT FILE TYPES
  // ============================================================
  static const List<String> _licenseExtensions = ['png', 'jpg', 'jpeg'];
  static const List<String> _documentExtensions = ['pdf', 'doc', 'docx'];

  String? _selectedRegion;
  String? _selectedProvince;
  String? _selectedMunicipality;
  String? _selectedBarangay;

  // Preferred logistics hub selected during rider registration.
  String? _selectedLogisticsCenter;

  // PSGC codes are kept internally for API requests.
  String? _selectedRegionCode;
  String? _selectedProvinceCode;
  String? _selectedMunicipalityCode;

  List<Map<String, String>> _regionItems = [];
  List<Map<String, String>> _provinceItems = [];
  List<Map<String, String>> _municipalityItems = [];
  List<Map<String, String>> _barangayItems = [];

  bool _isLoadingRegions = false;
  bool _isLoadingProvinces = false;
  bool _isLoadingMunicipalities = false;
  bool _isLoadingBarangays = false;

  bool _regionLoadFailed = false;
  bool _provinceLoadFailed = false;
  bool _municipalityLoadFailed = false;
  bool _barangayLoadFailed = false;

  static const String _psgcBaseUrl = 'https://psgc.cloud/api/v2';

  String? _selectedVehicleType;

  // ============================================================
  // JOB TYPE
  // Vehicle decides which jobs are possible; the job decides which
  // dashboard the rider lands on.
  //
  //   Motorcycle / Tricycle -> Delivery (automatic, no question)
  //   Van                   -> Seller Pickup  or  Delivery
  //   Truck                 -> Long-distance Handover  or  Delivery
  //                            (large items)
  // ============================================================
  static const String jobSellerPickup = 'Seller Pickup';
  static const String jobLongDistance = 'Long-distance Handover';
  static const String jobDelivery = 'Delivery';

  String? _selectedJobType;

  static const Map<String, List<Map<String, dynamic>>> _jobOptions = {
    'Van': [
      {
        'value': jobSellerPickup,
        'label': 'Seller Pickup',
        'description': 'Collect parcels from sellers and bring them to the sorting center.',
        'icon': Icons.storefront_outlined,
      },
      {
        'value': jobDelivery,
        'label': 'Delivery',
        'description': 'Deliver parcels from the sorting center to customers.',
        'icon': Icons.delivery_dining_outlined,
      },
    ],
    'Truck': [
      {
        'value': jobLongDistance,
        'label': 'Long-distance Handover',
        'description': 'Transport parcels between hubs over long distances.',
        'icon': Icons.route_outlined,
      },
      {
        'value': jobDelivery,
        'label': 'Delivery (Large Items)',
        'description': 'Deliver large or bulky items from the sorting center to customers.',
        'icon': Icons.local_shipping_outlined,
      },
    ],
  };

  /// Van and Truck riders must choose a job. The other vehicles are
  /// always Delivery.
  bool get _needsJobChoice => _jobOptions.containsKey(_selectedVehicleType);

  /// Label shown for the chosen job in the review card.
  String _jobDisplayLabel() {
    final String? vehicle = _selectedVehicleType;
    final String? job = _selectedJobType;

    if (vehicle == null || job == null) return 'Not selected';

    final List<Map<String, dynamic>>? options = _jobOptions[vehicle];

    if (options != null) {
      for (final option in options) {
        if (option['value'] == job) {
          return option['label'] as String;
        }
      }
    }

    return job;
  }

  final ImagePicker _picker = ImagePicker();

  // Driver's license is a photo (PNG / JPG). OR and CR are documents
  // (PDF / DOC / DOCX), so they are stored as plain files.
  XFile? _licenseImage;
  File? _orFile;
  File? _crFile;

  OverlayEntry? _topNotificationEntry;

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);

  final List<Map<String, dynamic>> _vehicleTypes = const [
    {'label': 'Motorcycle', 'icon': Icons.two_wheeler_outlined},
    {'label': 'Tricycle', 'icon': Icons.electric_rickshaw_outlined},
    {'label': 'Van', 'icon': Icons.airport_shuttle_outlined},
    {'label': 'Truck', 'icon': Icons.local_shipping_outlined},
  ];

  @override
  void initState() {
    super.initState();
    _fetchRegions();
  }

  List<String> get _regions =>
      _regionItems.map((item) => item['name']!).toList();

  List<String> get _provinces =>
      _provinceItems.map((item) => item['name']!).toList();

  List<String> get _municipalities =>
      _municipalityItems.map((item) => item['name']!).toList();

  List<String> get _barangays =>
      _barangayItems.map((item) => item['name']!).toList();

  String? _codeForName(List<Map<String, String>> items, String? name) {
    if (name == null) return null;

    for (final item in items) {
      if (item['name'] == name) {
        return item['code'];
      }
    }

    return null;
  }

  String _cleanPlaceName(String text) {
    String cleaned = text.trim();

    // Fix common mojibake such as "BiÃ±an" -> "Biñan".
    // Run at most twice in case the API text was encoded incorrectly more than once.
    for (int i = 0; i < 2; i++) {
      if (!(cleaned.contains('Ã') ||
          cleaned.contains('Â') ||
          cleaned.contains('â'))) {
        break;
      }

      try {
        final String repaired = utf8.decode(latin1.encode(cleaned));
        if (repaired == cleaned) break;
        cleaned = repaired;
      } catch (_) {
        break;
      }
    }

    // Final fallback for the characters commonly found in Philippine place names.
    return cleaned
        .replaceAll('Ã±', 'ñ')
        .replaceAll('Ã‘', 'Ñ')
        .replaceAll('Ã¡', 'á')
        .replaceAll('Ã©', 'é')
        .replaceAll('Ã­', 'í')
        .replaceAll('Ã³', 'ó')
        .replaceAll('Ãº', 'ú');
  }

  Future<List<Map<String, String>>> _fetchPsgcList(String url) async {
    debugPrint('PSGC GET: $url');

    final http.Response response = await http
        .get(Uri.parse(url), headers: const {'Accept': 'application/json'})
        .timeout(const Duration(seconds: 15));

    debugPrint('PSGC STATUS: ${response.statusCode}');

    if (response.statusCode != 200) {
      throw HttpException(
        'PSGC request failed with status ${response.statusCode}.',
      );
    }

    final dynamic decoded = jsonDecode(utf8.decode(response.bodyBytes));

    // The PSGC v2 API wraps list responses as {"data": [...]},
    // so unwrap that before validating it as a list.
    final dynamic items = decoded is Map && decoded['data'] is List
        ? decoded['data']
        : decoded;

    if (items is! List) {
      throw const FormatException('Unexpected PSGC response format.');
    }

    return items
        .whereType<Map>()
        .map<Map<String, String>>((dynamic item) {
          final Map<dynamic, dynamic> map = item as Map<dynamic, dynamic>;

          return {
            'code': map['code']?.toString() ?? '',
            'name': _cleanPlaceName(map['name']?.toString() ?? ''),
          };
        })
        .where(
          (item) =>
              item['code']!.trim().isNotEmpty &&
              item['name']!.trim().isNotEmpty,
        )
        .toList()
      ..sort((a, b) => a['name']!.compareTo(b['name']!));
  }

  Future<void> _fetchRegions() async {
    if (_isLoadingRegions) return;

    setState(() {
      _isLoadingRegions = true;
      _regionLoadFailed = false;
    });

    try {
      final items = await _fetchPsgcList('$_psgcBaseUrl/regions');

      if (!mounted) return;

      setState(() {
        _regionItems = items;
        _isLoadingRegions = false;
        _regionLoadFailed = items.isEmpty;
      });

      debugPrint('REGIONS LOADED: ${items.length}');
    } catch (e) {
      if (!mounted) return;

      setState(() {
        _isLoadingRegions = false;
        _regionLoadFailed = true;
      });

      debugPrint('Unable to load PSGC regions: $e');
      _showMessage(
        'Unable to load regions. Check your internet connection.',
        icon: Icons.wifi_off_rounded,
      );
    }
  }

  Future<void> _fetchProvinces() async {
    final String? regionCode = _selectedRegionCode;

    if (regionCode == null || _isLoadingProvinces) return;

    setState(() {
      _isLoadingProvinces = true;
      _provinceLoadFailed = false;
      _municipalityLoadFailed = false;
      _barangayLoadFailed = false;
      _provinceItems = [];
      _municipalityItems = [];
      _barangayItems = [];
    });

    try {
      final items = await _fetchPsgcList(
        '$_psgcBaseUrl/regions/$regionCode/provinces',
      );

      if (!mounted || _selectedRegionCode != regionCode) return;

      if (items.isEmpty) {
        // Some PSGC regions, such as NCR, do not have provinces.
        // We keep a synthetic province entry so your existing backend field
        // remains complete while cities/municipalities are loaded by region.
        setState(() {
          _provinceItems = [
            {'code': regionCode, 'name': _selectedRegion ?? 'No Province'},
          ];
          _selectedProvince = _selectedRegion ?? 'No Province';
          _selectedProvinceCode = regionCode;
          _isLoadingProvinces = false;
        });

        await _fetchMunicipalities(loadByRegion: true);
        return;
      }

      setState(() {
        _provinceItems = items;
        _isLoadingProvinces = false;
        _provinceLoadFailed = false;
      });
    } catch (e) {
      if (!mounted || _selectedRegionCode != regionCode) return;

      setState(() {
        _isLoadingProvinces = false;
        _provinceLoadFailed = true;
      });

      debugPrint('Unable to load PSGC provinces: $e');
      _showMessage(
        'Unable to load provinces. Please try again.',
        icon: Icons.wifi_off_rounded,
      );
    }
  }

  Future<void> _fetchMunicipalities({bool loadByRegion = false}) async {
    final String? regionCode = _selectedRegionCode;
    final String? provinceCode = _selectedProvinceCode;

    if (regionCode == null || _isLoadingMunicipalities) return;

    if (!loadByRegion && provinceCode == null) return;

    setState(() {
      _isLoadingMunicipalities = true;
      _municipalityLoadFailed = false;
      _barangayLoadFailed = false;
      _municipalityItems = [];
      _barangayItems = [];
    });

    try {
      final String url = loadByRegion
          ? '$_psgcBaseUrl/regions/$regionCode/cities-municipalities'
          : '$_psgcBaseUrl/provinces/$provinceCode/cities-municipalities';

      final items = await _fetchPsgcList(url);

      if (!mounted || _selectedRegionCode != regionCode) return;

      if (!loadByRegion && _selectedProvinceCode != provinceCode) return;

      setState(() {
        _municipalityItems = items;
        _isLoadingMunicipalities = false;
        _municipalityLoadFailed = items.isEmpty;
      });
    } catch (e) {
      if (!mounted || _selectedRegionCode != regionCode) return;

      setState(() {
        _isLoadingMunicipalities = false;
        _municipalityLoadFailed = true;
      });

      debugPrint('Unable to load PSGC cities/municipalities: $e');
      _showMessage(
        'Unable to load cities or municipalities. Please try again.',
        icon: Icons.wifi_off_rounded,
      );
    }
  }

  Future<void> _fetchBarangays() async {
    final String? municipalityCode = _selectedMunicipalityCode;

    if (municipalityCode == null || _isLoadingBarangays) return;

    setState(() {
      _isLoadingBarangays = true;
      _barangayLoadFailed = false;
      _barangayItems = [];
    });

    try {
      final items = await _fetchPsgcList(
        '$_psgcBaseUrl/cities-municipalities/$municipalityCode/barangays',
      );

      if (!mounted || _selectedMunicipalityCode != municipalityCode) return;

      setState(() {
        _barangayItems = items;
        _isLoadingBarangays = false;
        _barangayLoadFailed = items.isEmpty;
      });
    } catch (e) {
      if (!mounted || _selectedMunicipalityCode != municipalityCode) return;

      setState(() {
        _isLoadingBarangays = false;
        _barangayLoadFailed = true;
      });

      debugPrint('Unable to load PSGC barangays: $e');
      _showMessage(
        'Unable to load barangays. Please try again.',
        icon: Icons.wifi_off_rounded,
      );
    }
  }

  @override
  void dispose() {
    _firstNameController.dispose();
    _middleNameController.dispose();
    _lastNameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _ageController.dispose();
    _streetController.dispose();
    _houseUnitController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    _licenseNumberController.dispose();
    _plateNumberController.dispose();

    _topNotificationEntry?.remove();

    super.dispose();
  }

  Future<void> _pickBirthday() async {
    final DateTime now = DateTime.now();

    final DateTime? pickedDate = await showDatePicker(
      context: context,
      initialDate:
          _selectedBirthday ?? DateTime(now.year - 18, now.month, now.day),
      firstDate: DateTime(1940),
      lastDate: now,
      helpText: 'Select your birthday',
      cancelText: 'CANCEL',
      confirmText: 'SELECT',
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: maroon,
              onPrimary: Colors.white,
              onSurface: darkMaroon,
            ),
          ),
          child: child!,
        );
      },
    );

    if (pickedDate == null) {
      return;
    }

    final int age = _calculateAge(pickedDate);

    setState(() {
      _selectedBirthday = pickedDate;
      _ageController.text = age.toString();
    });
  }

  int _calculateAge(DateTime birthday) {
    final DateTime today = DateTime.now();

    int age = today.year - birthday.year;

    if (today.month < birthday.month ||
        (today.month == birthday.month && today.day < birthday.day)) {
      age--;
    }

    return age;
  }

  String _formatBirthday(DateTime date) {
    final List<String> months = [
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

  // ============================================================
  // FILE HELPERS
  // ============================================================

  String _extensionOf(String path) {
    final int dot = path.lastIndexOf('.');

    if (dot == -1 || dot == path.length - 1) return '';

    return path.substring(dot + 1).toLowerCase();
  }

  String _fileNameOf(String path) {
    return path.split(RegExp(r'[/\\]')).last;
  }

  // ============================================================
  // PASSWORD STRENGTH
  //
  //   level 0 = empty
  //   level 1 = Weak   (also anything under 8 characters)
  //   level 2 = Fair
  //   level 3 = Good
  //   level 4 = Strong
  // ============================================================

  int _passwordStrengthLevel(String password) {
    if (password.isEmpty) return 0;
    if (password.length < 8) return 1;

    int score = 1; // The 8-character minimum is met.

    if (password.length >= 12) score++;

    if (RegExp(r'[a-z]').hasMatch(password) &&
        RegExp(r'[A-Z]').hasMatch(password)) {
      score++;
    }

    if (RegExp(r'[0-9]').hasMatch(password)) score++;

    if (RegExp(r'[^A-Za-z0-9]').hasMatch(password)) score++;

    return score >= 4 ? 4 : score;
  }

  String _passwordStrengthLabel(String password) {
    if (password.isEmpty) return 'Enter a password';
    if (password.length < 8) return 'Too short';

    switch (_passwordStrengthLevel(password)) {
      case 1:
        return 'Weak';
      case 2:
        return 'Fair';
      case 3:
        return 'Good';
      default:
        return 'Strong';
    }
  }

  Color _passwordStrengthColor(int level) {
    switch (level) {
      case 1:
        return const Color(0xFFC62828);
      case 2:
        return const Color(0xFFEF6C00);
      case 3:
        return const Color(0xFF9E9D24);
      case 4:
        return const Color(0xFF62191C);
      default:
        return lightBeige;
    }
  }

  void _nextStep() {
    FocusScope.of(context).unfocus();

    if (_currentStep == 0) {
      if (!_validatePersonal()) {
        return;
      }

      setState(() {
        _currentStep = 1;
      });

      return;
    }

    if (_currentStep == 1) {
      if (!_validateAddress()) {
        return;
      }

      setState(() {
        _currentStep = 2;
      });

      return;
    }

    if (_currentStep == 2) {
      if (!_validateSecurity()) {
        return;
      }

      setState(() {
        _currentStep = 3;
      });

      return;
    }

    if (_currentStep == 3) {
      if (!_validateRider()) {
        return;
      }

      setState(() {
        _currentStep = 4;
      });

      return;
    }

    if (_currentStep == 4) {
      _signUp();
    }
  }

  void _previousStep() {
    FocusScope.of(context).unfocus();

    if (_currentStep > 0) {
      setState(() {
        _currentStep--;
      });
    }
  }

  // ============================================================
  // NAME CHECK
  // Returns an error message, or null when the name is valid.
  //
  // allowInitial: when true, a single letter with or without a dot
  // ("S" or "S.") is accepted. Used for the middle name only.
  // ============================================================
  String? _nameError(
    String value,
    String label, {
    bool required = true,
    bool allowInitial = false,
  }) {
    final String name = value.trim();

    if (name.isEmpty) {
      return required ? 'Please enter your $label.' : null;
    }

    if (name.contains(RegExp(r'[0-9]'))) {
      return 'Your $label must not contain numbers.';
    }

    final int letterCount = name
        .replaceAll(RegExp(r"[^A-Za-zÀ-ÖØ-öø-ÿ]"), '')
        .length;

    // A single letter is only allowed when it is an initial,
    // with or without a dot (e.g. "S" or "S.").
    final bool isSingleInitial = allowInitial && _initialRegex.hasMatch(name);

    if (!isSingleInitial && letterCount < 2) {
      return 'Your $label must have at least 2 letters.';
    }

    if (!_nameRegex.hasMatch(name)) {
      return 'Your $label may only contain letters, spaces, hyphens, apostrophes and periods.';
    }

    return null;
  }

  bool _validatePersonal() {
    final String email = _emailController.text.trim();
    final String phone = _phoneController.text.trim();

    final String? firstNameError = _nameError(
      _firstNameController.text,
      'first name',
    );

    if (firstNameError != null) {
      _showMessage(firstNameError, icon: Icons.person_outline);

      return false;
    }

    // Middle name is optional, but if it is filled in it must be valid.
    // A single initial such as "S" or "S." is allowed.
    final String? middleNameError = _nameError(
      _middleNameController.text,
      'middle name',
      required: false,
      allowInitial: true,
    );

    if (middleNameError != null) {
      _showMessage(middleNameError, icon: Icons.person_outline);

      return false;
    }

    final String? lastNameError = _nameError(
      _lastNameController.text,
      'last name',
    );

    if (lastNameError != null) {
      _showMessage(lastNameError, icon: Icons.person_outline);

      return false;
    }

    if (email.isEmpty) {
      _showMessage(
        'Please enter your email address.',
        icon: Icons.email_outlined,
      );

      return false;
    }

    if (email.contains(RegExp(r'\s'))) {
      _showMessage(
        'Email address must not contain spaces.',
        icon: Icons.email_outlined,
      );

      return false;
    }

    if (email.startsWith('@')) {
      _showMessage(
        'Please enter your email name before the @ (example: name@gmail.com).',
        icon: Icons.email_outlined,
      );

      return false;
    }

    if (!_emailRegex.hasMatch(email)) {
      _showMessage(
        'Please enter a valid email address (example: name@gmail.com).',
        icon: Icons.email_outlined,
      );

      return false;
    }

    if (phone.isEmpty) {
      _showMessage(
        'Please enter your phone number.',
        icon: Icons.phone_outlined,
      );

      return false;
    }

    if (phone.length != 10 || !phone.startsWith('9')) {
      _showMessage(
        'Please enter a valid PH mobile number. It should start with 9 after +63.',
        icon: Icons.phone_outlined,
      );

      return false;
    }

    if (_selectedSex == null) {
      _showMessage('Please select your sex.', icon: Icons.wc_outlined);

      return false;
    }

    if (_selectedBirthday == null) {
      _showMessage('Please select your birthday.', icon: Icons.cake_outlined);

      return false;
    }

    if (_calculateAge(_selectedBirthday!) < 18) {
      _showMessage(
        'You must be at least 18 years old to continue.',
        icon: Icons.cake_outlined,
      );

      return false;
    }

    return true;
  }

  bool _validateAddress() {
    if (_selectedRegion == null) {
      _showMessage('Please select your region.', icon: Icons.map_outlined);

      return false;
    }

    if (_selectedProvince == null) {
      _showMessage(
        'Please select your province.',
        icon: Icons.location_city_outlined,
      );

      return false;
    }

    if (_selectedMunicipality == null) {
      _showMessage(
        'Please select your municipality or city.',
        icon: Icons.apartment_outlined,
      );

      return false;
    }

    if (_selectedBarangay == null) {
      _showMessage('Please select your barangay.', icon: Icons.place_outlined);

      return false;
    }

    if (_streetController.text.trim().isEmpty) {
      _showMessage('Please enter your street.', icon: Icons.home_outlined);

      return false;
    }

    if (_houseUnitController.text.trim().isEmpty) {
      _showMessage(
        'Please enter your house or unit number.',
        icon: Icons.home_outlined,
      );

      return false;
    }

    if (_selectedLogisticsCenter == null) {
      _showMessage(
        'Please select your preferred logistics hub.',
        icon: Icons.local_shipping_outlined,
      );

      return false;
    }

    return true;
  }

  bool _validateSecurity() {
    final String password = _passwordController.text;

    final String confirmPassword = _confirmPasswordController.text;

    if (password.isEmpty) {
      _showMessage('Please create a password.', icon: Icons.lock_outline);

      return false;
    }

    if (password.length < 8) {
      _showMessage(
        'Password must contain at least 8 characters.',
        icon: Icons.lock_outline,
      );

      return false;
    }

    if (confirmPassword.isEmpty) {
      _showMessage(
        'Please confirm your password.',
        icon: Icons.lock_reset_outlined,
      );

      return false;
    }

    if (password != confirmPassword) {
      _showMessage(
        'Passwords do not match.',
        icon: Icons.warning_amber_rounded,
      );

      return false;
    }

    return true;
  }

  bool _validateRider() {
    if (_selectedVehicleType == null) {
      _showMessage(
        'Please select your vehicle type.',
        icon: Icons.two_wheeler_outlined,
      );

      return false;
    }

    if (_needsJobChoice && _selectedJobType == null) {
      _showMessage(
        'Please choose what you will use your $_selectedVehicleType for.',
        icon: Icons.work_outline_rounded,
      );

      return false;
    }

    if (_licenseNumberController.text.trim().isEmpty) {
      _showMessage(
        "Please enter your driver's license number.",
        icon: Icons.badge_outlined,
      );

      return false;
    }

    if (_plateNumberController.text.trim().isEmpty) {
      _showMessage('Please enter your plate number.', icon: Icons.pin_outlined);

      return false;
    }

    return true;
  }

  bool _validateDocuments() {
    if (_licenseImage == null) {
      _showMessage(
        "Please upload your driver's license (PNG or JPG).",
        icon: Icons.badge_outlined,
      );

      return false;
    }

    if (_orFile == null) {
      _showMessage(
        'Please upload your OR (PDF, DOC or DOCX).',
        icon: Icons.description_outlined,
      );

      return false;
    }

    if (_crFile == null) {
      _showMessage(
        'Please upload your CR (PDF, DOC or DOCX).',
        icon: Icons.description_outlined,
      );

      return false;
    }

    return true;
  }

  // ============================================================
  // DRIVER'S LICENSE: PHOTO ONLY (PNG / JPG)
  // ============================================================
  Future<void> _pickLicensePhoto() async {
    final ImageSource? source = await showModalBottomSheet<ImageSource>(
      context: context,
      backgroundColor: cream,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.only(top: 8, bottom: 12),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 42,
                  height: 4,
                  decoration: BoxDecoration(
                    color: lightBeige,
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),

                const SizedBox(height: 16),

                const Text(
                  "Upload Driver's License",
                  style: TextStyle(
                    color: Color(0xFF62191C),
                    fontSize: 17,
                    fontWeight: FontWeight.bold,
                  ),
                ),

                const SizedBox(height: 3),

                const Text(
                  'Accepted formats: PNG or JPG',
                  style: TextStyle(color: brownBeige, fontSize: 11),
                ),

                const SizedBox(height: 8),

                ListTile(
                  leading: const Icon(
                    Icons.camera_alt_outlined,
                    color: darkMaroon,
                  ),
                  title: const Text(
                    'Take Photo',
                    style: TextStyle(
                      color: darkMaroon,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  onTap: () {
                    Navigator.pop(sheetContext, ImageSource.camera);
                  },
                ),

                ListTile(
                  leading: const Icon(
                    Icons.photo_library_outlined,
                    color: darkMaroon,
                  ),
                  title: const Text(
                    'Choose from Gallery',
                    style: TextStyle(
                      color: darkMaroon,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  onTap: () {
                    Navigator.pop(sheetContext, ImageSource.gallery);
                  },
                ),
              ],
            ),
          ),
        );
      },
    );

    if (source == null) {
      return;
    }

    try {
      final XFile? image = await _picker.pickImage(
        source: source,
        imageQuality: 80,
      );

      if (image == null || !mounted) {
        return;
      }

      String extension = _extensionOf(image.path);

      if (extension.isEmpty) {
        extension = _extensionOf(image.name);
      }

      if (!_licenseExtensions.contains(extension)) {
        _showMessage(
          "Driver's license must be a PNG or JPG image.",
          icon: Icons.image_not_supported_outlined,
        );

        return;
      }

      setState(() {
        _licenseImage = image;
      });
    } catch (e) {
      if (!mounted) {
        return;
      }

      _showMessage('Unable to capture the document photo.');
    }
  }

  // ============================================================
  // OR / CR: DOCUMENT FILE ONLY (PDF / DOC / DOCX)
  // ============================================================
  Future<void> _pickDocumentFile({required String documentType}) async {
    try {
      final PlatformFile? picked = await FilePicker.pickFile(
        type: FileType.custom,
        allowedExtensions: _documentExtensions,
      );

      if (picked == null || !mounted) {
        return;
      }

      final String? path = picked.path;

      if (path == null) {
        _showMessage('Unable to read the selected file.');
        return;
      }

      String extension = _extensionOf(path);

      if (extension.isEmpty) {
        extension = _extensionOf(picked.name);
      }

      if (!_documentExtensions.contains(extension)) {
        _showMessage(
          'Only PDF, DOC or DOCX files are accepted.',
          icon: Icons.description_outlined,
        );

        return;
      }

      setState(() {
        if (documentType == 'or') {
          _orFile = File(path);
        } else if (documentType == 'cr') {
          _crFile = File(path);
        }
      });
    } catch (e) {
      if (!mounted) {
        return;
      }

      debugPrint('Document pick error: $e');
      _showMessage('Unable to select the document file.');
    }
  }

  // ============================================================
  // SIGN UP (local only)
  // The Node.js signup API was removed. The rider profile is now
  // saved locally and no data is sent to any server.
  // ============================================================
  Future<void> _signUp() async {
    if (_isLoading) return;

    if (!_validateDocuments()) {
      return;
    }

    if (!_agreeToTerms) {
      _showMessage(
        'Please agree to the Terms & Conditions before submitting.',
        icon: Icons.gavel_rounded,
      );
      return;
    }

    if (_selectedSex == null ||
        _selectedBirthday == null ||
        _selectedRegion == null ||
        _selectedProvince == null ||
        _selectedMunicipality == null ||
        _selectedBarangay == null ||
        _selectedLogisticsCenter == null ||
        _selectedVehicleType == null ||
        _selectedJobType == null ||
        _licenseImage == null ||
        _orFile == null ||
        _crFile == null) {
      _showMessage(
        'Please complete all registration information.',
        icon: Icons.warning_amber_rounded,
      );
      return;
    }

    FocusScope.of(context).unfocus();

    try {
      // No server request: the rider profile is saved locally only.
      _saveLocalRiderProfile();
      RiderProfile.status = 'Pending verification';

      if (!mounted) return;

      _showPendingVerificationDialog();
    } catch (e) {
      if (!mounted) return;

      _showMessage(
        'Something went wrong while saving your registration. Please try again.',
        icon: Icons.error_outline_rounded,
      );

      debugPrint('Rider signup error: $e');
    }
  }

  void _saveLocalRiderProfile() {
    // The three name fields are combined into one full name, so the rest
    // of the app (which reads RiderProfile.name) keeps working unchanged.
    RiderProfile.name = _fullName;
    RiderProfile.email = _emailController.text.trim();
    RiderProfile.phone = '+63 ${_phoneController.text.trim()}';
    RiderProfile.sex = _selectedSex!;
    RiderProfile.birthday = _formatBirthday(_selectedBirthday!);
    RiderProfile.age = int.tryParse(_ageController.text.trim());
    RiderProfile.region = _selectedRegion!;
    RiderProfile.province = _selectedProvince!;
    RiderProfile.municipality = _selectedMunicipality!;
    RiderProfile.barangay = _selectedBarangay!;
    RiderProfile.street = _streetController.text.trim();
    RiderProfile.houseUnit = _houseUnitController.text.trim();
    RiderProfile.vehicleType = _selectedVehicleType!;
    RiderProfile.jobType = _selectedJobType!;
    RiderProfile.licenseNumber = _licenseNumberController.text.trim();
    RiderProfile.plateNumber = _plateNumberController.text.trim();
    RiderProfile.localDriverLicenseFile = File(_licenseImage!.path);
    RiderProfile.localOrFile = _orFile!;
    RiderProfile.localCrFile = _crFile!;
    RiderProfile.status = 'Local demo profile';
  }

  void _showMessage(String message, {IconData? icon}) {
    _topNotificationEntry?.remove();
    _topNotificationEntry = null;

    final OverlayState overlay = Overlay.of(context);

    late OverlayEntry entry;

    entry = OverlayEntry(
      builder: (context) {
        return Positioned(
          top: MediaQuery.of(context).padding.top + 10,
          left: 16,
          right: 16,
          child: Material(
            color: Colors.transparent,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              decoration: BoxDecoration(
                color: darkMaroon,
                borderRadius: BorderRadius.circular(14),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.18),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Row(
                children: [
                  Icon(
                    icon ?? Icons.info_outline,
                    color: Colors.white,
                    size: 20,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      message,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );

    _topNotificationEntry = entry;
    overlay.insert(entry);

    Future.delayed(const Duration(seconds: 2), () {
      if (_topNotificationEntry == entry) {
        entry.remove();
        _topNotificationEntry = null;
      }
    });
  }

  InputDecoration _inputDecoration({
    required String hint,
    required IconData icon,
    Widget? suffixIcon,
  }) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: Color(0xFF9E7161), fontSize: 12),
      prefixIcon: Icon(icon, color: maroon, size: 20),
      suffixIcon: suffixIcon,
      filled: true,
      fillColor: Colors.white.withValues(alpha: 0.58),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide.none,
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: brownBeige.withValues(alpha: 0.35)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: maroon, width: 1.5),
      ),
    );
  }

  Widget _textField({
    required TextEditingController controller,
    required String hint,
    required IconData icon,
    TextInputType? keyboardType,
    TextCapitalization textCapitalization = TextCapitalization.none,
    bool obscureText = false,
    Widget? suffixIcon,
    bool readOnly = false,
    String? fixedPrefix,
    List<TextInputFormatter>? inputFormatters,
    ValueChanged<String>? onChanged,
  }) {
    if (fixedPrefix == null) {
      return SizedBox(
        height: 60,
        child: TextField(
          controller: controller,
          keyboardType: keyboardType,
          textCapitalization: textCapitalization,
          obscureText: obscureText,
          readOnly: readOnly,
          inputFormatters: inputFormatters,
          onChanged: onChanged,
          style: const TextStyle(color: darkMaroon, fontSize: 13),
          decoration: _inputDecoration(
            hint: hint,
            icon: icon,
            suffixIcon: suffixIcon,
          ),
        ),
      );
    }

    return Container(
      height: 60,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.58),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: brownBeige.withValues(alpha: 0.35)),
      ),
      child: Row(
        children: [
          const SizedBox(width: 12),
          Icon(icon, color: maroon, size: 20),
          const SizedBox(width: 10),
          Text(
            fixedPrefix,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(width: 8),
          Container(
            width: 1,
            height: 24,
            color: brownBeige.withValues(alpha: 0.35),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: TextField(
              controller: controller,
              keyboardType: keyboardType,
              textCapitalization: textCapitalization,
              obscureText: obscureText,
              readOnly: readOnly,
              inputFormatters: inputFormatters,
              onChanged: onChanged,
              style: const TextStyle(color: darkMaroon, fontSize: 13),
              decoration: InputDecoration(
                hintText: hint,
                hintStyle: const TextStyle(color: brownBeige, fontSize: 12),
                suffixIcon: suffixIcon,
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                isDense: true,
                contentPadding: const EdgeInsets.symmetric(vertical: 20),
              ),
            ),
          ),
          const SizedBox(width: 8),
        ],
      ),
    );
  }

  InputDecoration _flatDecoration(String hint) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: brownBeige, fontSize: 13),
      filled: true,
      fillColor: lightBeige.withValues(alpha: 0.30),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide.none,
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: BorderSide.none,
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(16),
        borderSide: const BorderSide(color: maroon, width: 1.4),
      ),
    );
  }

  Widget _flatTextField({
    required TextEditingController controller,
    required String hint,
    TextCapitalization textCapitalization = TextCapitalization.none,
  }) {
    return SizedBox(
      height: 60,
      child: TextField(
        controller: controller,
        textCapitalization: textCapitalization,
        style: const TextStyle(color: darkMaroon, fontSize: 13),
        decoration: _flatDecoration(hint),
      ),
    );
  }

  // FIX: previously wrapped in a fixed SizedBox(height: 56), which was a few
  // pixels shorter than what DropdownButtonFormField's InputDecorator needs
  // once you factor in the icon + selected item text, causing the
  // "bottom overflowed by 16 pixels" error. Now it's only width-constrained
  // and sized naturally via isDense + tighter content padding, so it never
  // overflows its box while still visually matching the other fields.
  Widget _flatDropdownField({
    required String hint,
    required String? value,
    required List<String> items,
    required ValueChanged<String?> onChanged,
  }) {
    return SizedBox(
      width: double.infinity,
      child: DropdownButtonFormField<String>(
        initialValue: value,
        isExpanded: true,
        isDense: true,
        menuMaxHeight: 360,
        borderRadius: BorderRadius.circular(18),
        alignment: AlignmentDirectional.centerStart,
        padding: const EdgeInsets.symmetric(horizontal: 2),
        decoration: _flatDecoration(hint).copyWith(
          isDense: true,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 16,
            vertical: 14,
          ),
        ),
        icon: const Padding(
          padding: EdgeInsets.only(right: 4),
          child: Icon(
            Icons.keyboard_arrow_down_rounded,
            color: maroon,
            size: 24,
          ),
        ),
        dropdownColor: cream,
        style: const TextStyle(
          color: darkMaroon,
          fontSize: 13,
          fontWeight: FontWeight.w500,
        ),
        selectedItemBuilder: (context) {
          return items.map((item) {
            return Align(
              alignment: Alignment.centerLeft,
              child: Text(
                item,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: darkMaroon,
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            );
          }).toList();
        },
        items: items.map((item) {
          return DropdownMenuItem<String>(
            value: item,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
              child: Text(
                item,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: darkMaroon,
                  fontSize: 13,
                  height: 1.25,
                ),
              ),
            ),
          );
        }).toList(),
        onChanged: items.isEmpty ? null : onChanged,
      ),
    );
  }

  Widget _buildHeader() {
    return const Text(
      'Create your account',
      style: TextStyle(
        color: darkMaroon,
        fontSize: 25,
        fontWeight: FontWeight.bold,
      ),
    );
  }

  Widget _buildLoginPrompt() {
    return GestureDetector(
      onTap: () {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (context) => const LoginScreen()),
        );
      },
      child: const Padding(
        padding: EdgeInsets.only(top: 3),
        child: Text.rich(
          TextSpan(
            style: TextStyle(color: brownBeige, fontSize: 13),
            children: [
              TextSpan(text: 'Already have an account? '),
              TextSpan(
                text: 'Login',
                style: TextStyle(
                  color: darkMaroon,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStepIndicator() {
    final List<String> labels = [
      'Personal',
      'Address',
      'Security',
      'Rider',
      'Verify',
    ];

    return Row(
      children: List.generate(labels.length, (index) {
        return Expanded(
          child: Row(
            children: [
              Expanded(child: _stepCircle(index, labels[index])),
              if (index < labels.length - 1)
                Expanded(
                  child: Container(
                    height: 2,
                    margin: const EdgeInsets.only(bottom: 17),
                    color: _currentStep > index ? maroon : lightBeige,
                  ),
                ),
            ],
          ),
        );
      }),
    );
  }

  Widget _stepCircle(int index, String label) {
    final bool completed = _currentStep > index;
    final bool active = _currentStep == index;

    return Column(
      children: [
        AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          width: 29,
          height: 29,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: completed || active
                ? maroon
                : Colors.white.withValues(alpha: 0.45),
            border: Border.all(
              color: completed || active ? maroon : lightBeige,
            ),
          ),
          child: completed
              ? const Icon(Icons.check_rounded, color: Colors.white, size: 17)
              : Text(
                  '${index + 1}',
                  style: TextStyle(
                    color: active ? Colors.white : brownBeige,
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                  ),
                ),
        ),
        const SizedBox(height: 4),
        FittedBox(
          child: Text(
            label,
            style: TextStyle(
              color: active || completed ? maroon : brownBeige,
              fontSize: 9,
              fontWeight: active ? FontWeight.bold : FontWeight.normal,
            ),
          ),
        ),
      ],
    );
  }

  Widget _stepTitle(String title, String subtitle) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            color: darkMaroon,
            fontSize: 17,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 3),
        Text(subtitle, style: const TextStyle(color: brownBeige, fontSize: 11)),
      ],
    );
  }

  // ============================================================
  // SEX SELECTOR (Male / Female / Other)
  // ============================================================
  Widget _buildSexSelector() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.only(left: 4, bottom: 6),
          child: Text(
            'Sex',
            style: TextStyle(
              color: brownBeige,
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
        Row(
          children: List.generate(_sexOptions.length, (index) {
            final Map<String, dynamic> option = _sexOptions[index];
            final String label = option['label'] as String;
            final IconData icon = option['icon'] as IconData;
            final bool selected = _selectedSex == label;

            return Expanded(
              child: Padding(
                padding: EdgeInsets.only(
                  right: index < _sexOptions.length - 1 ? 8 : 0,
                ),
                child: InkWell(
                  onTap: () {
                    setState(() {
                      _selectedSex = label;
                    });
                  },
                  borderRadius: BorderRadius.circular(13),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    height: 48,
                    padding: const EdgeInsets.symmetric(horizontal: 6),
                    decoration: BoxDecoration(
                      color: selected
                          ? maroon
                          : Colors.white.withValues(alpha: 0.5),
                      borderRadius: BorderRadius.circular(13),
                      border: Border.all(color: selected ? maroon : lightBeige),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          icon,
                          color: selected ? Colors.white : darkMaroon,
                          size: 19,
                        ),
                        const SizedBox(width: 5),
                        Flexible(
                          child: Text(
                            label,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: selected ? Colors.white : darkMaroon,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            );
          }),
        ),
      ],
    );
  }

  Widget _buildPersonalStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _stepTitle('Personal Information', 'Tell us a little about yourself.'),
        const SizedBox(height: 14),

        // FIRST NAME
        _textField(
          controller: _firstNameController,
          hint: 'First Name',
          icon: Icons.person_outline,
          textCapitalization: TextCapitalization.words,
          keyboardType: TextInputType.name,
          // Letters only: numbers are removed as the user types or pastes.
          inputFormatters: [_NameInputFormatter()],
        ),
        const SizedBox(height: 10),

        // MIDDLE NAME (optional, a single initial like "S" or "S." is OK)
        _textField(
          controller: _middleNameController,
          hint: 'Middle Name or Initial (optional)',
          icon: Icons.person_outline,
          textCapitalization: TextCapitalization.words,
          keyboardType: TextInputType.name,
          inputFormatters: [_NameInputFormatter()],
        ),
        const SizedBox(height: 10),

        // LAST NAME
        _textField(
          controller: _lastNameController,
          hint: 'Last Name',
          icon: Icons.person_outline,
          textCapitalization: TextCapitalization.words,
          keyboardType: TextInputType.name,
          inputFormatters: [_NameInputFormatter()],
        ),
        const SizedBox(height: 10),

        _textField(
          controller: _emailController,
          hint: 'Email Address (example@gmail.com)',
          icon: Icons.email_outlined,
          keyboardType: TextInputType.emailAddress,
          // Spaces are never valid in an email address.
          inputFormatters: [FilteringTextInputFormatter.deny(RegExp(r'\s'))],
        ),
        const SizedBox(height: 10),
        _textField(
          controller: _phoneController,
          hint: '9123456789',
          icon: Icons.phone_outlined,
          keyboardType: TextInputType.phone,
          fixedPrefix: '+63',
          inputFormatters: [_PhLocalNumberFormatter()],
        ),
        const SizedBox(height: 12),
        _buildSexSelector(),
        const SizedBox(height: 12),
        InkWell(
          onTap: _pickBirthday,
          borderRadius: BorderRadius.circular(14),
          child: Container(
            height: 60,
            padding: const EdgeInsets.symmetric(horizontal: 14),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.58),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: brownBeige.withValues(alpha: 0.35)),
            ),
            child: Row(
              children: [
                const Icon(Icons.cake_outlined, color: maroon, size: 20),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    _selectedBirthday == null
                        ? 'Birthday'
                        : _formatBirthday(_selectedBirthday!),
                    style: TextStyle(
                      color: _selectedBirthday == null
                          ? brownBeige
                          : darkMaroon,
                      fontSize: 12,
                    ),
                  ),
                ),
                const Icon(
                  Icons.calendar_month_outlined,
                  color: maroon,
                  size: 20,
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 10),
        _textField(
          controller: _ageController,
          hint: 'Age is calculated automatically',
          icon: Icons.person_search_outlined,
          readOnly: true,
        ),
        // Extra breathing room so the last field never sits flush against
        // the keyboard when this step scrolls.
        const SizedBox(height: 8),
      ],
    );
  }

  Widget _buildAddressStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _stepTitle(
          'Residential Address',
          'Select your complete rider address.',
        ),
        const SizedBox(height: 14),

        if (_isLoadingRegions && _regionItems.isEmpty)
          _locationLoadingField('Loading regions...')
        else if (_regionLoadFailed && _regionItems.isEmpty)
          _locationRetryField(
            text: 'Unable to load regions. Tap to retry.',
            onTap: _fetchRegions,
          )
        else
          _flatDropdownField(
            hint: 'Region*',
            value: _selectedRegion,
            items: _regions,
            onChanged: (value) async {
              setState(() {
                _selectedRegion = value;
                _selectedRegionCode = _codeForName(_regionItems, value);

                _selectedProvince = null;
                _selectedProvinceCode = null;

                _selectedMunicipality = null;
                _selectedMunicipalityCode = null;

                _selectedBarangay = null;

                _provinceItems = [];
                _municipalityItems = [];
                _barangayItems = [];

                _provinceLoadFailed = false;
                _municipalityLoadFailed = false;
                _barangayLoadFailed = false;
              });

              if (value != null) {
                await _fetchProvinces();
              }
            },
          ),

        const SizedBox(height: 10),

        if (_isLoadingProvinces)
          _locationLoadingField('Loading provinces...')
        else if (_provinceLoadFailed)
          _locationRetryField(
            text: 'Unable to load provinces. Tap to retry.',
            onTap: _fetchProvinces,
          )
        else
          _flatDropdownField(
            hint: _selectedRegion == null ? 'Select region first' : 'Province*',
            value: _selectedProvince,
            items: _provinces,
            onChanged: (value) async {
              setState(() {
                _selectedProvince = value;
                _selectedProvinceCode = _codeForName(_provinceItems, value);

                _selectedMunicipality = null;
                _selectedMunicipalityCode = null;

                _selectedBarangay = null;

                _municipalityItems = [];
                _barangayItems = [];
              });

              if (value != null) {
                final bool provinceUsesRegionCode =
                    _selectedProvinceCode == _selectedRegionCode &&
                    _provinceItems.length == 1;

                await _fetchMunicipalities(
                  loadByRegion: provinceUsesRegionCode,
                );
              }
            },
          ),

        const SizedBox(height: 10),

        if (_isLoadingMunicipalities)
          _locationLoadingField('Loading cities / municipalities...')
        else if (_municipalityLoadFailed)
          _locationRetryField(
            text: 'Unable to load cities / municipalities. Tap to retry.',
            onTap: () async {
              final bool loadByRegion =
                  _selectedProvinceCode == _selectedRegionCode &&
                  _provinceItems.length == 1;
              await _fetchMunicipalities(loadByRegion: loadByRegion);
            },
          )
        else
          _flatDropdownField(
            hint: _selectedProvince == null
                ? 'Select province first'
                : 'City / Municipality*',
            value: _selectedMunicipality,
            items: _municipalities,
            onChanged: (value) async {
              setState(() {
                _selectedMunicipality = value;
                _selectedMunicipalityCode = _codeForName(
                  _municipalityItems,
                  value,
                );

                _selectedBarangay = null;
                _barangayItems = [];
              });

              if (value != null) {
                await _fetchBarangays();
              }
            },
          ),

        const SizedBox(height: 10),

        if (_isLoadingBarangays)
          _locationLoadingField('Loading barangays...')
        else if (_barangayLoadFailed)
          _locationRetryField(
            text: 'Unable to load barangays. Tap to retry.',
            onTap: _fetchBarangays,
          )
        else
          _flatDropdownField(
            hint: _selectedMunicipality == null
                ? 'Select city / municipality first'
                : 'Barangay*',
            value: _selectedBarangay,
            items: _barangays,
            onChanged: (value) {
              setState(() {
                _selectedBarangay = value;
              });
            },
          ),

        const SizedBox(height: 10),

        _flatTextField(
          controller: _streetController,
          hint: 'Street*',
          textCapitalization: TextCapitalization.words,
        ),

        const SizedBox(height: 10),

        _flatTextField(
          controller: _houseUnitController,
          hint: 'House / unit no.*',
          textCapitalization: TextCapitalization.words,
        ),

        const SizedBox(height: 10),

        _flatDropdownField(
          hint: 'Preferred Logistics Hub*',
          value: _selectedLogisticsCenter,
          items: const [
            'Victoria Hub',
            'Calamba Logistics Hub',
            'No preference — assign me later',
          ],
          onChanged: (value) {
            setState(() {
              _selectedLogisticsCenter = value;
            });
          },
        ),

        const SizedBox(height: 5),
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 4),
          child: Text(
            'Choose your preferred hub. Logistics may confirm or change your assignment.',
            style: TextStyle(color: brownBeige, fontSize: 10, height: 1.35),
          ),
        ),
        const SizedBox(height: 8),
      ],
    );
  }

  Widget _locationLoadingField(String text) {
    return Container(
      height: 60,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: lightBeige.withValues(alpha: 0.30),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          const SizedBox(
            width: 18,
            height: 18,
            child: CircularProgressIndicator(strokeWidth: 2, color: maroon),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(color: brownBeige, fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }

  Widget _locationRetryField({
    required String text,
    required Future<void> Function() onTap,
  }) {
    return InkWell(
      onTap: () {
        onTap();
      },
      borderRadius: BorderRadius.circular(16),
      child: Container(
        height: 60,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.45),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: maroon.withValues(alpha: 0.25)),
        ),
        child: Row(
          children: [
            const Icon(Icons.refresh_rounded, color: maroon, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                text,
                style: const TextStyle(
                  color: maroon,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // PASSWORD STRENGTH METER
  // ============================================================
  Widget _buildPasswordStrengthMeter() {
    final String password = _passwordController.text;
    final int level = _passwordStrengthLevel(password);
    final Color color = _passwordStrengthColor(level);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Row(
                children: List.generate(4, (index) {
                  return Expanded(
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 200),
                      height: 6,
                      margin: EdgeInsets.only(right: index < 3 ? 5 : 0),
                      decoration: BoxDecoration(
                        color: index < level
                            ? color
                            : lightBeige.withValues(alpha: 0.6),
                        borderRadius: BorderRadius.circular(4),
                      ),
                    ),
                  );
                }),
              ),
            ),
            const SizedBox(width: 12),
            Text(
              _passwordStrengthLabel(password),
              style: TextStyle(
                color: password.isEmpty ? brownBeige : color,
                fontSize: 12,
                fontWeight: FontWeight.bold,
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 8,
          runSpacing: 6,
          children: [
            _requirementChip('8+ characters', password.length >= 8),
            _requirementChip('Uppercase', RegExp(r'[A-Z]').hasMatch(password)),
            _requirementChip('Lowercase', RegExp(r'[a-z]').hasMatch(password)),
            _requirementChip('Number', RegExp(r'[0-9]').hasMatch(password)),
            _requirementChip(
              'Symbol',
              RegExp(r'[^A-Za-z0-9]').hasMatch(password),
            ),
          ],
        ),
      ],
    );
  }

  Widget _requirementChip(String text, bool met) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(
          met ? Icons.check_circle_rounded : Icons.radio_button_unchecked,
          size: 14,
          color: met ? const Color(0xFF9E7161) : brownBeige,
        ),
        const SizedBox(width: 4),
        Text(
          text,
          style: TextStyle(
            color: met ? const Color(0xFF9E7161) : brownBeige,
            fontSize: 11,
            fontWeight: met ? FontWeight.w600 : FontWeight.normal,
          ),
        ),
      ],
    );
  }

  Widget _buildSecurityStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _stepTitle(
          'Account Security',
          'Create a secure password for your rider account.',
        ),
        const SizedBox(height: 22),
        _textField(
          controller: _passwordController,
          hint: 'Create Password (min. 8 characters)',
          icon: Icons.lock_outline,
          obscureText: _obscurePassword,
          // Rebuild so the strength meter updates while typing.
          onChanged: (_) {
            setState(() {});
          },
          suffixIcon: IconButton(
            onPressed: () {
              setState(() {
                _obscurePassword = !_obscurePassword;
              });
            },
            icon: Icon(
              _obscurePassword
                  ? Icons.visibility_off_outlined
                  : Icons.visibility_outlined,
              color: maroon,
              size: 20,
            ),
          ),
        ),
        const SizedBox(height: 12),
        _buildPasswordStrengthMeter(),
        const SizedBox(height: 14),
        _textField(
          controller: _confirmPasswordController,
          hint: 'Confirm Password',
          icon: Icons.lock_reset_outlined,
          obscureText: _obscureConfirmPassword,
          suffixIcon: IconButton(
            onPressed: () {
              setState(() {
                _obscureConfirmPassword = !_obscureConfirmPassword;
              });
            },
            icon: Icon(
              _obscureConfirmPassword
                  ? Icons.visibility_off_outlined
                  : Icons.visibility_outlined,
              color: maroon,
              size: 20,
            ),
          ),
        ),
        const SizedBox(height: 18),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.35),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: lightBeige),
          ),
          child: const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.shield_outlined, color: maroon, size: 20),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Use at least 8 characters. Mix uppercase and lowercase letters, numbers and symbols for a stronger password. Do not share your password with anyone.',
                  style: TextStyle(
                    color: brownBeige,
                    fontSize: 11,
                    height: 1.4,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
      ],
    );
  }

  Widget _buildRiderStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _stepTitle(
          'Rider & Vehicle',
          'Provide the vehicle you will use for deliveries.',
        ),
        const SizedBox(height: 14),
        _vehicleTypeSelector(),

        // Only Van and Truck riders are asked what the vehicle is for.
        // Motorcycle and Tricycle are always Delivery.
        AnimatedSize(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOut,
          alignment: Alignment.topCenter,
          child: _needsJobChoice
              ? _buildJobTypeSelector()
              : const SizedBox(width: double.infinity),
        ),

        const SizedBox(height: 14),
        _textField(
          controller: _licenseNumberController,
          hint: "Driver's License Number",
          icon: Icons.badge_outlined,
          textCapitalization: TextCapitalization.characters,
        ),
        const SizedBox(height: 10),
        _textField(
          controller: _plateNumberController,
          hint: 'Vehicle Plate Number',
          icon: Icons.pin_outlined,
          textCapitalization: TextCapitalization.characters,
        ),
        const SizedBox(height: 8),
      ],
    );
  }

  Widget _vehicleTypeSelector() {
    return LayoutBuilder(
      builder: (context, constraints) {
        const double spacing = 8;

        final double width = (constraints.maxWidth - spacing) / 2;

        return Wrap(
          spacing: spacing,
          runSpacing: 8,
          children: _vehicleTypes.map((vehicle) {
            final String label = vehicle['label'] as String;

            final IconData icon = vehicle['icon'] as IconData;

            final bool selected = _selectedVehicleType == label;

            return SizedBox(
              width: width,
              height: 48,
              child: InkWell(
                onTap: () {
                  setState(() {
                    // Changing the vehicle resets the job choice, because
                    // the available jobs depend on the vehicle.
                    if (_selectedVehicleType != label) {
                      _selectedVehicleType = label;
                      _selectedJobType = _jobOptions.containsKey(label)
                          ? null
                          : jobDelivery;
                    }
                    RiderProfile.vehicleType = label;
                  });
                },
                borderRadius: BorderRadius.circular(13),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  decoration: BoxDecoration(
                    color: selected
                        ? maroon
                        : Colors.white.withValues(alpha: 0.5),
                    borderRadius: BorderRadius.circular(13),
                    border: Border.all(color: selected ? maroon : lightBeige),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        icon,
                        color: selected ? Colors.white : darkMaroon,
                        size: 19,
                      ),
                      const SizedBox(width: 7),
                      Expanded(
                        child: Text(
                          label,
                          style: TextStyle(
                            color: selected ? Colors.white : darkMaroon,
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          }).toList(),
        );
      },
    );
  }

  // ============================================================
  // JOB TYPE SELECTOR (Van / Truck only)
  // ============================================================

  Widget _buildJobTypeSelector() {
    final String vehicle = _selectedVehicleType!;
    final List<Map<String, dynamic>> options = _jobOptions[vehicle]!;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 16),
        Text(
          'What will you use your $vehicle for?',
          style: const TextStyle(
            color: darkMaroon,
            fontSize: 13,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 3),
        const Text(
          'This decides which dashboard you will use. It can only be '
          'changed by the admin later.',
          style: TextStyle(color: brownBeige, fontSize: 10, height: 1.4),
        ),
        const SizedBox(height: 10),
        ...options.map((option) {
          final String value = option['value'] as String;
          final String label = option['label'] as String;
          final String description = option['description'] as String;
          final IconData icon = option['icon'] as IconData;
          final bool selected = _selectedJobType == value;

          return Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: InkWell(
              onTap: () {
                setState(() {
                  _selectedJobType = value;
                });
              },
              borderRadius: BorderRadius.circular(14),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: selected
                      ? maroon.withValues(alpha: 0.10)
                      : Colors.white.withValues(alpha: 0.5),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: selected ? maroon : lightBeige,
                    width: selected ? 1.5 : 1,
                  ),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: selected
                            ? maroon
                            : lightBeige.withValues(alpha: 0.45),
                        borderRadius: BorderRadius.circular(11),
                      ),
                      child: Icon(
                        icon,
                        color: selected ? Colors.white : darkMaroon,
                        size: 21,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            label,
                            style: const TextStyle(
                              color: darkMaroon,
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            description,
                            style: const TextStyle(
                              color: brownBeige,
                              fontSize: 10,
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Icon(
                      selected
                          ? Icons.radio_button_checked_rounded
                          : Icons.radio_button_unchecked_rounded,
                      color: selected ? maroon : lightBeige,
                      size: 21,
                    ),
                  ],
                ),
              ),
            ),
          );
        }),
      ],
    );
  }

  // Short review of the vehicle and job shown on the last step so the
  // rider can confirm their choice before submitting.
  Widget _buildJobSummaryCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: lightBeige),
      ),
      child: Row(
        children: [
          const Icon(Icons.work_outline_rounded, color: maroon, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Vehicle & Job',
                  style: TextStyle(color: brownBeige, fontSize: 10),
                ),
                const SizedBox(height: 2),
                Text(
                  '${_selectedVehicleType ?? '-'}  •  ${_jobDisplayLabel()}',
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildVerificationStep() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _stepTitle(
          'Document Verification',
          'Upload your documents for admin approval.',
        ),
        const SizedBox(height: 14),
        _buildJobSummaryCard(),
        const SizedBox(height: 10),
        _compactDocumentCard(
          title: "Driver's License",
          subtitle: _licenseImage == null
              ? 'Photo only: PNG or JPG'
              : 'Photo uploaded',
          image: _licenseImage,
          icon: Icons.badge_outlined,
          onTap: _pickLicensePhoto,
        ),
        const SizedBox(height: 10),
        _documentFileCard(
          title: 'Official Receipt (OR)',
          hint: 'PDF, DOC or DOCX only',
          file: _orFile,
          icon: Icons.description_outlined,
          onTap: () {
            _pickDocumentFile(documentType: 'or');
          },
        ),
        const SizedBox(height: 10),
        _documentFileCard(
          title: 'Certificate of Registration (CR)',
          hint: 'PDF, DOC or DOCX only',
          file: _crFile,
          icon: Icons.article_outlined,
          onTap: () {
            _pickDocumentFile(documentType: 'cr');
          },
        ),
        const SizedBox(height: 12),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: lightBeige.withValues(alpha: 0.30),
            borderRadius: BorderRadius.circular(13),
          ),
          child: const Row(
            children: [
              Icon(
                Icons.admin_panel_settings_outlined,
                color: maroon,
                size: 19,
              ),
              SizedBox(width: 9),
              Expanded(
                child: Text(
                  'Your account will be reviewed by the admin after submission.',
                  style: TextStyle(color: brownBeige, fontSize: 10),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.35),
            borderRadius: BorderRadius.circular(13),
            border: Border.all(color: lightBeige),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              SizedBox(
                width: 32,
                height: 32,
                child: Checkbox(
                  value: _agreeToTerms,
                  activeColor: darkMaroon,
                  checkColor: Colors.white,
                  side: const BorderSide(color: brownBeige, width: 1.5),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(4),
                  ),
                  onChanged: _isLoading
                      ? null
                      : (value) {
                          setState(() {
                            _agreeToTerms = value ?? false;
                          });
                        },
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Wrap(
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    const Text(
                      'I agree to the ',
                      style: TextStyle(color: brownBeige, fontSize: 11),
                    ),
                    GestureDetector(
                      onTap: _isLoading
                          ? null
                          : () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (context) =>
                                      const TermsConditionsScreen(),
                                ),
                              );
                            },
                      child: const Text(
                        'Terms & Conditions',
                        style: TextStyle(
                          color: darkMaroon,
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                          decoration: TextDecoration.underline,
                          decorationColor: darkMaroon,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
      ],
    );
  }

  // Card for the driver's license (photo preview).
  Widget _compactDocumentCard({
    required String title,
    required String subtitle,
    required XFile? image,
    required IconData icon,
    required VoidCallback onTap,
  }) {
    final bool uploaded = image != null;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        height: 92,
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.50),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: uploaded ? Colors.green.withValues(alpha: 0.50) : lightBeige,
          ),
        ),
        child: Row(
          children: [
            if (uploaded)
              ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Image.file(
                  File(image.path),
                  width: 70,
                  height: 70,
                  fit: BoxFit.cover,
                ),
              )
            else
              Container(
                width: 70,
                height: 70,
                decoration: BoxDecoration(
                  color: lightBeige.withValues(alpha: 0.40),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, color: darkMaroon, size: 27),
              ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: darkMaroon,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    style: const TextStyle(color: brownBeige, fontSize: 10),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    uploaded ? 'Tap to change' : 'Tap to upload',
                    style: const TextStyle(
                      color: maroon,
                      fontSize: 10,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              uploaded ? Icons.check_circle : Icons.add_a_photo_outlined,
              color: uploaded ? Colors.green : maroon,
              size: 21,
            ),
          ],
        ),
      ),
    );
  }

  // Card for OR / CR (PDF, DOC, DOCX file, shown by file name).
  Widget _documentFileCard({
    required String title,
    required String hint,
    required File? file,
    required IconData icon,
    required VoidCallback onTap,
  }) {
    final bool uploaded = file != null;
    final String extension = uploaded ? _extensionOf(file.path) : '';

    final IconData fileIcon = !uploaded
        ? icon
        : (extension == 'pdf'
              ? Icons.picture_as_pdf_outlined
              : Icons.description_outlined);

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        height: 92,
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.50),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: uploaded ? Colors.green.withValues(alpha: 0.50) : lightBeige,
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 70,
              height: 70,
              decoration: BoxDecoration(
                color: uploaded
                    ? Colors.green.withValues(alpha: 0.12)
                    : lightBeige.withValues(alpha: 0.40),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(fileIcon, color: darkMaroon, size: 27),
                  if (uploaded) ...[
                    const SizedBox(height: 3),
                    Text(
                      extension.toUpperCase(),
                      style: const TextStyle(
                        color: darkMaroon,
                        fontSize: 9,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: darkMaroon,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    uploaded ? _fileNameOf(file.path) : hint,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: brownBeige, fontSize: 10),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    uploaded ? 'Tap to change' : 'Tap to upload',
                    style: const TextStyle(
                      color: maroon,
                      fontSize: 10,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              uploaded ? Icons.check_circle : Icons.upload_file_outlined,
              color: uploaded ? Colors.green : maroon,
              size: 21,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCurrentStep() {
    switch (_currentStep) {
      case 0:
        return _buildPersonalStep();
      case 1:
        return _buildAddressStep();
      case 2:
        return _buildSecurityStep();
      case 3:
        return _buildRiderStep();
      case 4:
        return _buildVerificationStep();
      default:
        return _buildPersonalStep();
    }
  }

  Widget _buildBottomButtons() {
    return Row(
      children: [
        if (_currentStep > 0)
          Expanded(
            child: SizedBox(
              height: 60,
              child: OutlinedButton(
                onPressed: _isLoading ? null : _previousStep,
                style: OutlinedButton.styleFrom(
                  backgroundColor: Colors.white.withValues(alpha: 0.55),
                  foregroundColor: darkMaroon,
                  side: BorderSide.none,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(28),
                  ),
                ),
                child: const Text(
                  'BACK',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
              ),
            ),
          ),
        if (_currentStep > 0) const SizedBox(width: 10),
        Expanded(
          child: SizedBox(
            height: 60,
            child: ElevatedButton(
              onPressed: _isLoading ? null : _nextStep,
              style: ElevatedButton.styleFrom(
                backgroundColor: maroon,
                foregroundColor: Colors.white,
                disabledBackgroundColor: brownBeige,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(28),
                ),
              ),
              child: _isLoading
                  ? const SizedBox(
                      width: 21,
                      height: 21,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : Text(
                      _currentStep == 4 ? 'SUBMIT' : 'CONTINUE',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
            ),
          ),
        ),
      ],
    );
  }

  void _showPendingVerificationDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return AlertDialog(
          backgroundColor: cream,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24),
          ),
          contentPadding: const EdgeInsets.all(24),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 75,
                height: 75,
                decoration: BoxDecoration(
                  color: lightBeige,
                  shape: BoxShape.circle,
                  border: Border.all(color: darkMaroon, width: 2),
                ),
                child: const Icon(
                  Icons.hourglass_top_rounded,
                  color: darkMaroon,
                  size: 38,
                ),
              ),
              const SizedBox(height: 18),
              const Text(
                'Registration Complete!',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: darkMaroon,
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Your rider registration form is complete on this device.',
                textAlign: TextAlign.center,
                style: TextStyle(color: brownBeige, fontSize: 12, height: 1.5),
              ),
              const SizedBox(height: 18),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: lightBeige.withValues(alpha: 0.50),
                  borderRadius: BorderRadius.circular(15),
                ),
                child: const Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      Icons.admin_panel_settings_outlined,
                      color: darkMaroon,
                      size: 27,
                    ),
                    SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Local Demo Registration',
                            style: TextStyle(
                              color: darkMaroon,
                              fontSize: 13,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          SizedBox(height: 5),
                          Text(
                            'Laravel submission is disabled for this screen. No rider data is being sent to the server.',
                            style: TextStyle(
                              color: maroon,
                              fontSize: 11,
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.pop(dialogContext);

                    Navigator.pushAndRemoveUntil(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const LoginScreen(),
                      ),
                      (route) => false,
                    );
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: darkMaroon,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: const Text(
                    'Back to Login',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      resizeToAvoidBottomInset: true,
      body: Stack(
        children: [
          // Decorative waves/lines matching the Login screen.
          const Positioned.fill(
            child: IgnorePointer(
              child: CustomPaint(painter: _SignUpDecorPainter()),
            ),
          ),

          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(24, 12, 24, 14),
              child: Column(
                children: [
                  SizedBox(
                    height: 42,
                    child: Row(
                      children: [
                        IconButton(
                          padding: EdgeInsets.zero,
                          alignment: Alignment.centerLeft,
                          onPressed: () {
                            if (_currentStep > 0) {
                              _previousStep();
                            } else {
                              Navigator.pop(context);
                            }
                          },
                          icon: const Icon(
                            Icons.arrow_back_ios_new_rounded,
                            color: darkMaroon,
                            size: 19,
                          ),
                        ),
                        const Spacer(),
                        Image.asset(
                          'assets/images/logo.png',
                          width: 42,
                          height: 42,
                          fit: BoxFit.contain,
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 6),

                  Align(alignment: Alignment.centerLeft, child: _buildHeader()),

                  const SizedBox(height: 18),

                  _buildStepIndicator(),

                  const SizedBox(height: 18),

                  // The step content scrolls internally, and the CONTINUE /
                  // BACK buttons below stay fixed in place.
                  Expanded(
                    child: SingleChildScrollView(
                      keyboardDismissBehavior:
                          ScrollViewKeyboardDismissBehavior.onDrag,
                      physics: const ClampingScrollPhysics(),
                      child: AnimatedSwitcher(
                        duration: const Duration(milliseconds: 220),
                        switchInCurve: Curves.easeOut,
                        switchOutCurve: Curves.easeIn,
                        child: Align(
                          key: ValueKey(_currentStep),
                          alignment: Alignment.topCenter,
                          child: _buildCurrentStep(),
                        ),
                      ),
                    ),
                  ),

                  const SizedBox(height: 10),

                  _buildBottomButtons(),

                  if (_currentStep == 0) ...[
                    const SizedBox(height: 8),
                    _buildLoginPrompt(),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ================================================================
// SIGN UP DECORATIVE BACKGROUND
// Same visual language as the Login screen: soft bottom waves
// plus subtle curved accent lines along the left and right edges.
// ================================================================
class _SignUpDecorPainter extends CustomPainter {
  const _SignUpDecorPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final backWavePaint = Paint()
      ..color = const Color(0xFF9E7161).withValues(alpha: 0.16)
      ..style = PaintingStyle.fill;

    final frontWavePaint = Paint()
      ..color = const Color(0xFF873632).withValues(alpha: 0.10)
      ..style = PaintingStyle.fill;

    final sideLinePaint = Paint()
      ..color = const Color(0xFF873632).withValues(alpha: 0.10)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.2
      ..strokeCap = StrokeCap.round;

    // Bottom back wave
    final backWave = Path()
      ..moveTo(0, size.height * 0.88)
      ..cubicTo(
        size.width * 0.22,
        size.height * 0.84,
        size.width * 0.44,
        size.height * 0.94,
        size.width * 0.64,
        size.height * 0.90,
      )
      ..cubicTo(
        size.width * 0.80,
        size.height * 0.87,
        size.width * 0.91,
        size.height * 0.84,
        size.width,
        size.height * 0.86,
      )
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();

    canvas.drawPath(backWave, backWavePaint);

    // Bottom front wave
    final frontWave = Path()
      ..moveTo(0, size.height * 0.92)
      ..cubicTo(
        size.width * 0.20,
        size.height * 0.89,
        size.width * 0.40,
        size.height * 0.97,
        size.width * 0.58,
        size.height * 0.94,
      )
      ..cubicTo(
        size.width * 0.76,
        size.height * 0.91,
        size.width * 0.88,
        size.height * 0.89,
        size.width,
        size.height * 0.91,
      )
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();

    canvas.drawPath(frontWave, frontWavePaint);

    // Left decorative curves
    final leftTopCurve = Path()
      ..moveTo(0, size.height * 0.18)
      ..cubicTo(
        size.width * 0.07,
        size.height * 0.16,
        size.width * 0.11,
        size.height * 0.20,
        size.width * 0.16,
        size.height * 0.18,
      );
    canvas.drawPath(leftTopCurve, sideLinePaint);

    final leftMidCurve = Path()
      ..moveTo(0, size.height * 0.55)
      ..cubicTo(
        size.width * 0.05,
        size.height * 0.53,
        size.width * 0.10,
        size.height * 0.57,
        size.width * 0.14,
        size.height * 0.55,
      );
    canvas.drawPath(leftMidCurve, sideLinePaint);

    // Right decorative curves
    final rightTopCurve = Path()
      ..moveTo(size.width, size.height * 0.27)
      ..cubicTo(
        size.width * 0.93,
        size.height * 0.25,
        size.width * 0.89,
        size.height * 0.29,
        size.width * 0.84,
        size.height * 0.27,
      );
    canvas.drawPath(rightTopCurve, sideLinePaint);

    final rightMidCurve = Path()
      ..moveTo(size.width, size.height * 0.68)
      ..cubicTo(
        size.width * 0.95,
        size.height * 0.66,
        size.width * 0.90,
        size.height * 0.70,
        size.width * 0.86,
        size.height * 0.68,
      );
    canvas.drawPath(rightMidCurve, sideLinePaint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

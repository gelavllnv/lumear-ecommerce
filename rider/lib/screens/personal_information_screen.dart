import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../models/rider_profile.dart';

class PersonalInformationScreen extends StatefulWidget {
  const PersonalInformationScreen({super.key});

  @override
  State<PersonalInformationScreen> createState() =>
      _PersonalInformationScreenState();
}

class _PersonalInformationScreenState extends State<PersonalInformationScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);

  static const String _baseUrl = 'http://10.102.177.127:8000';

  // Same options as the Sex selector on the sign-up screen.
  static const List<String> _sexOptions = ['Male', 'Female', 'Other'];

  static const List<String> _monthNames = [
    'january',
    'february',
    'march',
    'april',
    'may',
    'june',
    'july',
    'august',
    'september',
    'october',
    'november',
    'december',
  ];

  bool _isEditing = false;
  bool _isSaving = false;

  // Sex chosen in the dropdown while editing. Null if nothing valid is set.
  String? _selectedSex;

  // Birthday chosen with the date picker (or loaded from the profile).
  DateTime? _selectedBirthday;

  late final TextEditingController _nameController;
  late final TextEditingController _phoneController;
  late final TextEditingController _emailController;
  late final TextEditingController _sexController; // read-only display
  late final TextEditingController _birthdayController; // shows MM-DD-YYYY
  late final TextEditingController _ageController; // calculated
  late final TextEditingController _regionController;
  late final TextEditingController _provinceController;
  late final TextEditingController _municipalityController;
  late final TextEditingController _barangayController;
  late final TextEditingController _streetController;
  late final TextEditingController _houseUnitController;

  /// Sex saved on the rider profile (set during sign-up).
  /// Written defensively so it works whether RiderProfile.sex is a
  /// String or a nullable String.
  String _profileSex() {
    final dynamic value = RiderProfile.sex;
    return value is String ? value.trim() : '';
  }

  /// Matches the saved sex to one of the dropdown options (ignoring case),
  /// or returns null if it does not match any option.
  String? _matchSexOption(String value) {
    for (final option in _sexOptions) {
      if (option.toLowerCase() == value.trim().toLowerCase()) {
        return option;
      }
    }
    return null;
  }

  // ============================================================
  // BIRTHDAY HELPERS
  // ============================================================

  /// Reads a saved birthday. Supports "YYYY-MM-DD" (from the server,
  /// optionally with a time part) and "January 5, 2000" (saved locally
  /// by the sign-up screen).
  DateTime? _parseBirthday(String raw) {
    final String text = raw.trim();
    if (text.isEmpty) return null;

    // YYYY-MM-DD or YYYY-MM-DDTHH:MM:SS...
    final RegExpMatch? iso = RegExp(r'^(\d{4})-(\d{1,2})-(\d{1,2})')
        .firstMatch(text);
    if (iso != null) {
      return _buildDate(
        int.parse(iso.group(1)!),
        int.parse(iso.group(2)!),
        int.parse(iso.group(3)!),
      );
    }

    // "January 5, 2000"
    final RegExpMatch? long = RegExp(r'^([A-Za-z]+)\s+(\d{1,2}),?\s+(\d{4})$')
        .firstMatch(text);
    if (long != null) {
      final int month = _monthNames.indexOf(long.group(1)!.toLowerCase()) + 1;
      if (month == 0) return null;
      return _buildDate(
        int.parse(long.group(3)!),
        month,
        int.parse(long.group(2)!),
      );
    }

    return null;
  }

  /// Returns a DateTime only if the year / month / day make a real date
  /// (rejects things like February 30).
  DateTime? _buildDate(int year, int month, int day) {
    if (month < 1 || month > 12 || day < 1 || day > 31) return null;

    final DateTime date = DateTime(year, month, day);
    if (date.year != year || date.month != month || date.day != day) {
      return null;
    }
    return date;
  }

  /// Birthday shown as MM-DD-YYYY (example: 07-18-2005).
  String _formatBirthday(DateTime date) {
    final String month = date.month.toString().padLeft(2, '0');
    final String day = date.day.toString().padLeft(2, '0');
    final String year = date.year.toString().padLeft(4, '0');

    return '$month-$day-$year';
  }

  /// The birthday currently selected, or null if none.
  DateTime? _enteredBirthday() => _selectedBirthday;

  void _fillBirthdayFields(DateTime? date) {
    _selectedBirthday = date;
    _birthdayController.text = date == null ? '' : _formatBirthday(date);
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

  /// Keeps the Age field in sync with the Month / Day / Year fields.
  void _updateAge() {
    final DateTime? birthday = _enteredBirthday();

    if (birthday == null || birthday.isAfter(DateTime.now())) {
      _ageController.text = '';
    } else {
      _ageController.text = _calculateAge(birthday).toString();
    }
  }

  @override
  void initState() {
    super.initState();
    // Data now comes from the signed-up / logged-in rider, not hardcoded.
    _nameController = TextEditingController(text: RiderProfile.name);
    _phoneController = TextEditingController(text: RiderProfile.phone);
    _emailController = TextEditingController(text: RiderProfile.email);
    _sexController = TextEditingController(text: _profileSex());
    _selectedSex = _matchSexOption(_profileSex());
    _birthdayController = TextEditingController();
    _fillBirthdayFields(_parseBirthday(RiderProfile.birthday));
    _ageController = TextEditingController(
      text: RiderProfile.age?.toString() ?? '',
    );
    _regionController = TextEditingController(text: RiderProfile.region);
    _provinceController = TextEditingController(text: RiderProfile.province);
    _municipalityController = TextEditingController(
      text: RiderProfile.municipality,
    );
    _barangayController = TextEditingController(text: RiderProfile.barangay);
    _streetController = TextEditingController(text: RiderProfile.street);
    _houseUnitController = TextEditingController(text: RiderProfile.houseUnit);
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _sexController.dispose();
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

  // ============================================================
  // ADDRESS DROPDOWNS (PSGC)
  // Same data source as the sign-up screen. While editing, Region,
  // Province, City / Municipality and Barangay become dropdowns.
  // The text controllers stay the source of truth for the saved values,
  // so an address the rider doesn't touch is saved exactly as before.
  // ============================================================

  static const String _psgcBaseUrl = 'https://psgc.cloud/api/v2';

  List<Map<String, String>> _regionItems = [];
  List<Map<String, String>> _provinceItems = [];
  List<Map<String, String>> _municipalityItems = [];
  List<Map<String, String>> _barangayItems = [];

  String? _regionCode;
  String? _provinceCode;
  String? _municipalityCode;

  // True for regions with no provinces (such as NCR). Their cities and
  // municipalities are loaded by region instead.
  bool _provinceUsesRegion = false;

  bool _loadingRegions = false;
  bool _loadingProvinces = false;
  bool _loadingMunicipalities = false;
  bool _loadingBarangays = false;

  bool _regionFailed = false;
  bool _provinceFailed = false;
  bool _municipalityFailed = false;
  bool _barangayFailed = false;

  String _cleanPlaceName(String text) {
    String cleaned = text.trim();

    // Fix common mojibake such as "BiÃ±an" -> "Biñan".
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
    final http.Response response = await http
        .get(Uri.parse(url), headers: const {'Accept': 'application/json'})
        .timeout(const Duration(seconds: 15));

    if (response.statusCode != 200) {
      throw Exception('PSGC request failed (${response.statusCode}).');
    }

    final dynamic decoded = jsonDecode(utf8.decode(response.bodyBytes));

    // The PSGC v2 API wraps lists as {"data": [...]}.
    final dynamic items = decoded is Map && decoded['data'] is List
        ? decoded['data']
        : decoded;

    if (items is! List) {
      throw const FormatException('Unexpected PSGC response format.');
    }

    final List<Map<String, String>> list = items
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
        .toList();

    list.sort((a, b) => a['name']!.compareTo(b['name']!));
    return list;
  }

  /// Item name that matches [text] (ignoring case), or null.
  String? _matchName(List<Map<String, String>> items, String text) {
    final String wanted = text.trim().toLowerCase();
    if (wanted.isEmpty) return null;

    for (final item in items) {
      if (item['name']!.trim().toLowerCase() == wanted) {
        return item['name'];
      }
    }
    return null;
  }

  String? _codeFor(List<Map<String, String>> items, String text) {
    final String? name = _matchName(items, text);
    if (name == null) return null;

    for (final item in items) {
      if (item['name'] == name) return item['code'];
    }
    return null;
  }

  Future<void> _loadRegions() async {
    setState(() {
      _loadingRegions = true;
      _regionFailed = false;
    });

    try {
      final items = await _fetchPsgcList('$_psgcBaseUrl/regions');

      if (!mounted) return;

      setState(() {
        _regionItems = items;
        _loadingRegions = false;
        _regionFailed = items.isEmpty;
      });
    } catch (e) {
      debugPrint('Unable to load PSGC regions: $e');
      if (!mounted) return;

      setState(() {
        _loadingRegions = false;
        _regionFailed = true;
      });
    }
  }

  Future<void> _loadProvinces({bool selectSynthetic = false}) async {
    final String? regionCode = _regionCode;
    if (regionCode == null) return;

    setState(() {
      _loadingProvinces = true;
      _provinceFailed = false;
      _municipalityFailed = false;
      _barangayFailed = false;
      _provinceItems = [];
      _municipalityItems = [];
      _barangayItems = [];
    });

    try {
      final items = await _fetchPsgcList(
        '$_psgcBaseUrl/regions/$regionCode/provinces',
      );

      if (!mounted || _regionCode != regionCode) return;

      if (items.isEmpty) {
        // Regions such as NCR have no provinces. Keep one entry named after
        // the region (same as sign-up) and load cities by region.
        final String regionName = _regionController.text.trim();

        setState(() {
          _provinceUsesRegion = true;
          _provinceCode = regionCode;
          _provinceItems = [
            {'code': regionCode, 'name': regionName},
          ];
          if (selectSynthetic) {
            _provinceController.text = regionName;
          }
          _loadingProvinces = false;
        });

        await _loadMunicipalities();
        return;
      }

      setState(() {
        _provinceUsesRegion = false;
        _provinceItems = items;
        _loadingProvinces = false;
      });
    } catch (e) {
      debugPrint('Unable to load PSGC provinces: $e');
      if (!mounted || _regionCode != regionCode) return;

      setState(() {
        _loadingProvinces = false;
        _provinceFailed = true;
      });
    }
  }

  Future<void> _loadMunicipalities() async {
    final String? code = _provinceUsesRegion ? _regionCode : _provinceCode;
    if (code == null) return;

    final bool byRegion = _provinceUsesRegion;

    setState(() {
      _loadingMunicipalities = true;
      _municipalityFailed = false;
      _barangayFailed = false;
      _municipalityItems = [];
      _barangayItems = [];
    });

    try {
      final String url = byRegion
          ? '$_psgcBaseUrl/regions/$code/cities-municipalities'
          : '$_psgcBaseUrl/provinces/$code/cities-municipalities';

      final items = await _fetchPsgcList(url);

      final String? currentCode = _provinceUsesRegion
          ? _regionCode
          : _provinceCode;

      if (!mounted || currentCode != code) return;

      setState(() {
        _municipalityItems = items;
        _loadingMunicipalities = false;
        _municipalityFailed = items.isEmpty;
      });
    } catch (e) {
      debugPrint('Unable to load PSGC cities/municipalities: $e');
      if (!mounted) return;

      setState(() {
        _loadingMunicipalities = false;
        _municipalityFailed = true;
      });
    }
  }

  Future<void> _loadBarangays() async {
    final String? code = _municipalityCode;
    if (code == null) return;

    setState(() {
      _loadingBarangays = true;
      _barangayFailed = false;
      _barangayItems = [];
    });

    try {
      final items = await _fetchPsgcList(
        '$_psgcBaseUrl/cities-municipalities/$code/barangays',
      );

      if (!mounted || _municipalityCode != code) return;

      setState(() {
        _barangayItems = items;
        _loadingBarangays = false;
        _barangayFailed = items.isEmpty;
      });
    } catch (e) {
      debugPrint('Unable to load PSGC barangays: $e');
      if (!mounted) return;

      setState(() {
        _loadingBarangays = false;
        _barangayFailed = true;
      });
    }
  }

  /// Called when the rider taps "Edit Information". Loads the lists and
  /// tries to pre-select the rider's current address in each dropdown.
  /// If a saved name can't be matched, that dropdown shows the saved value
  /// as its hint and the rider can pick a new one.
  Future<void> _prepareAddressDropdowns() async {
    await _loadRegions();
    if (!mounted || !_isEditing || _regionFailed) return;

    final String? regionCode = _codeFor(_regionItems, _regionController.text);
    if (regionCode == null) return;
    setState(() => _regionCode = regionCode);

    await _loadProvinces();
    if (!mounted || !_isEditing || _provinceFailed) return;

    // For regions without provinces, _loadProvinces already loaded the
    // cities / municipalities.
    if (!_provinceUsesRegion) {
      final String? provinceCode = _codeFor(
        _provinceItems,
        _provinceController.text,
      );
      if (provinceCode == null) return;
      setState(() => _provinceCode = provinceCode);

      await _loadMunicipalities();
    }

    if (!mounted || !_isEditing || _municipalityFailed) return;

    final String? municipalityCode = _codeFor(
      _municipalityItems,
      _municipalityController.text,
    );
    if (municipalityCode == null) return;
    setState(() => _municipalityCode = municipalityCode);

    await _loadBarangays();
  }

  Future<void> _onRegionChanged(String? value) async {
    if (value == null) return;

    setState(() {
      _regionController.text = value;
      _regionCode = _codeFor(_regionItems, value);

      _provinceController.clear();
      _municipalityController.clear();
      _barangayController.clear();

      _provinceCode = null;
      _municipalityCode = null;
      _provinceUsesRegion = false;

      _provinceItems = [];
      _municipalityItems = [];
      _barangayItems = [];

      _provinceFailed = false;
      _municipalityFailed = false;
      _barangayFailed = false;
    });

    await _loadProvinces(selectSynthetic: true);
  }

  Future<void> _onProvinceChanged(String? value) async {
    if (value == null) return;

    setState(() {
      _provinceController.text = value;
      _provinceCode = _codeFor(_provinceItems, value);

      _municipalityController.clear();
      _barangayController.clear();

      _municipalityCode = null;
      _municipalityItems = [];
      _barangayItems = [];

      _municipalityFailed = false;
      _barangayFailed = false;
    });

    await _loadMunicipalities();
  }

  Future<void> _onMunicipalityChanged(String? value) async {
    if (value == null) return;

    setState(() {
      _municipalityController.text = value;
      _municipalityCode = _codeFor(_municipalityItems, value);

      _barangayController.clear();
      _barangayItems = [];
      _barangayFailed = false;
    });

    await _loadBarangays();
  }

  Future<void> _onBarangayChanged(String? value) async {
    if (value == null) return;

    setState(() {
      _barangayController.text = value;
    });
  }

  /// Address card contents. Read-only fields normally; dropdowns for
  /// Region / Province / City / Barangay while editing.
  List<Widget> _addressFields() {
    if (!_isEditing) {
      return [
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
        _infoField('House / Unit', Icons.home_outlined, _houseUnitController),
      ];
    }

    return [
      _addressDropdown(
        label: 'Region',
        icon: Icons.map_outlined,
        controller: _regionController,
        items: _regionItems,
        loading: _loadingRegions,
        failed: _regionFailed,
        onRetry: _prepareAddressDropdowns,
        onChanged: _onRegionChanged,
      ),
      _gap(),
      _addressDropdown(
        label: 'Province',
        icon: Icons.location_city_outlined,
        controller: _provinceController,
        items: _provinceItems,
        loading: _loadingProvinces,
        failed: _provinceFailed,
        onRetry: _loadProvinces,
        onChanged: _onProvinceChanged,
      ),
      _gap(),
      _addressDropdown(
        label: 'Municipality / City',
        icon: Icons.apartment_outlined,
        controller: _municipalityController,
        items: _municipalityItems,
        loading: _loadingMunicipalities,
        failed: _municipalityFailed,
        onRetry: _loadMunicipalities,
        onChanged: _onMunicipalityChanged,
      ),
      _gap(),
      _addressDropdown(
        label: 'Barangay',
        icon: Icons.place_outlined,
        controller: _barangayController,
        items: _barangayItems,
        loading: _loadingBarangays,
        failed: _barangayFailed,
        onRetry: _loadBarangays,
        onChanged: _onBarangayChanged,
      ),
      _gap(),
      _infoField('Street', Icons.route_outlined, _streetController),
      _gap(),
      _infoField('House / Unit', Icons.home_outlined, _houseUnitController),
    ];
  }

  Widget _addressDropdown({
    required String label,
    required IconData icon,
    required TextEditingController controller,
    required List<Map<String, String>> items,
    required bool loading,
    required bool failed,
    required Future<void> Function() onRetry,
    required Future<void> Function(String?) onChanged,
  }) {
    if (loading) {
      return _addressStatusField(
        text: 'Loading ${label.toLowerCase()}...',
        loading: true,
      );
    }

    if (failed) {
      return _addressStatusField(
        text: 'Unable to load ${label.toLowerCase()}. Tap to retry.',
        onTap: onRetry,
      );
    }

    final List<String> names = items.map((item) => item['name']!).toList();
    final String? value = _matchName(items, controller.text);
    final String current = controller.text.trim();

    return DropdownButtonFormField<String>(
      key: ValueKey('$label-${value ?? 'none'}-${names.length}'),
      initialValue: value,
      isExpanded: true,
      menuMaxHeight: 360,
      borderRadius: BorderRadius.circular(18),
      dropdownColor: cream,
      icon: const Icon(
        Icons.keyboard_arrow_down_rounded,
        color: maroon,
        size: 24,
      ),
      style: const TextStyle(
        color: darkMaroon,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      // Shows the saved value until the rider picks a new one.
      hint: Text(
        current.isNotEmpty ? current : 'Select ${label.toLowerCase()}',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: current.isNotEmpty ? darkMaroon : brownBeige,
          fontSize: 14,
          fontWeight: FontWeight.w600,
        ),
      ),
      decoration: _fieldDecoration(
        label,
        icon,
      ).copyWith(floatingLabelBehavior: FloatingLabelBehavior.always),
      selectedItemBuilder: (context) {
        return names.map((name) {
          return Align(
            alignment: Alignment.centerLeft,
            child: Text(
              name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: darkMaroon,
                fontSize: 14,
                fontWeight: FontWeight.w600,
              ),
            ),
          );
        }).toList();
      },
      items: names.map((name) {
        return DropdownMenuItem<String>(
          value: name,
          child: Text(
            name,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 13,
              height: 1.25,
            ),
          ),
        );
      }).toList(),
      onChanged: names.isEmpty
          ? null
          : (String? selected) {
              onChanged(selected);
            },
    );
  }

  Widget _addressStatusField({
    required String text,
    bool loading = false,
    Future<void> Function()? onTap,
  }) {
    return InkWell(
      onTap: onTap == null ? null : () => onTap(),
      borderRadius: BorderRadius.circular(18),
      child: Container(
        height: 58,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: loading
                ? lightBeige.withValues(alpha: 0.8)
                : maroon.withValues(alpha: 0.25),
          ),
        ),
        child: Row(
          children: [
            if (loading)
              const SizedBox(
                width: 18,
                height: 18,
                child: CircularProgressIndicator(strokeWidth: 2, color: maroon),
              )
            else
              const Icon(Icons.refresh_rounded, color: maroon, size: 20),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                text,
                style: TextStyle(
                  color: loading ? brownBeige : maroon,
                  fontSize: 12,
                  fontWeight: loading ? FontWeight.normal : FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _saveChanges() async {
    final int? riderId = RiderProfile.id;
    if (riderId == null) {
      _showMessage('Rider information is unavailable. Please login again.');
      return;
    }

    if (_nameController.text.trim().isEmpty ||
        _emailController.text.trim().isEmpty ||
        _phoneController.text.trim().isEmpty ||
        _selectedSex == null ||
        _selectedBirthday == null ||
        _regionController.text.trim().isEmpty ||
        _provinceController.text.trim().isEmpty ||
        _municipalityController.text.trim().isEmpty ||
        _barangayController.text.trim().isEmpty ||
        _streetController.text.trim().isEmpty ||
        _houseUnitController.text.trim().isEmpty) {
      _showMessage('Please complete all personal information fields.');
      return;
    }

    final DateTime? birthday = _enteredBirthday();

    if (birthday == null) {
      _showMessage('Please select your birthday.');
      return;
    }

    if (birthday.isAfter(DateTime.now())) {
      _showMessage('Birthday cannot be in the future.');
      return;
    }

    final int age = _calculateAge(birthday);

    if (age < 18) {
      _showMessage('You must be at least 18 years old.');
      return;
    }

    final String sexToSave = _selectedSex!;
    final String birthdayToSave =
        '${birthday.year.toString().padLeft(4, '0')}-'
        '${birthday.month.toString().padLeft(2, '0')}-'
        '${birthday.day.toString().padLeft(2, '0')}';

    setState(() => _isSaving = true);

    try {
      final response = await http
          .patch(
            Uri.parse('$_baseUrl/api/riders/$riderId/personal-information'),
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
            body: jsonEncode({
              'full_name': _nameController.text.trim(),
              'email': _emailController.text.trim(),
              'phone': _phoneController.text.trim(),
              'sex': sexToSave,
              'birthday': birthdayToSave,
              'age': age,
              'region': _regionController.text.trim(),
              'province': _provinceController.text.trim(),
              'municipality': _municipalityController.text.trim(),
              'barangay': _barangayController.text.trim(),
              'street': _streetController.text.trim(),
              'house_unit': _houseUnitController.text.trim(),
            }),
          )
          .timeout(const Duration(seconds: 15));

      final dynamic decoded = response.body.isNotEmpty
          ? jsonDecode(response.body)
          : <String, dynamic>{};
      final Map<String, dynamic> data = decoded is Map<String, dynamic>
          ? decoded
          : <String, dynamic>{};

      if (!mounted) return;

      if (response.statusCode == 200 && data['success'] == true) {
        final dynamic rider = data['rider'];
        if (rider is Map) {
          RiderProfile.setFromJson(Map<String, dynamic>.from(rider));
        }

        // Keep the saved sex even if the server response does not include it.
        RiderProfile.sex = sexToSave;

        setState(() {
          _sexController.text = sexToSave;
          _fillBirthdayFields(
            _parseBirthday(RiderProfile.birthday) ?? birthday,
          );
          _updateAge();
          _isEditing = false;
          _isSaving = false;
        });

        _showMessage('Personal information updated.', success: true);
        return;
      }

      setState(() => _isSaving = false);
      _showMessage(
        data['message']?.toString() ?? 'Unable to update information.',
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSaving = false);
      _showMessage('Unable to connect to the server.');
    }
  }

  void _cancelEditing() {
    _nameController.text = RiderProfile.name;
    _phoneController.text = RiderProfile.phone;
    _emailController.text = RiderProfile.email;
    _sexController.text = _profileSex();
    _fillBirthdayFields(_parseBirthday(RiderProfile.birthday));
    _ageController.text = RiderProfile.age?.toString() ?? '';
    _regionController.text = RiderProfile.region;
    _provinceController.text = RiderProfile.province;
    _municipalityController.text = RiderProfile.municipality;
    _barangayController.text = RiderProfile.barangay;
    _streetController.text = RiderProfile.street;
    _houseUnitController.text = RiderProfile.houseUnit;
    setState(() {
      _selectedSex = _matchSexOption(_profileSex());
      _isEditing = false;
    });
  }

  void _showMessage(String message, {bool success = false}) {
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
                  CircleAvatar(
                    radius: 41,
                    backgroundColor: lightBeige,
                    backgroundImage: RiderProfile.profileImage != null
                        ? FileImage(RiderProfile.profileImage!)
                        : null,
                    child: RiderProfile.profileImage == null
                        ? const Icon(Icons.person, size: 45, color: darkMaroon)
                        : null,
                  ),
                  const SizedBox(width: 18),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          RiderProfile.name.isEmpty
                              ? 'Rider Information'
                              : RiderProfile.name,
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: Colors.white,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          RiderProfile.riderId.isEmpty
                              ? 'Registered Rider'
                              : 'Rider ID: ${RiderProfile.riderId}',
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
              _sexField(),
              _gap(),
              _birthdayField(),
              _gap(),
              _infoField(
                'Age (calculated from birthday)',
                Icons.numbers_outlined,
                _ageController,
                readOnly: true,
              ),
            ]),
            const SizedBox(height: 28),
            _sectionTitle(
              'Address',
              'Your registered delivery service address.',
            ),
            const SizedBox(height: 18),
            _card(_addressFields()),
            const SizedBox(height: 30),
            SizedBox(
              width: double.infinity,
              height: 56,
              child: ElevatedButton.icon(
                onPressed: _isSaving
                    ? null
                    : () {
                        if (_isEditing) {
                          _saveChanges();
                        } else {
                          setState(() => _isEditing = true);
                          _prepareAddressDropdowns();
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
                  onPressed: _isSaving ? null : _cancelEditing,
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

  // Shared look for every field so the dropdown and number fields match
  // the text fields.
  InputDecoration _fieldDecoration(
    String label,
    IconData? icon, {
    String? hintText,
    bool readOnly = false,
  }) {
    return InputDecoration(
      labelText: label,
      hintText: hintText,
      labelStyle: const TextStyle(color: brownBeige, fontSize: 12),
      floatingLabelStyle: const TextStyle(
        color: darkMaroon,
        fontWeight: FontWeight.w600,
      ),
      prefixIcon: icon == null
          ? null
          : Container(
              margin: const EdgeInsets.all(9),
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: lightBeige.withValues(alpha: 0.55),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: darkMaroon, size: 20),
            ),
      filled: true,
      fillColor: (_isEditing && !readOnly)
          ? Colors.white
          : cream.withValues(alpha: 0.45),
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
    );
  }

  // Sex: shows the value chosen at sign-up. While editing, it becomes a
  // Male / Female / Other dropdown (same options as the sign-up screen).
  Widget _sexField() {
    if (!_isEditing) {
      return _infoField('Sex', Icons.wc_outlined, _sexController);
    }

    return DropdownButtonFormField<String>(
      key: const ValueKey('sex-dropdown'),
      initialValue: _selectedSex,
      isExpanded: true,
      borderRadius: BorderRadius.circular(18),
      dropdownColor: cream,
      icon: const Icon(
        Icons.keyboard_arrow_down_rounded,
        color: maroon,
        size: 24,
      ),
      style: const TextStyle(
        color: darkMaroon,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      decoration: _fieldDecoration('Sex', Icons.wc_outlined),
      items: _sexOptions.map((option) {
        return DropdownMenuItem<String>(
          value: option,
          child: Text(
            option,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
        );
      }).toList(),
      onChanged: (value) {
        setState(() => _selectedSex = value);
      },
    );
  }

  // Birthday is one field shown as MM-DD-YYYY. While editing, tapping it
  // opens a date picker.
  Future<void> _pickBirthday() async {
    if (!_isEditing) return;

    final DateTime now = DateTime.now();
    final DateTime firstDate = DateTime(1940);

    DateTime initial =
        _selectedBirthday ?? DateTime(now.year - 18, now.month, now.day);
    if (initial.isAfter(now)) initial = now;
    if (initial.isBefore(firstDate)) initial = firstDate;

    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: firstDate,
      lastDate: now,
      helpText: 'Select your birthday',
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: darkMaroon,
              onPrimary: Colors.white,
              surface: cream,
              onSurface: darkMaroon,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked == null || !mounted) return;

    setState(() {
      _fillBirthdayFields(picked);
      _updateAge();
    });
  }

  Widget _birthdayField() {
    return TextField(
      controller: _birthdayController,
      enabled: _isEditing,
      readOnly: true,
      onTap: _pickBirthday,
      style: const TextStyle(
        color: darkMaroon,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      decoration:
          _fieldDecoration(
            'Birthday (MM-DD-YYYY)',
            Icons.cake_outlined,
            hintText: 'MM-DD-YYYY',
          ).copyWith(
            suffixIcon: _isEditing
                ? const Padding(
                    padding: EdgeInsets.only(right: 6),
                    child: Icon(
                      Icons.calendar_month_outlined,
                      color: maroon,
                      size: 22,
                    ),
                  )
                : null,
          ),
    );
  }

  Widget _infoField(
    String label,
    IconData icon,
    TextEditingController controller, {
    TextInputType keyboardType = TextInputType.text,
    String? hintText,
    bool readOnly = false,
  }) {
    return TextField(
      controller: controller,
      enabled: _isEditing && !readOnly,
      keyboardType: keyboardType,
      style: const TextStyle(
        color: darkMaroon,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      decoration: _fieldDecoration(
        label,
        icon,
        hintText: hintText,
        readOnly: readOnly,
      ),
    );
  }
}

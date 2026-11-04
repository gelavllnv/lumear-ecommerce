import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;

import '../services/postal_code_service.dart';

/// Cascading PSGC selections; postal routing comes from the bundled lookup.
class RegistrationAddressFields extends StatefulWidget {
  const RegistrationAddressFields({
    super.key,
    required this.province,
    required this.city,
    required this.barangay,
    required this.postal,
  });
  final TextEditingController province, city, barangay, postal;

  @override
  State<RegistrationAddressFields> createState() =>
      _RegistrationAddressFieldsState();
}

class _RegistrationAddressFieldsState extends State<RegistrationAddressFields> {
  List<Map<String, dynamic>> provinces = [], cities = [], barangays = [];
  String? provinceCode, cityCode, barangayCode, error, postalNotice;
  String ncrCode = '';
  bool loadingProvinces = true, loadingCities = false, loadingBarangays = false;
  int request = 0;
  VoidCallback? retry;

  @override
  void initState() {
    super.initState();
    loadProvinces();
  }

  Future<List<Map<String, dynamic>>> get(String path) async {
    final response = await http
        .get(Uri.parse('https://psgc.cloud/api/v2$path'))
        .timeout(const Duration(seconds: 15));
    if (response.statusCode != 200) throw Exception('Location unavailable');
    final decoded = jsonDecode(response.body);
    final data = decoded is Map ? decoded['data'] : decoded;
    if (data is! List) throw const FormatException('Invalid location response');
    final rows = data.map((r) => Map<String, dynamic>.from(r as Map)).toList();
    rows.sort((a, b) => a['name'].toString().compareTo(b['name'].toString()));
    return rows;
  }

  Future<void> loadProvinces() async {
    setState(() {
      loadingProvinces = true;
      error = null;
    });
    try {
      final results = await Future.wait([get('/provinces'), get('/regions')]);
      if (!mounted) return;
      final ncr = results[1].where(
        (r) => r['name'].toString().toLowerCase().contains('national capital'),
      );
      setState(() {
        provinces = results[0];
        if (ncr.isNotEmpty) {
          ncrCode = ncr.first['code'].toString();
          provinces.add({'code': 'metro-manila', 'name': 'Metro Manila'});
          provinces.sort(
            (a, b) => a['name'].toString().compareTo(b['name'].toString()),
          );
        }
        loadingProvinces = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        loadingProvinces = false;
        error = 'Unable to load provinces.';
        retry = loadProvinces;
      });
    }
  }

  Future<void> chooseProvince(String? code) async {
    if (code == null) return;
    final version = ++request;
    setState(() {
      provinceCode = code;
      cityCode = null;
      barangayCode = null;
      widget.province.text = provinces
          .firstWhere((r) => r['code'].toString() == code)['name']
          .toString();
      widget.city.clear();
      widget.barangay.clear();
      widget.postal.clear();
      cities = [];
      barangays = [];
      postalNotice = null;
      error = null;
      loadingCities = true;
      loadingBarangays = false;
    });
    try {
      final rows = await get(
        code == 'metro-manila'
            ? '/regions/$ncrCode/cities-municipalities'
            : '/provinces/$code/cities-municipalities',
      );
      if (!mounted || version != request) return;
      setState(() {
        cities = rows;
        loadingCities = false;
      });
    } catch (_) {
      if (!mounted || version != request) return;
      setState(() {
        loadingCities = false;
        error = 'Unable to load cities / municipalities.';
        retry = () => chooseProvince(code);
      });
    }
  }

  Future<void> chooseCity(String? code) async {
    if (code == null) return;
    final version = ++request;
    final province = widget.province.text;
    final city = cities
        .firstWhere((r) => r['code'].toString() == code)['name']
        .toString();
    setState(() {
      cityCode = code;
      barangayCode = null;
      widget.city.text = city;
      widget.barangay.clear();
      widget.postal.clear();
      barangays = [];
      postalNotice = null;
      error = null;
      loadingBarangays = true;
    });
    // Resolve independently so a barangay-service failure does not block ZIP lookup.
    fillPostal(province, city, version);
    try {
      final rows = await get('/cities-municipalities/$code/barangays');
      if (!mounted || version != request) return;
      setState(() {
        barangays = rows;
        loadingBarangays = false;
      });
    } catch (_) {
      if (!mounted || version != request) return;
      setState(() {
        loadingBarangays = false;
        error = 'Unable to load barangays.';
        retry = () => chooseCity(code);
      });
    }
  }

  Future<void> fillPostal(String province, String city, int version) async {
    String? code;
    try {
      code = (await PostalCodeService.load()).find(
        province: province,
        municipality: city,
      );
    } catch (_) {
      /* Allow correction if no unique routing code is available. */
    }
    if (!mounted || version != request) return;
    setState(() {
      widget.postal.text = code ?? '';
      postalNotice = code == null
          ? 'No single postal code found. Enter your 4-digit postal code.'
          : 'Filled automatically from your city / municipality.';
    });
  }

  Widget dropdown(
    String label,
    List<Map<String, dynamic>> rows,
    String? value,
    bool loading,
    ValueChanged<String?>? onChanged,
    String parent,
  ) => Padding(
    padding: const EdgeInsets.only(bottom: 16),
    child: DropdownButtonFormField<String>(
      key: ValueKey('$label:$parent:$value'),
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(
        labelText: label,
        suffixIcon: loading
            ? const Padding(
                padding: EdgeInsets.all(14),
                child: SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                ),
              )
            : null,
      ),
      hint: Text(loading ? 'Loading…' : 'Select $label'),
      items: rows
          .map(
            (r) => DropdownMenuItem(
              value: r['code'].toString(),
              child: Text(
                r['name'].toString(),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          )
          .toList(),
      onChanged: loading || rows.isEmpty ? null : onChanged,
      validator: (v) => v == null ? 'Please select $label.' : null,
    ),
  );

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (error != null)
        Row(
          children: [
            Expanded(child: Text(error!)),
            TextButton(onPressed: retry, child: const Text('Retry')),
          ],
        ),
      dropdown(
        'Province',
        provinces,
        provinceCode,
        loadingProvinces,
        chooseProvince,
        '',
      ),
      dropdown(
        'City / Municipality',
        cities,
        cityCode,
        loadingCities,
        chooseCity,
        provinceCode ?? '',
      ),
      dropdown('Barangay', barangays, barangayCode, loadingBarangays, (code) {
        setState(() {
          barangayCode = code;
          widget.barangay.text = barangays
              .firstWhere((r) => r['code'].toString() == code)['name']
              .toString();
        });
      }, cityCode ?? ''),
      Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: TextFormField(
          controller: widget.postal,
          keyboardType: TextInputType.number,
          maxLength: 4,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          decoration: InputDecoration(
            labelText: 'Postal code',
            counterText: '',
            helperText: postalNotice,
            helperMaxLines: 3,
          ),
          validator: (v) => RegExp(r'^\d{4}$').hasMatch(v ?? '')
              ? null
              : 'Enter a 4-digit postal code.',
        ),
      ),
    ],
  );
}

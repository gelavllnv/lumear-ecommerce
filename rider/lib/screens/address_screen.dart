import 'package:flutter/material.dart';
import 'package:latlong2/latlong.dart';

import 'map_screen.dart';

class AddressScreen extends StatefulWidget {
  const AddressScreen({super.key});

  @override
  State<AddressScreen> createState() => _AddressScreenState();
}

class _AddressScreenState extends State<AddressScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);

  final TextEditingController _streetController = TextEditingController();

  final TextEditingController _houseController = TextEditingController();

  String? _selectedRegion;
  String? _selectedProvince;
  String? _selectedMunicipality;
  String? _selectedBarangay;

  LatLng? _selectedMapLocation;

  final List<String> _regions = ['South Luzon'];

  final List<String> _provinces = ['Laguna'];

  final List<String> _municipalities = ['Victoria'];

  final List<String> _barangays = [
    'Banca-banca',
    'Daniw',
    'Masapang',
    'Nanhaya',
    'Pagalangan',
    'San Benito',
    'San Felix',
    'San Francisco',
  ];

  // ============================================================
  // OPEN MAP
  // ============================================================

  Future<void> _openMap() async {
    final LatLng? location = await Navigator.push<LatLng>(
      context,
      MaterialPageRoute(builder: (context) => const MapScreen()),
    );

    // User pressed back instead of Confirm.
    if (location == null) {
      return;
    }

    if (!mounted) return;

    setState(() {
      _selectedMapLocation = location;
    });

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: darkMaroon,
        behavior: SnackBarBehavior.floating,
        content: const Text('Location selected successfully.'),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    );
  }

  // ============================================================
  // SAVE ADDRESS
  // ============================================================

  void _saveAddress() {
    if (_selectedRegion == null ||
        _selectedProvince == null ||
        _selectedMunicipality == null ||
        _selectedBarangay == null) {
      _showMessage('Please complete your address.');
      return;
    }

    if (_streetController.text.trim().isEmpty ||
        _houseController.text.trim().isEmpty) {
      _showMessage('Please enter your street and house/unit.');
      return;
    }

    if (_selectedMapLocation == null) {
      _showMessage('Please select your exact location on the map.');
      return;
    }

    _showMessage('Address saved successfully!');
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        backgroundColor: darkMaroon,
        behavior: SnackBarBehavior.floating,
        content: Text(message),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    );
  }

  // ============================================================
  // DISPOSE
  // ============================================================

  @override
  void dispose() {
    _streetController.dispose();
    _houseController.dispose();
    super.dispose();
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,

      appBar: AppBar(
        backgroundColor: cream,
        surfaceTintColor: Colors.transparent,
        elevation: 0,

        title: const Text(
          'Address',
          style: TextStyle(color: darkMaroon, fontWeight: FontWeight.bold),
        ),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 15, 20, 30),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ==================================================
              // HEADER
              // ==================================================

              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: darkMaroon,
                  borderRadius: BorderRadius.circular(24),
                ),

                child: const Row(
                  children: [
                    CircleAvatar(
                      radius: 27,
                      backgroundColor: lightBeige,
                      child: Icon(
                        Icons.home_outlined,
                        color: darkMaroon,
                        size: 28,
                      ),
                    ),

                    SizedBox(width: 15),

                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Delivery Address',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),

                          SizedBox(height: 4),

                          Text(
                            'Enter your address and pin your exact location.',
                            style: TextStyle(color: lightBeige, fontSize: 12),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              const Text(
                'Address Details',
                style: TextStyle(
                  color: darkMaroon,
                  fontSize: 17,
                  fontWeight: FontWeight.bold,
                ),
              ),

              const SizedBox(height: 12),

              Container(
                padding: const EdgeInsets.all(17),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.45),
                  borderRadius: BorderRadius.circular(22),
                ),

                child: Column(
                  children: [
                    _dropdown(
                      label: 'Region',
                      icon: Icons.public_outlined,
                      value: _selectedRegion,
                      items: _regions,
                      onChanged: (value) {
                        setState(() {
                          _selectedRegion = value;
                        });
                      },
                    ),

                    const SizedBox(height: 13),

                    _dropdown(
                      label: 'Province',
                      icon: Icons.map_outlined,
                      value: _selectedProvince,
                      items: _provinces,
                      onChanged: (value) {
                        setState(() {
                          _selectedProvince = value;
                        });
                      },
                    ),

                    const SizedBox(height: 13),

                    _dropdown(
                      label: 'Municipality',
                      icon: Icons.location_city_outlined,
                      value: _selectedMunicipality,
                      items: _municipalities,
                      onChanged: (value) {
                        setState(() {
                          _selectedMunicipality = value;
                        });
                      },
                    ),

                    const SizedBox(height: 13),

                    _dropdown(
                      label: 'Barangay',
                      icon: Icons.place_outlined,
                      value: _selectedBarangay,
                      items: _barangays,
                      onChanged: (value) {
                        setState(() {
                          _selectedBarangay = value;
                        });
                      },
                    ),

                    const SizedBox(height: 13),

                    _textField(
                      controller: _streetController,
                      label: 'Street',
                      icon: Icons.signpost_outlined,
                    ),

                    const SizedBox(height: 13),

                    _textField(
                      controller: _houseController,
                      label: 'House / Unit No.',
                      icon: Icons.home_work_outlined,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              const Text(
                'Exact Location',
                style: TextStyle(
                  color: darkMaroon,
                  fontSize: 17,
                  fontWeight: FontWeight.bold,
                ),
              ),

              const SizedBox(height: 12),

              // ==================================================
              // LOCATION CARD
              // ==================================================
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(17),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.45),
                  borderRadius: BorderRadius.circular(22),
                ),

                child: Column(
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 48,
                          height: 48,
                          decoration: BoxDecoration(
                            color: _selectedMapLocation != null
                                ? darkMaroon
                                : lightBeige,
                            shape: BoxShape.circle,
                          ),

                          child: Icon(
                            _selectedMapLocation != null
                                ? Icons.check_rounded
                                : Icons.location_on_outlined,
                            color: _selectedMapLocation != null
                                ? Colors.white
                                : darkMaroon,
                          ),
                        ),

                        const SizedBox(width: 13),

                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _selectedMapLocation == null
                                    ? 'No location selected'
                                    : 'Location Selected',
                                style: const TextStyle(
                                  color: darkMaroon,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 14,
                                ),
                              ),

                              const SizedBox(height: 4),

                              Text(
                                _selectedMapLocation == null
                                    ? 'Pin your exact location on the map.'
                                    : 'Lat: ${_selectedMapLocation!.latitude.toStringAsFixed(6)}\n'
                                          'Lng: ${_selectedMapLocation!.longitude.toStringAsFixed(6)}',
                                style: const TextStyle(
                                  color: brownBeige,
                                  fontSize: 11,
                                  height: 1.4,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 15),

                    SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: ElevatedButton.icon(
                        onPressed: _openMap,

                        icon: Icon(
                          _selectedMapLocation == null
                              ? Icons.map_outlined
                              : Icons.edit_location_alt_outlined,
                        ),

                        label: Text(
                          _selectedMapLocation == null
                              ? 'SELECT EXACT LOCATION'
                              : 'CHANGE LOCATION',
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),

                        style: ElevatedButton.styleFrom(
                          backgroundColor: darkMaroon,
                          foregroundColor: Colors.white,
                          elevation: 0,

                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(25),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              // ==================================================
              // SAVE
              // ==================================================
              SizedBox(
                width: double.infinity,
                height: 55,
                child: ElevatedButton(
                  onPressed: _saveAddress,

                  style: ElevatedButton.styleFrom(
                    backgroundColor: maroon,
                    foregroundColor: Colors.white,
                    elevation: 0,

                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(28),
                    ),
                  ),

                  child: const Text(
                    'SAVE ADDRESS',
                    style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // DROPDOWN
  // ============================================================

  Widget _dropdown({
    required String label,
    required IconData icon,
    required String? value,
    required List<String> items,
    required ValueChanged<String?> onChanged,
  }) {
    return DropdownButtonFormField<String>(
      initialValue: value,

      decoration: InputDecoration(
        labelText: label,

        prefixIcon: Icon(icon, color: darkMaroon),

        filled: true,
        fillColor: cream.withValues(alpha: 0.65),

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
          borderSide: const BorderSide(color: darkMaroon, width: 1.5),
        ),
      ),

      items: items.map((item) {
        return DropdownMenuItem<String>(value: item, child: Text(item));
      }).toList(),

      onChanged: onChanged,
    );
  }

  // ============================================================
  // TEXT FIELD
  // ============================================================

  Widget _textField({
    required TextEditingController controller,
    required String label,
    required IconData icon,
  }) {
    return TextFormField(
      controller: controller,

      decoration: InputDecoration(
        labelText: label,

        prefixIcon: Icon(icon, color: darkMaroon),

        filled: true,
        fillColor: cream.withValues(alpha: 0.65),

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
          borderSide: const BorderSide(color: darkMaroon, width: 1.5),
        ),
      ),
    );
  }
}

import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../models/rider_profile.dart';

class VehicleInformationScreen extends StatefulWidget {
  const VehicleInformationScreen({super.key});

  @override
  State<VehicleInformationScreen> createState() =>
      _VehicleInformationScreenState();
}

class _VehicleInformationScreenState extends State<VehicleInformationScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);

  static const String _baseUrl = 'http://10.102.177.127:8000';

  // ============================================================
  // JOB TYPE (same rules as the Sign Up screen)
  //
  //   Motorcycle / Tricycle -> Delivery (automatic, no question)
  //   Van                   -> Seller Pickup  or  Delivery
  //   Truck                 -> Long-distance Handover  or  Delivery
  //                            (large items)
  // ============================================================
  static const String jobSellerPickup = 'Seller Pickup';
  static const String jobLongDistance = 'Long-distance Handover';
  static const String jobDelivery = 'Delivery';

  static const List<Map<String, dynamic>> _vehicleTypes = [
    {'label': 'Motorcycle', 'icon': Icons.two_wheeler_outlined},
    {'label': 'Tricycle', 'icon': Icons.electric_rickshaw_outlined},
    {'label': 'Van', 'icon': Icons.airport_shuttle_outlined},
    {'label': 'Truck', 'icon': Icons.local_shipping_outlined},
  ];

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

  bool _isEditing = false;
  bool _isSaving = false;

  late String _selectedVehicle;
  String? _selectedJob;

  late final TextEditingController _licenseNumberController;
  late final TextEditingController _plateController;

  @override
  void initState() {
    super.initState();

    _licenseNumberController = TextEditingController(
      text: RiderProfile.licenseNumber,
    );
    _plateController = TextEditingController(text: RiderProfile.plateNumber);

    _loadVehicleAndJobFromProfile();
  }

  @override
  void dispose() {
    _licenseNumberController.dispose();
    _plateController.dispose();
    super.dispose();
  }

  void _loadVehicleAndJobFromProfile() {
    _selectedVehicle = _normalizedVehicleType(RiderProfile.vehicleType);

    final String job = RiderProfile.jobType.trim();

    if (job.isNotEmpty) {
      _selectedJob = job;
    } else {
      _selectedJob = _jobOptions.containsKey(_selectedVehicle)
          ? null
          : (_selectedVehicle == 'Rider Vehicle' ? null : jobDelivery);
    }
  }

  // Van and Truck riders must choose a job. The other vehicles are
  // always Delivery.
  bool get _needsJobChoice => _jobOptions.containsKey(_selectedVehicle);

  // Label shown for a job value (e.g. "Delivery (Large Items)" for trucks).
  String _jobDisplayLabel(String vehicle, String? job) {
    if (job == null || job.isEmpty) return 'Not selected';

    final options = _jobOptions[vehicle];

    if (options != null) {
      for (final option in options) {
        if (option['value'] == job) return option['label'] as String;
      }
    }

    return job;
  }

  // Normalize the registered vehicle type.
  String _normalizedVehicleType(String value) {
    final normalized = value.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]'), '');

    switch (normalized) {
      case 'van':
        return 'Van';
      case 'truck':
        return 'Truck';
      case 'tricycle':
        return 'Tricycle';
      case 'motorcycle':
      case 'motorbike':
      case 'motor':
        return 'Motorcycle';
      default:
        return value.trim().isEmpty ? 'Rider Vehicle' : value.trim();
    }
  }

  // Choose an icon based on the registered vehicle.
  IconData _vehicleIcon(String value) {
    switch (_normalizedVehicleType(value)) {
      case 'Van':
        return Icons.airport_shuttle_rounded;
      case 'Truck':
        return Icons.local_shipping_rounded;
      case 'Tricycle':
        return Icons.electric_rickshaw_outlined;
      case 'Motorcycle':
        return Icons.two_wheeler_rounded;
      default:
        return Icons.directions_car_rounded;
    }
  }

  // Shows the vehicle and the job, e.g.
  // "Van • Seller Pickup", "Truck • Long-distance Handover",
  // "Motorcycle • Delivery".
  String _vehicleJobLabel() {
    final vehicle = _normalizedVehicleType(RiderProfile.vehicleType);
    final job = RiderProfile.jobType.trim();

    if (job.isEmpty) return vehicle;

    return '$vehicle • ${_jobDisplayLabel(vehicle, job)}';
  }

  void _selectVehicle(String label) {
    if (_selectedVehicle == label) return;

    setState(() {
      _selectedVehicle = label;

      // Changing the vehicle resets the job, because the available jobs
      // depend on the vehicle.
      _selectedJob = _jobOptions.containsKey(label) ? null : jobDelivery;
    });
  }

  Future<void> _saveChanges() async {
    final int? riderId = RiderProfile.id;

    if (riderId == null) {
      _showMessage('Rider information is unavailable. Please login again.');
      return;
    }

    final vehicleType = _selectedVehicle;
    final licenseNumber = _licenseNumberController.text.trim();
    final plateNumber = _plateController.text.trim();

    if (vehicleType == 'Rider Vehicle' ||
        licenseNumber.isEmpty ||
        plateNumber.isEmpty) {
      _showMessage('Please complete all vehicle information fields.');
      return;
    }

    if (_needsJobChoice && _selectedJob == null) {
      _showMessage('Please choose what you will use your $vehicleType for.');
      return;
    }

    final String jobType = _needsJobChoice
        ? _selectedJob!
        : jobDelivery; // Motorcycle / Tricycle are always Delivery.

    setState(() => _isSaving = true);

    try {
      final response = await http
          .patch(
            Uri.parse('$_baseUrl/api/riders/$riderId/vehicle-information'),
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
            body: jsonEncode({
              'vehicle_type': vehicleType,
              'job_type': jobType,
              'license_number': licenseNumber,
              'plate_number': plateNumber,
            }),
          )
          .timeout(const Duration(seconds: 15));

      dynamic decoded;

      try {
        decoded = response.body.isNotEmpty
            ? jsonDecode(response.body)
            : <String, dynamic>{};
      } on FormatException {
        decoded = <String, dynamic>{};
      }

      final Map<String, dynamic> data = decoded is Map<String, dynamic>
          ? decoded
          : <String, dynamic>{};

      if (!mounted) return;

      if (response.statusCode == 200 && data['success'] == true) {
        final dynamic rider = data['rider'];

        if (rider is Map) {
          RiderProfile.setFromJson(Map<String, dynamic>.from(rider));
        }

        // Make sure the saved values are what the rider chose.
        RiderProfile.vehicleType = vehicleType;
        RiderProfile.jobType = jobType;

        if (RiderProfile.licenseNumber.isEmpty) {
          RiderProfile.licenseNumber = licenseNumber;
        }
        if (RiderProfile.plateNumber.isEmpty) {
          RiderProfile.plateNumber = plateNumber;
        }

        _licenseNumberController.text = RiderProfile.licenseNumber;
        _plateController.text = RiderProfile.plateNumber;

        setState(() {
          _loadVehicleAndJobFromProfile();
          _isEditing = false;
          _isSaving = false;
        });

        _showMessage(
          'Vehicle information updated successfully.',
          success: true,
        );
        return;
      }

      setState(() => _isSaving = false);

      _showMessage(
        data['message']?.toString() ?? 'Unable to update vehicle information.',
      );
    } catch (_) {
      if (!mounted) return;

      setState(() => _isSaving = false);
      _showMessage('Unable to connect to the server. Please try again.');
    }
  }

  void _cancelEditing() {
    _licenseNumberController.text = RiderProfile.licenseNumber;
    _plateController.text = RiderProfile.plateNumber;

    setState(() {
      _loadVehicleAndJobFromProfile();
      _isEditing = false;
    });
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

  @override
  Widget build(BuildContext context) {
    final vehicleType = _normalizedVehicleType(RiderProfile.vehicleType);

    final vehicleIcon = _vehicleIcon(vehicleType);
    final vehicleLabel = _vehicleJobLabel();

    final isApproved = RiderProfile.status.toLowerCase() == 'approved';

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
            // VEHICLE HEADER
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
                  Container(
                    width: 84,
                    height: 84,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: lightBeige,
                      border: Border.all(
                        color: Colors.white.withValues(alpha: 0.35),
                        width: 3,
                      ),
                    ),
                    child: Icon(vehicleIcon, size: 45, color: darkMaroon),
                  ),
                  const SizedBox(width: 18),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          vehicleLabel,
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                            color: Colors.white,
                          ),
                        ),
                        const SizedBox(height: 7),
                        const Text(
                          'Your registered vehicle and assignment type.',
                          style: TextStyle(
                            fontSize: 12,
                            color: lightBeige,
                            height: 1.4,
                          ),
                        ),
                        const SizedBox(height: 10),
                        Row(
                          children: [
                            Icon(
                              isApproved
                                  ? Icons.verified_outlined
                                  : Icons.pending_outlined,
                              color: lightBeige,
                              size: 16,
                            ),
                            const SizedBox(width: 6),
                            Expanded(
                              child: Text(
                                isApproved
                                    ? 'Approved Vehicle'
                                    : 'Registration Status',
                                style: const TextStyle(
                                  fontSize: 11,
                                  color: lightBeige,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 32),

            const Text(
              'Vehicle Details',
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.bold,
                color: darkMaroon,
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'These are the vehicle details submitted during registration.',
              style: TextStyle(color: brownBeige, fontSize: 12),
            ),

            const SizedBox(height: 20),

            // VEHICLE INFORMATION FIELDS
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.55),
                borderRadius: BorderRadius.circular(24),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (_isEditing) ...[
                    _buildVehicleTypeSelector(),
                    AnimatedSize(
                      duration: const Duration(milliseconds: 220),
                      curve: Curves.easeOut,
                      alignment: Alignment.topCenter,
                      child: _buildJobTypeEditor(),
                    ),
                  ] else ...[
                    _readOnlyField(
                      label: 'Vehicle Type',
                      icon: vehicleIcon,
                      value: _selectedVehicle,
                    ),
                    const SizedBox(height: 16),
                    _readOnlyField(
                      label: 'Job Type',
                      icon: Icons.work_outline_rounded,
                      value: _jobDisplayLabel(
                        _selectedVehicle,
                        RiderProfile.jobType.trim().isEmpty
                            ? _selectedJob
                            : RiderProfile.jobType.trim(),
                      ),
                    ),
                  ],
                  const SizedBox(height: 16),
                  _infoField(
                    label: 'Driver License Number',
                    icon: Icons.badge_outlined,
                    controller: _licenseNumberController,
                  ),
                  const SizedBox(height: 16),
                  _infoField(
                    label: 'Plate Number',
                    icon: Icons.confirmation_number_outlined,
                    controller: _plateController,
                  ),
                ],
              ),
            ),

            const SizedBox(height: 30),

            // IMPORTANT NOTE
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
              decoration: BoxDecoration(
                color: lightBeige.withValues(alpha: 0.55),
                borderRadius: BorderRadius.circular(20),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CircleAvatar(
                    radius: 19,
                    backgroundColor: darkMaroon,
                    child: Icon(
                      Icons.info_outline,
                      color: Colors.white,
                      size: 20,
                    ),
                  ),
                  SizedBox(width: 13),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Important',
                          style: TextStyle(
                            color: darkMaroon,
                            fontSize: 13,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        SizedBox(height: 4),
                        Text(
                          'Make sure your vehicle information matches your registration documents. Your job type decides which dashboard you will use, and it depends on your vehicle: Van (Seller Pickup or Delivery), Truck (Long-distance Handover or Delivery), Motorcycle and Tricycle (Delivery).',
                          style: TextStyle(
                            fontSize: 11,
                            color: maroon,
                            height: 1.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 32),

            // EDIT / SAVE BUTTON
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
                          setState(() {
                            _loadVehicleAndJobFromProfile();
                            _isEditing = true;
                          });
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
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: darkMaroon,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(28),
                  ),
                ),
              ),
            ),

            if (_isEditing) ...[
              const SizedBox(height: 10),
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

  // ============================================================
  // READ-ONLY FIELD (view mode)
  // ============================================================
  Widget _readOnlyField({
    required String label,
    required IconData icon,
    required String value,
  }) {
    return InputDecorator(
      decoration: InputDecoration(
        labelText: label,
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
        filled: true,
        fillColor: cream.withValues(alpha: 0.45),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide.none,
        ),
      ),
      child: Text(
        value,
        style: const TextStyle(
          color: darkMaroon,
          fontSize: 14,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  // ============================================================
  // VEHICLE TYPE SELECTOR (edit mode)
  // ============================================================
  Widget _buildVehicleTypeSelector() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Padding(
          padding: EdgeInsets.only(left: 4, bottom: 8),
          child: Text(
            'Vehicle Type',
            style: TextStyle(
              color: darkMaroon,
              fontSize: 13,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
        LayoutBuilder(
          builder: (context, constraints) {
            const double spacing = 8;
            final double width = (constraints.maxWidth - spacing) / 2;

            return Wrap(
              spacing: spacing,
              runSpacing: 8,
              children: _vehicleTypes.map((vehicle) {
                final String label = vehicle['label'] as String;
                final IconData icon = vehicle['icon'] as IconData;
                final bool selected = _selectedVehicle == label;

                return SizedBox(
                  width: width,
                  height: 48,
                  child: InkWell(
                    onTap: _isSaving ? null : () => _selectVehicle(label),
                    borderRadius: BorderRadius.circular(13),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      decoration: BoxDecoration(
                        color: selected
                            ? maroon
                            : Colors.white.withValues(alpha: 0.5),
                        borderRadius: BorderRadius.circular(13),
                        border: Border.all(
                          color: selected ? maroon : lightBeige,
                        ),
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
        ),
      ],
    );
  }

  // ============================================================
  // JOB TYPE EDITOR (edit mode)
  //   Van / Truck       -> choose between two jobs
  //   Motorcycle/Tricycle -> automatic Delivery
  // ============================================================
  Widget _buildJobTypeEditor() {
    if (_selectedVehicle == 'Rider Vehicle') {
      return const SizedBox(width: double.infinity);
    }

    if (!_needsJobChoice) {
      return Padding(
        padding: const EdgeInsets.only(top: 16),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: lightBeige.withValues(alpha: 0.30),
            borderRadius: BorderRadius.circular(14),
          ),
          child: Row(
            children: [
              const Icon(
                Icons.delivery_dining_outlined,
                color: maroon,
                size: 22,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  '$_selectedVehicle riders are assigned to Delivery automatically.',
                  style: const TextStyle(
                    color: maroon,
                    fontSize: 11,
                    height: 1.4,
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    }

    final List<Map<String, dynamic>> options = _jobOptions[_selectedVehicle]!;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SizedBox(height: 16),
        Text(
          'What will you use your $_selectedVehicle for?',
          style: const TextStyle(
            color: darkMaroon,
            fontSize: 13,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 3),
        const Text(
          'This decides which dashboard you will use.',
          style: TextStyle(color: brownBeige, fontSize: 10, height: 1.4),
        ),
        const SizedBox(height: 10),
        ...options.map((option) {
          final String value = option['value'] as String;
          final String label = option['label'] as String;
          final String description = option['description'] as String;
          final IconData icon = option['icon'] as IconData;
          final bool selected = _selectedJob == value;

          return Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: InkWell(
              onTap: _isSaving
                  ? null
                  : () => setState(() => _selectedJob = value),
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

  Widget _infoField({
    required String label,
    required IconData icon,
    required TextEditingController controller,
  }) {
    return TextField(
      controller: controller,
      enabled: _isEditing,
      textCapitalization: TextCapitalization.characters,
      style: const TextStyle(
        color: darkMaroon,
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
      decoration: InputDecoration(
        labelText: label,
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
        filled: true,
        fillColor: _isEditing ? Colors.white : cream.withValues(alpha: 0.45),
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

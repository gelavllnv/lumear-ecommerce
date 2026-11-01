import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_application_1/models/rider_profile.dart';

class VanVehicleInformationScreen extends StatefulWidget {
  const VanVehicleInformationScreen({super.key});

  @override
  State<VanVehicleInformationScreen> createState() =>
      _VanVehicleInformationScreenState();
}

class _VanVehicleInformationScreenState
    extends State<VanVehicleInformationScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFF3E9E1);

  final _formKey = GlobalKey<FormState>();

  // Prefilled with what the rider entered during signup.
  late final TextEditingController _plateNumberController =
      TextEditingController(
        text: (RiderProfile.plateNumber ?? '').toUpperCase(),
      );
  late final TextEditingController _licenseNumberController =
      TextEditingController(
        text: (RiderProfile.licenseNumber ?? '').toUpperCase(),
      );

  final FocusNode _plateFocus = FocusNode();
  final FocusNode _licenseFocus = FocusNode();

  @override
  void dispose() {
    _plateNumberController.dispose();
    _licenseNumberController.dispose();
    _plateFocus.dispose();
    _licenseFocus.dispose();
    super.dispose();
  }

  void _saveInformation() {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    FocusScope.of(context).unfocus();

    // Write back to the in-memory profile so other screens stay in sync.
    // Connect this to your backend later to persist it.
    RiderProfile.plateNumber = _plateNumberController.text.trim();
    RiderProfile.licenseNumber = _licenseNumberController.text.trim();

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: darkMaroon,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          content: const Row(
            children: [
              Icon(Icons.check_circle_rounded, color: Colors.white),
              SizedBox(width: 10),
              Expanded(
                child: Text('Vehicle information validated successfully.'),
              ),
            ],
          ),
        ),
      );
  }

  String? _validatePlate(String? value) {
    final text = (value ?? '').trim();
    if (text.isEmpty) return 'Plate number is required.';

    final compact = text.replaceAll(RegExp(r'[^A-Za-z0-9]'), '');
    if (compact.length < 5 || compact.length > 7) {
      return 'Enter a valid plate number (e.g. ABC 1234).';
    }
    return null;
  }

  String? _validateLicense(String? value) {
    final text = (value ?? '').trim();
    if (text.isEmpty) return "Driver's license number is required.";

    final compact = text.replaceAll('-', '');
    if (compact.length < 8) {
      return 'Enter a valid license number.';
    }
    return null;
  }

  InputDecoration _inputDecoration({
    required String hint,
    required IconData icon,
  }) {
    OutlineInputBorder border(Color color, [double width = 1.2]) {
      return OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: BorderSide(color: color, width: width),
      );
    }

    return InputDecoration(
      hintText: hint,
      hintStyle: TextStyle(color: brownBeige.withValues(alpha: 0.6)),
      prefixIcon: Icon(icon, color: brownBeige),
      filled: true,
      fillColor: const Color(0xFFFFFAF6),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      border: border(lightBeige),
      enabledBorder: border(lightBeige.withValues(alpha: 0.7)),
      focusedBorder: border(maroon, 1.8),
      errorBorder: border(Colors.red.shade600),
      focusedErrorBorder: border(Colors.red.shade600, 1.8),
    );
  }

  Widget _fieldLabel(String label, {String? helper}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: darkMaroon,
            ),
          ),
          if (helper != null) ...[
            const SizedBox(height: 2),
            Text(
              helper,
              style: const TextStyle(fontSize: 12, color: brownBeige),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildHeader() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [darkMaroon, maroon],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: darkMaroon.withValues(alpha: 0.22),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            height: 56,
            width: 56,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.16),
              borderRadius: BorderRadius.circular(16),
            ),
            child: const Icon(
              Icons.local_shipping_rounded,
              color: Colors.white,
              size: 30,
            ),
          ),
          const SizedBox(width: 16),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Vehicle Details',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                SizedBox(height: 4),
                Text(
                  'Add your plate number and license number so we can verify you as a rider.',
                  style: TextStyle(color: cream, fontSize: 12.5, height: 1.4),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFormCard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: lightBeige.withValues(alpha: 0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _fieldLabel('Plate Number', helper: 'As shown on your vehicle plate'),
          TextFormField(
            controller: _plateNumberController,
            focusNode: _plateFocus,
            textCapitalization: TextCapitalization.characters,
            textInputAction: TextInputAction.next,
            onFieldSubmitted: (_) => _licenseFocus.requestFocus(),
            autovalidateMode: AutovalidateMode.onUserInteraction,
            inputFormatters: [
              FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z0-9 ]')),
              LengthLimitingTextInputFormatter(8),
              _UpperCaseFormatter(),
            ],
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              letterSpacing: 1.2,
              color: darkMaroon,
            ),
            decoration: _inputDecoration(
              hint: 'e.g. ABC 1234',
              icon: Icons.confirmation_number_outlined,
            ),
            validator: _validatePlate,
          ),
          const SizedBox(height: 22),
          _fieldLabel(
            "Driver's License Number",
            helper: 'Found on the front of your license',
          ),
          TextFormField(
            controller: _licenseNumberController,
            focusNode: _licenseFocus,
            textCapitalization: TextCapitalization.characters,
            textInputAction: TextInputAction.done,
            onFieldSubmitted: (_) => _saveInformation(),
            autovalidateMode: AutovalidateMode.onUserInteraction,
            inputFormatters: [
              FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z0-9-]')),
              LengthLimitingTextInputFormatter(16),
              _UpperCaseFormatter(),
            ],
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              letterSpacing: 1.0,
              color: darkMaroon,
            ),
            decoration: _inputDecoration(
              hint: 'e.g. N01-23-456789',
              icon: Icons.badge_outlined,
            ),
            validator: _validateLicense,
          ),
        ],
      ),
    );
  }

  Widget _buildInfoNote() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF3D6),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFEAD39A)),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.info_outline_rounded, color: Color(0xFF815C13), size: 20),
          SizedBox(width: 10),
          Expanded(
            child: Text(
              'Make sure these details match your uploaded OR/CR and '
              "driver's license under My Documents.",
              style: TextStyle(
                fontSize: 12,
                height: 1.5,
                color: Color(0xFF684D1B),
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => FocusScope.of(context).unfocus(),
      child: Scaffold(
        backgroundColor: cream,
        appBar: AppBar(
          backgroundColor: darkMaroon,
          foregroundColor: Colors.white,
          elevation: 0,
          title: const Text(
            'Vehicle Information',
            style: TextStyle(fontWeight: FontWeight.bold),
          ),
        ),
        body: SafeArea(
          child: Column(
            children: [
              Expanded(
                child: SingleChildScrollView(
                  keyboardDismissBehavior:
                      ScrollViewKeyboardDismissBehavior.onDrag,
                  padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
                  child: Form(
                    key: _formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _buildHeader(),
                        const SizedBox(height: 20),
                        _buildFormCard(),
                        const SizedBox(height: 16),
                        _buildInfoNote(),
                      ],
                    ),
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.07),
                      blurRadius: 12,
                      offset: const Offset(0, -3),
                    ),
                  ],
                ),
                child: SizedBox(
                  width: double.infinity,
                  height: 54,
                  child: ElevatedButton.icon(
                    onPressed: _saveInformation,
                    icon: const Icon(Icons.save_outlined),
                    label: const Text(
                      'Save Information',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: darkMaroon,
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
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
}

class _UpperCaseFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    return newValue.copyWith(text: newValue.text.toUpperCase());
  }
}

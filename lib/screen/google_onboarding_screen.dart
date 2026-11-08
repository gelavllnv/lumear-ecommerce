import 'package:flutter/material.dart';

import '../services/mobile_number.dart';
import '../services/api_service.dart';
import '../widgets/registration_address_fields.dart';
import '../widgets/account_picture.dart';
import '../widgets/seller_registration_fields.dart';
import '../seller/product_media.dart';

class GoogleOnboardingScreen extends StatefulWidget {
  const GoogleOnboardingScreen({
    super.key,
    required this.initialName,
    required this.email,
    this.isNewAccount = true,
    this.existingRole,
    required this.onComplete,
  });
  final String initialName;
  final String email;
  final bool isNewAccount;
  final String? existingRole;
  final Future<Map<String, dynamic>> Function(Map<String, dynamic>) onComplete;
  @override
  State<GoogleOnboardingScreen> createState() => _GoogleOnboardingScreenState();
}

class _GoogleOnboardingScreenState extends State<GoogleOnboardingScreen> {
  final form = GlobalKey<FormState>();
  late final name = TextEditingController(text: widget.initialName);
  final phone = TextEditingController();
  final password = TextEditingController();
  final confirmPassword = TextEditingController();
  final recipient = TextEditingController();
  final deliveryPhone = TextEditingController();
  final street = TextEditingController();
  final province = TextEditingController();
  final city = TextEditingController();
  final barangay = TextEditingController();
  final postal = TextEditingController();
  final shopName = TextEditingController();
  final shopDescription = TextEditingController();
  final taxNumber = TextEditingController();
  Map<String, Map<String, String>> sellerDocuments = {};
  bool pickingDocuments = false;
  late String role = widget.existingRole ?? 'buyer';
  DateTime? birthDate;
  ProductMedia? photo;
  bool picking = false;
  bool saving = false;
  String? saveError;
  bool showPassword = false;
  bool showConfirmation = false;

  @override
  void dispose() {
    for (final c in [
      name,
      phone,
      password,
      confirmPassword,
      recipient,
      deliveryPhone,
      street,
      province,
      city,
      barangay,
      postal,
      shopName,
      shopDescription,
      taxNumber,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  Widget field(
    String label,
    TextEditingController controller, {
    bool secret = false,
    bool mobile = false,
    int maxLength = 150,
    String? Function(String?)? validator,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 16),
    child: TextFormField(
      controller: controller,
      obscureText:
          secret && !(controller == password ? showPassword : showConfirmation),
      enableSuggestions: !secret,
      autocorrect: !secret,
      maxLength: maxLength,
      keyboardType: mobile ? TextInputType.phone : TextInputType.text,
      inputFormatters: mobile ? MobileNumber.inputFormatters : null,
      decoration: InputDecoration(
        labelText: label,
        counterText: '',
        suffixIcon: secret
            ? IconButton(
                tooltip:
                    (controller == password ? showPassword : showConfirmation)
                    ? 'Hide $label'
                    : 'Show $label',
                icon: Icon(
                  (controller == password ? showPassword : showConfirmation)
                      ? Icons.visibility_off_outlined
                      : Icons.visibility_outlined,
                ),
                onPressed: () => setState(() {
                  if (controller == password) {
                    showPassword = !showPassword;
                  } else {
                    showConfirmation = !showConfirmation;
                  }
                }),
              )
            : null,
      ),
      validator:
          validator ??
          (v) => mobile
              ? (MobileNumber.isValid(v ?? '') ? null : MobileNumber.error)
              : ((v?.trim().isEmpty ?? true)
                    ? 'This field is required.'
                    : null),
    ),
  );

  Future<void> chooseBirthDate(FormFieldState<DateTime> state) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: birthDate ?? DateTime(now.year - 18, now.month, now.day),
      firstDate: DateTime(now.year - 120, now.month, now.day),
      lastDate: DateTime(now.year, now.month, now.day),
    );
    if (!mounted || picked == null) return;
    setState(() => birthDate = picked);
    state.didChange(picked);
  }

  Future<void> submit() async {
    if (saving ||
        picking ||
        pickingDocuments ||
        !form.currentState!.validate()) {
      return;
    }
    final details = <String, dynamic>{
      'account_type': role,
      'name': name.text.trim(),
      'display_name': name.text.trim(),
      'phone': phone.text.trim(),
      'date_of_birth': birthDate!.toIso8601String().split('T').first,
      if (widget.isNewAccount) 'password': password.text,
      'address': {
        'label': 'Home',
        'name': recipient.text.trim(),
        'phone': deliveryPhone.text.trim(),
        'street': street.text.trim(),
        'province': province.text.trim(),
        'city': city.text.trim(),
        'barangay': barangay.text.trim(),
        'postal_code': postal.text.trim(),
        'default': true,
      },
      if (photo != null) 'photo': photo!.toJson(),
      if (widget.isNewAccount && role == 'seller') ...{
        'shop_name': shopName.text.trim(),
        'shop_description': shopDescription.text.trim(),
        'tax_number': taxNumber.text.trim(),
        'documents': sellerDocuments,
      },
    };
    setState(() {
      saving = true;
      saveError = null;
    });
    try {
      final session = await widget.onComplete(details);
      if (!mounted) return;
      Navigator.pop(context, session);
    } catch (error) {
      if (!mounted) return;
      setState(
        () => saveError = error is ApiException
            ? error.message
            : 'Unable to complete registration. Please try again.',
      );
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Complete your Google account')),
    body: PopScope(
      canPop: !saving,
      child: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 480),
              child: Form(
                key: form,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Create your account',
                      style: Theme.of(context).textTheme.headlineMedium,
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Complete your details before entering Lumear. You must be 18 or older.',
                    ),
                    const SizedBox(height: 24),
                    SegmentedButton<String>(
                      segments: const [
                        ButtonSegment(value: 'buyer', label: Text('Buyer')),
                        ButtonSegment(value: 'seller', label: Text('Seller')),
                      ],
                      selected: {role},
                      onSelectionChanged:
                          widget.existingRole != null ||
                              pickingDocuments ||
                              saving
                          ? null
                          : (v) => setState(() => role = v.single),
                    ),
                    if (role == 'seller') ...[
                      const SizedBox(height: 12),
                      const Text(
                        'Seller shops require approval before selling.',
                      ),
                    ],
                    const SizedBox(height: 24),
                    AccountPicturePicker(
                      photo: photo,
                      onChanged: (v) => setState(() => photo = v),
                      onBusyChanged: (v) => setState(() => picking = v),
                    ),
                    const SizedBox(height: 24),
                    field(
                      'Full name',
                      name,
                      maxLength: 100,
                      validator: (v) => (v?.trim().length ?? 0) < 2
                          ? 'Enter your full name.'
                          : null,
                    ),
                    TextFormField(
                      initialValue: widget.email,
                      readOnly: true,
                      decoration: const InputDecoration(
                        labelText: 'Verified Google email',
                      ),
                    ),
                    const SizedBox(height: 16),
                    field('Contact number', phone, mobile: true),
                    FormField<DateTime>(
                      validator: (_) {
                        final date = birthDate;
                        if (date == null) {
                          return 'Please select your date of birth.';
                        }
                        final now = DateTime.now();
                        final age =
                            now.year -
                            date.year -
                            ((now.month < date.month ||
                                    (now.month == date.month &&
                                        now.day < date.day))
                                ? 1
                                : 0);
                        return age < 18 || age > 120
                            ? 'You must be 18 or older (up to 120).'
                            : null;
                      },
                      builder: (state) => Padding(
                        padding: const EdgeInsets.only(bottom: 16),
                        child: InkWell(
                          onTap: () => chooseBirthDate(state),
                          child: InputDecorator(
                            decoration: InputDecoration(
                              labelText: 'Date of birth',
                              errorText: state.errorText,
                              suffixIcon: const Icon(
                                Icons.calendar_today_outlined,
                              ),
                            ),
                            child: Text(
                              birthDate == null
                                  ? 'Select your date of birth'
                                  : birthDate!
                                        .toIso8601String()
                                        .split('T')
                                        .first,
                            ),
                          ),
                        ),
                      ),
                    ),
                    if (widget.isNewAccount) ...[
                      field(
                        'Password',
                        password,
                        secret: true,
                        maxLength: 72,
                        validator: (v) => (v?.length ?? 0) < 8
                            ? 'Use at least 8 characters.'
                            : null,
                      ),
                      field(
                        'Confirm password',
                        confirmPassword,
                        secret: true,
                        maxLength: 72,
                        validator: (v) => v != password.text
                            ? 'Passwords do not match.'
                            : null,
                      ),
                    ],
                    const Divider(),
                    const Text('Default delivery address'),
                    const SizedBox(height: 16),
                    field('Recipient name', recipient),
                    field(
                      'Delivery contact number',
                      deliveryPhone,
                      mobile: true,
                    ),
                    field('Street, building, or unit', street),
                    RegistrationAddressFields(
                      province: province,
                      city: city,
                      barangay: barangay,
                      postal: postal,
                    ),
                    if (widget.isNewAccount && role == 'seller') ...[
                      const Divider(),
                      const Text('Shop details'),
                      const SizedBox(height: 16),
                      field('Shop name', shopName),
                      field(
                        'Shop description',
                        shopDescription,
                        maxLength: 2000,
                      ),
                      field('TIN number', taxNumber, maxLength: 255),
                      SellerRegistrationFields(
                        onChanged: (value) => sellerDocuments = value,
                        onBusyChanged: (value) =>
                            setState(() => pickingDocuments = value),
                      ),
                    ],
                    const SizedBox(height: 8),
                    if (saveError != null) ...[
                      Text(
                        saveError!,
                        key: const ValueKey('registrationError'),
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                    FilledButton(
                      onPressed: picking || pickingDocuments || saving
                          ? null
                          : submit,
                      child: Text(
                        saving
                            ? 'Creating your account…'
                            : 'Complete registration',
                      ),
                    ),
                    TextButton(
                      onPressed: saving ? null : () => Navigator.pop(context),
                      child: const Text('Cancel'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../widgets/account_picture.dart';
import '../seller/product_media.dart';

class GoogleOnboardingScreen extends StatefulWidget {
  const GoogleOnboardingScreen({
    super.key,
    required this.initialName,
    this.existingRole,
  });
  final String initialName;
  final String? existingRole;
  @override
  State<GoogleOnboardingScreen> createState() => _GoogleOnboardingScreenState();
}

class _GoogleOnboardingScreenState extends State<GoogleOnboardingScreen> {
  final form = GlobalKey<FormState>();
  late final display = TextEditingController(text: widget.initialName);
  final age = TextEditingController();
  late String role = widget.existingRole ?? 'buyer';
  ProductMedia? photo;
  bool picking = false;
  @override
  void dispose() {
    display.dispose();
    age.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Complete your Google account')),
    body: SafeArea(
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
                    'Make it yours',
                    style: Theme.of(context).textTheme.headlineMedium,
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Choose how your name appears in Lumear, tell us your age, and select your account type.',
                  ),
                  const SizedBox(height: 24),
                  AccountPicturePicker(
                    photo: photo,
                    onChanged: (v) => setState(() => photo = v),
                    onBusyChanged: (v) => setState(() => picking = v),
                  ),
                  const SizedBox(height: 24),
                  TextFormField(
                    controller: display,
                    maxLength: 100,
                    decoration: const InputDecoration(
                      labelText: 'Display name',
                    ),
                    validator: (v) => (v?.trim().length ?? 0) < 2
                        ? 'Enter at least 2 characters.'
                        : null,
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: age,
                    keyboardType: TextInputType.number,
                    inputFormatters: [
                      FilteringTextInputFormatter.digitsOnly,
                      LengthLimitingTextInputFormatter(3),
                    ],
                    decoration: const InputDecoration(labelText: 'Age'),
                    validator: (v) {
                      final n = int.tryParse(v ?? '');
                      return n == null || n < 18 || n > 120
                          ? 'Enter an age from 18 to 120.'
                          : null;
                    },
                  ),
                  const SizedBox(height: 24),
                  const Text('Account type'),
                  const SizedBox(height: 8),
                  SegmentedButton<String>(
                    segments: const [
                      ButtonSegment(
                        value: 'buyer',
                        label: Text('Buyer'),
                        icon: Icon(Icons.shopping_bag_outlined),
                      ),
                      ButtonSegment(
                        value: 'seller',
                        label: Text('Seller'),
                        icon: Icon(Icons.storefront_outlined),
                      ),
                    ],
                    selected: {role},
                    onSelectionChanged: widget.existingRole != null
                        ? null
                        : (v) => setState(() => role = v.single),
                  ),
                  if (role == 'seller') ...[
                    const SizedBox(height: 12),
                    const Text('Seller shops require approval before selling.'),
                  ],
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: picking
                        ? null
                        : () {
                            if (form.currentState!.validate()) {
                              Navigator.pop(context, {
                                'account_type': role,
                                'display_name': display.text.trim(),
                                'age': int.parse(age.text),
                                if (photo != null) 'photo': photo!.toJson(),
                              });
                            }
                          },
                    child: const Text('Continue'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../design/lumear_theme.dart';
import '../services/api_service.dart';
import '../services/mobile_number.dart';
import '../widgets/account_picture.dart';
import '../seller/product_media.dart';

class AccountProfileScreen extends StatefulWidget {
  const AccountProfileScreen({
    super.key,
    required this.initialProfile,
    required this.connected,
    this.onSaved,
  });
  final Map<String, dynamic> initialProfile;
  final bool connected;
  final Future<void> Function(Map<String, dynamic>)? onSaved;
  @override
  State<AccountProfileScreen> createState() => _AccountProfileScreenState();
}

class _AccountProfileScreenState extends State<AccountProfileScreen> {
  final form = GlobalKey<FormState>();
  late final fields = {
    'name': TextEditingController(
      text: widget.initialProfile['name'] as String? ?? '',
    ),
    'display_name': TextEditingController(
      text:
          widget.initialProfile['display_name'] as String? ??
          widget.initialProfile['name'] as String? ??
          '',
    ),
    'username': TextEditingController(
      text: widget.initialProfile['username'] as String? ?? '',
    ),
    'phone': TextEditingController(
      text: MobileNumber.forEditing(
        widget.initialProfile['phone'] as String? ?? '',
      ),
    ),
    'age': TextEditingController(
      text: widget.initialProfile['age']?.toString() ?? '',
    ),
  };
  late ProductMedia? photo = accountPhoto(widget.initialProfile['photo']);
  bool saving = false, picking = false, photoChanged = false;
  String? error;
  @override
  void dispose() {
    for (final c in fields.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> save() async {
    if (saving || picking || !form.currentState!.validate()) return;
    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      saving = true;
      error = null;
    });
    final body = <String, dynamic>{
      for (final entry in fields.entries) entry.key: entry.value.text.trim(),
    };
    body['age'] = int.tryParse(fields['age']!.text);
    if (photoChanged) body['photo'] = photo?.toJson();
    try {
      Map<String, dynamic> saved;
      if (widget.connected) {
        final response = await ApiService.patch('/me', body: body);
        saved = response is Map && response['profile'] is Map
            ? Map<String, dynamic>.from(response['profile'])
            : Map<String, dynamic>.from(await ApiService.get('/me'));
      } else {
        saved = {...widget.initialProfile, ...body, 'photo': photo?.toJson()};
      }
      await widget.onSaved?.call(saved);
      if (mounted) Navigator.pop(context, saved);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => LumearAppearance.build(
    context,
    () => PopScope(
      canPop: !saving,
      child: Scaffold(
        appBar: AppBar(title: const Text('My Account')),
        body: SafeArea(
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 600),
              child: Form(
                key: form,
                child: ListView(
                  padding: const EdgeInsets.all(24),
                  children: [
                    AccountPicturePicker(
                      photo: photo,
                      enabled: !saving,
                      onBusyChanged: (v) => setState(() => picking = v),
                      onChanged: (v) => setState(() {
                        photo = v;
                        photoChanged = true;
                      }),
                    ),
                    const SizedBox(height: 24),
                    Text(widget.initialProfile['email'] as String? ?? ''),
                    const SizedBox(height: 16),
                    for (final entry in {
                      'name': 'Full name',
                      'display_name': 'Display name',
                      'username': 'Username',
                      'phone': 'Mobile number',
                      'age': 'Age',
                    }.entries) ...[
                      TextFormField(
                        controller: fields[entry.key],
                        enabled: !saving,
                        keyboardType: ['age', 'phone'].contains(entry.key)
                            ? TextInputType.number
                            : TextInputType.text,
                        inputFormatters: entry.key == 'phone'
                            ? MobileNumber.inputFormatters
                            : entry.key == 'age'
                            ? [
                                FilteringTextInputFormatter.digitsOnly,
                                LengthLimitingTextInputFormatter(3),
                              ]
                            : null,
                        decoration: InputDecoration(
                          labelText: entry.value,
                          prefixText: entry.key == 'username' ? '@' : null,
                        ),
                        validator: (v) {
                          final value = v?.trim() ?? '';
                          if (entry.key == 'age') {
                            final n = int.tryParse(value);
                            return value.isEmpty ||
                                    n != null && n >= 18 && n <= 120
                                ? null
                                : 'Enter an age from 18 to 120.';
                          }
                          if (entry.key == 'phone') {
                            return MobileNumber.isValid(value)
                                ? null
                                : MobileNumber.error;
                          }
                          if (entry.key == 'username') {
                            return RegExp(r'^[A-Za-z0-9_]{3,30}$')
                                    .hasMatch(value)
                                ? null
                                : 'Use 3–30 letters, numbers or underscores.';
                          }
                          return value.length >= 2 && value.length <= 100
                              ? null
                              : 'Use 2–100 characters.';
                        },
                      ),
                      const SizedBox(height: 16),
                    ],
                    if (error != null) Text(error!),
                    if (saving) const LinearProgressIndicator(),
                    FilledButton(
                      onPressed: saving || picking ? null : save,
                      child: const Text('Save changes'),
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

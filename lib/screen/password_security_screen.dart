import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../login_page.dart';
import '../services/buyer_data.dart';

/// Both roles use the same server-verified email challenge flow.
class PasswordSecurityScreen extends StatefulWidget {
  const PasswordSecurityScreen({
    super.key,
    required this.connected,
    this.changePassword = false,
    this.initialEmail = '',
  });
  final bool connected, changePassword;
  final String initialEmail;
  @override
  State<PasswordSecurityScreen> createState() => _PasswordSecurityScreenState();
}

class _PasswordSecurityScreenState extends State<PasswordSecurityScreen> {
  final form = GlobalKey<FormState>();
  late final email = TextEditingController(text: widget.initialEmail);
  final current = TextEditingController();
  final code = TextEditingController();
  final password = TextEditingController();
  final confirm = TextEditingController();
  String? challenge, error;
  bool busy = false, complete = false;
  String get purpose => widget.changePassword ? 'change' : 'reset';
  @override
  void dispose() {
    for (final c in [email, current, code, password, confirm]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> submit() async {
    if (busy || !widget.connected || !form.currentState!.validate()) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      if (challenge == null) {
        final response = await ApiService.post(
          '/auth/password/$purpose/request',
          body: {
            if (!widget.changePassword) 'email': email.text.trim(),
            if (widget.changePassword) 'current_password': current.text,
          },
        );
        if (response is! Map ||
            response['challenge_id'] is! String ||
            (response['challenge_id'] as String).isEmpty) {
          throw const ApiException(
            'The service did not return a verification challenge. Please try again.',
          );
        }
        if (!mounted) return;
        setState(() => challenge = response['challenge_id'] as String);
        current.clear();
      } else {
        final response = await ApiService.post(
          '/auth/password/$purpose/confirm',
          body: {
            'challenge_id': challenge,
            'code': code.text.trim(),
            'new_password': password.text,
            'confirm_password': confirm.text,
          },
        );
        if (response is! Map || response['updated'] != true) {
          throw const ApiException(
            'The service did not confirm a password update. Please try again.',
          );
        }
        if (!mounted) return;
        password.clear();
        confirm.clear();
        code.clear();
        setState(() => complete = true);
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(
        widget.changePassword ? 'Change password' : 'Forgot password',
      ),
    ),
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
                  if (!widget.connected) ...[
                    const Text(
                      'Email verification requires the shared account service. No email is sent and no password is changed in local preview.',
                    ),
                  ] else if (complete) ...[
                    const Icon(Icons.check_circle_outline, size: 48),
                    const Text(
                      'Password updated. Sign in again with your new password.',
                    ),
                    FilledButton(
                      onPressed: () {
                        if (widget.changePassword) {
                          ApiService.setToken(null);
                          BuyerData.instance.reset();
                          Navigator.of(context).pushAndRemoveUntil(
                            MaterialPageRoute(
                              builder: (_) => const LoginPage(useBackend: true),
                            ),
                            (route) => false,
                          );
                        } else {
                          Navigator.pop(context);
                        }
                      },
                      child: const Text('Sign in'),
                    ),
                  ] else ...[
                    Text(
                      challenge == null
                          ? widget.changePassword
                                ? 'Confirm your current password to request a verification code at your registered account email.'
                                : 'Enter your registered email. If the account is eligible, you will receive a verification code.'
                          : 'If your account is eligible, a code has been sent to your registered email. Enter it below to confirm your new password.',
                    ),
                    const SizedBox(height: 20),
                    if (challenge == null && !widget.changePassword)
                      TextFormField(
                        controller: email,
                        keyboardType: TextInputType.emailAddress,
                        decoration: const InputDecoration(
                          labelText: 'Registered email',
                        ),
                        validator: (v) =>
                            RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$')
                                .hasMatch(v!.trim())
                            ? null
                            : 'Enter a valid email.',
                      ),
                    if (challenge == null && widget.changePassword)
                      TextFormField(
                        controller: current,
                        obscureText: true,
                        decoration: const InputDecoration(
                          labelText: 'Current password',
                        ),
                        validator: (v) =>
                            v!.isEmpty ? 'Enter your current password.' : null,
                      ),
                    if (challenge != null) ...[
                      TextFormField(
                        controller: code,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(
                          labelText: 'Email verification code',
                        ),
                        validator: (v) => RegExp(r'^\d{6}$').hasMatch(v!.trim())
                            ? null
                            : 'Enter the 6-digit code.',
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: password,
                        obscureText: true,
                        decoration: const InputDecoration(
                          labelText: 'New password',
                        ),
                        validator: (v) =>
                            RegExp(r'^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$')
                                .hasMatch(v!)
                            ? null
                            : 'Use at least 8 characters, uppercase, lowercase and a number.',
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: confirm,
                        obscureText: true,
                        decoration: const InputDecoration(
                          labelText: 'Confirm new password',
                        ),
                        validator: (v) => v == password.text
                            ? null
                            : 'Passwords do not match.',
                      ),
                    ],
                    if (error != null) ...[
                      const SizedBox(height: 12),
                      Text(error!),
                    ],
                    const SizedBox(height: 20),
                    if (busy) const LinearProgressIndicator(),
                    FilledButton(
                      onPressed: busy ? null : submit,
                      child: Text(
                        challenge == null
                            ? 'Send verification code'
                            : 'Confirm password',
                      ),
                    ),
                    if (challenge != null)
                      TextButton(
                        onPressed: busy
                            ? null
                            : () {
                                setState(() {
                                  challenge = null;
                                  error = null;
                                  code.clear();
                                  password.clear();
                                  confirm.clear();
                                });
                              },
                        child: const Text('Request a new code'),
                      ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

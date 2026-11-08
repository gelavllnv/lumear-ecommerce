import 'package:flutter/material.dart';

import '../services/api_service.dart';

class GoogleOtpScreen extends StatefulWidget {
  const GoogleOtpScreen({
    super.key,
    required this.challengeId,
    required this.idToken,
  });
  final String challengeId, idToken;
  @override
  State<GoogleOtpScreen> createState() => _GoogleOtpScreenState();
}

class _GoogleOtpScreenState extends State<GoogleOtpScreen> {
  final code = TextEditingController();
  late String challenge = widget.challengeId;
  bool busy = false;
  String? error;
  @override
  void dispose() {
    code.dispose();
    super.dispose();
  }

  Future<void> submit({bool resend = false}) async {
    if (busy) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final result = await ApiService.post(
        resend ? '/auth/google' : '/auth/google/verify',
        body: resend
            ? {'id_token': widget.idToken}
            : {'challenge_id': challenge, 'code': code.text.trim()},
      );
      if (!mounted) return;
      if (resend) {
        setState(() {
          challenge = result['challenge_id'] as String;
          code.clear();
        });
      } else {
        final proof = result['verification_token'];
        if (proof is! String || proof.isEmpty) {
          throw const ApiException(
            'Verification could not be completed. Try again.',
          );
        }
        Navigator.pop(context, proof);
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Verify Google sign-in')),
    body: SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Enter the 6-digit code sent to your Google account email to continue. Codes expire in 10 minutes.',
          ),
          const SizedBox(height: 20),
          TextField(
            controller: code,
            enabled: !busy,
            maxLength: 6,
            keyboardType: TextInputType.number,
            autofillHints: const [AutofillHints.oneTimeCode],
            decoration: const InputDecoration(
              labelText: 'Email verification code',
            ),
          ),
          if (error != null) Text(error!),
          if (busy) const LinearProgressIndicator(),
          FilledButton(
            onPressed: busy ? null : submit,
            child: const Text('Verify and continue'),
          ),
          TextButton(
            onPressed: busy ? null : () => submit(resend: true),
            child: const Text('Resend code (wait 60 seconds)'),
          ),
        ],
      ),
    ),
  );
}

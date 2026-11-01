import 'package:flutter/material.dart';

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);

  final _formKey = GlobalKey<FormState>();
  final _currentPasswordController = TextEditingController();
  final _newPasswordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  bool _hideCurrent = true;
  bool _hideNew = true;
  bool _hideConfirm = true;
  bool _isSaving = false;

  @override
  void dispose() {
    _currentPasswordController.dispose();
    _newPasswordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  String? _validateNewPassword(String? value) {
    if (value == null || value.isEmpty) {
      return 'Please enter your new password.';
    }
    if (value.length < 8) {
      return 'Password must be at least 8 characters.';
    }
    if (!RegExp(r'[A-Z]').hasMatch(value)) {
      return 'Include at least one uppercase letter.';
    }
    if (!RegExp(r'[0-9]').hasMatch(value)) {
      return 'Include at least one number.';
    }
    return null;
  }

  Future<void> _changePassword() async {
    FocusScope.of(context).unfocus();

    if (!_formKey.currentState!.validate()) return;

    if (_currentPasswordController.text == _newPasswordController.text) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Your new password must be different from your current password.',
          ),
        ),
      );
      return;
    }

    setState(() => _isSaving = true);

    // UI only: connect this to your authenticated backend endpoint.
    // The backend must verify the current password, hash the new password,
    // save it, and return success before the app displays success.
    await Future<void>.delayed(const Duration(milliseconds: 300));

    if (!mounted) return;
    setState(() => _isSaving = false);

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        backgroundColor: Colors.red,
        content: Text(
          'Password update is not connected yet. Connect the backend API to save your new password.',
        ),
      ),
    );
  }

  Widget _passwordField({
    required String label,
    required String hint,
    required TextEditingController controller,
    required bool hidden,
    required VoidCallback onToggleVisibility,
    String? Function(String?)? validator,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            color: darkMaroon,
            fontSize: 13,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 8),
        TextFormField(
          controller: controller,
          obscureText: hidden,
          validator:
              validator ??
              (value) {
                if (value == null || value.isEmpty) {
                  return 'This field is required.';
                }
                return null;
              },
          decoration: InputDecoration(
            hintText: hint,
            prefixIcon: const Icon(Icons.lock_outline_rounded, color: maroon),
            suffixIcon: IconButton(
              onPressed: onToggleVisibility,
              icon: Icon(
                hidden
                    ? Icons.visibility_off_outlined
                    : Icons.visibility_outlined,
                color: brownBeige,
              ),
            ),
            filled: true,
            fillColor: Colors.white,
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 16,
            ),
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: const BorderSide(color: lightBeige),
            ),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: const BorderSide(color: lightBeige),
            ),
            focusedBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(14),
              borderSide: const BorderSide(color: darkMaroon, width: 1.5),
            ),
            errorMaxLines: 2,
          ),
        ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: cream,
        foregroundColor: darkMaroon,
        elevation: 0,
        title: const Text(
          'Change Password',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            Center(
              child: Container(
                width: 76,
                height: 76,
                decoration: BoxDecoration(
                  color: maroon,
                  borderRadius: BorderRadius.circular(24),
                ),
                child: const Icon(
                  Icons.lock_reset_rounded,
                  color: Colors.white,
                  size: 38,
                ),
              ),
            ),
            const SizedBox(height: 18),
            const Text(
              'Keep your account secure',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: darkMaroon,
                fontSize: 21,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Enter your current password, then choose a new password.',
              textAlign: TextAlign.center,
              style: TextStyle(color: brownBeige, fontSize: 13, height: 1.5),
            ),
            const SizedBox(height: 28),
            Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _passwordField(
                    label: 'Current Password',
                    hint: 'Enter current password',
                    controller: _currentPasswordController,
                    hidden: _hideCurrent,
                    onToggleVisibility: () {
                      setState(() => _hideCurrent = !_hideCurrent);
                    },
                  ),
                  const SizedBox(height: 20),
                  _passwordField(
                    label: 'New Password',
                    hint: 'Enter new password',
                    controller: _newPasswordController,
                    hidden: _hideNew,
                    onToggleVisibility: () {
                      setState(() => _hideNew = !_hideNew);
                    },
                    validator: _validateNewPassword,
                  ),
                  const SizedBox(height: 20),
                  _passwordField(
                    label: 'Confirm New Password',
                    hint: 'Re-enter new password',
                    controller: _confirmPasswordController,
                    hidden: _hideConfirm,
                    onToggleVisibility: () {
                      setState(() => _hideConfirm = !_hideConfirm);
                    },
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Please confirm your new password.';
                      }
                      if (value != _newPasswordController.text) {
                        return 'Passwords do not match.';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 12),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.65),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: lightBeige),
                    ),
                    child: const Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                          Icons.info_outline_rounded,
                          color: maroon,
                          size: 20,
                        ),
                        SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            'Use at least 8 characters, including an uppercase letter and a number.',
                            style: TextStyle(
                              color: brownBeige,
                              fontSize: 12,
                              height: 1.5,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 28),
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: FilledButton.icon(
                      onPressed: _isSaving ? null : _changePassword,
                      icon: _isSaving
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Icon(Icons.lock_reset_rounded),
                      label: Text(
                        _isSaving ? 'Please wait...' : 'Update Password',
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                      style: FilledButton.styleFrom(
                        backgroundColor: darkMaroon,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

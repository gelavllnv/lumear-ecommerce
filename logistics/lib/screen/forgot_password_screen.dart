import 'package:flutter/material.dart';

import 'login_screen.dart';

const Color darkMaroon = Color(0xFF671B1D);
const Color maroon = Color(0xFF873632);
const Color brown = Color(0xFF9E7161);
const Color cream = Color(0xFFFFF8E7);
const Color paleCream = Color(0xFFFFFDF5);
const Color borderColor = Color(0xFFE8D6C3);

// Demo verification code only. No API or email sending yet.
const String hardcodedVerificationCode = '1234';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();

  final _codeController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();

  bool _isLoading = false;
  bool _emailSubmitted = false;
  bool _codeVerified = false;
  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;

  @override
  void dispose() {
    _emailController.dispose();
    _codeController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }

  String? _validateGmail(String? value) {
    final email = value?.trim().toLowerCase() ?? '';

    if (email.isEmpty) {
      return 'Please enter your Gmail address.';
    }

    final gmailPattern = RegExp(r'^[a-z0-9][a-z0-9._%+-]*@gmail\.com$');

    if (!gmailPattern.hasMatch(email)) {
      return 'Please use a valid @gmail.com address.';
    }

    final username = email.split('@').first;

    if (username.startsWith('.') ||
        username.endsWith('.') ||
        username.contains('..')) {
      return 'Please enter a valid Gmail address.';
    }

    return null;
  }

  Future<void> _confirmEmail() async {
    FocusScope.of(context).unfocus();

    if (!_formKey.currentState!.validate()) return;

    setState(() => _isLoading = true);

    // Demo only: no email is actually sent.
    await Future.delayed(const Duration(milliseconds: 400));

    if (!mounted) return;

    setState(() {
      _isLoading = false;
      _emailSubmitted = true;
    });
  }

  void _verifyCode() {
    FocusScope.of(context).unfocus();

    if (_codeController.text.trim() != hardcodedVerificationCode) {
      _showMessage(
        'Incorrect verification code. Please try again.',
        isError: true,
      );
      return;
    }

    setState(() {
      _codeVerified = true;
      _codeController.clear();
    });

    _showMessage('Verification successful!');
  }

  void _resetPassword() {
    FocusScope.of(context).unfocus();

    if (_passwordController.text.length < 6) {
      _showMessage('Password must be at least 6 characters.', isError: true);
      return;
    }

    if (_passwordController.text != _confirmPasswordController.text) {
      _showMessage('Passwords do not match.', isError: true);
      return;
    }

    // Demo only: this does not change a real account password.
    _showMessage('Password reset successful!');

    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.redAccent : darkMaroon,
        behavior: SnackBarBehavior.floating,
      ),
    );
  }

  InputDecoration _inputDecoration({
    String? hintText,
    Widget? prefixIcon,
    Widget? suffixIcon,
  }) {
    return InputDecoration(
      hintText: hintText,
      hintStyle: const TextStyle(color: Color(0xFFAD9489), fontSize: 13),
      prefixIcon: prefixIcon,
      suffixIcon: suffixIcon,
      filled: true,
      fillColor: const Color(0xFFF5EFEB),
      contentPadding: const EdgeInsets.symmetric(horizontal: 15, vertical: 18),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(11),
        borderSide: const BorderSide(color: borderColor),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(11),
        borderSide: const BorderSide(color: borderColor),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(11),
        borderSide: const BorderSide(color: maroon, width: 1.5),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(11),
        borderSide: const BorderSide(color: Colors.redAccent),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(11),
        borderSide: const BorderSide(color: Colors.redAccent, width: 1.5),
      ),
    );
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String hintText,
    required IconData icon,
    TextInputType keyboardType = TextInputType.text,
    bool obscureText = false,
    Widget? suffixIcon,
    String? Function(String?)? validator,
    int? maxLength,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      obscureText: obscureText,
      autocorrect: false,
      enableSuggestions: false,
      maxLength: maxLength,
      decoration: _inputDecoration(
        hintText: hintText,
        prefixIcon: Icon(icon, color: brown, size: 20),
        suffixIcon: suffixIcon,
      ).copyWith(counterText: ''),
      validator: validator,
    );
  }

  Widget _buildCard({required Widget child}) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(26),
      decoration: BoxDecoration(
        color: paleCream,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: borderColor),
        boxShadow: [
          BoxShadow(
            color: brown.withValues(alpha: 0.08),
            blurRadius: 25,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: child,
    );
  }

  Widget _buildPrimaryButton({
    required String label,
    required VoidCallback onPressed,
    IconData? icon,
    bool loading = false,
  }) {
    return SizedBox(
      width: double.infinity,
      height: 54,
      child: ElevatedButton(
        onPressed: loading ? null : onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: darkMaroon,
          foregroundColor: Colors.white,
          disabledBackgroundColor: maroon,
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
        child: loading
            ? const SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: Colors.white,
                ),
              )
            : Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    label,
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 13,
                    ),
                  ),
                  if (icon != null) ...[
                    const SizedBox(width: 10),
                    Icon(icon, size: 18),
                  ],
                ],
              ),
      ),
    );
  }

  Widget _buildHeader({
    required String eyebrow,
    required String title,
    required String description,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          eyebrow,
          style: const TextStyle(
            color: brown,
            fontSize: 11,
            fontWeight: FontWeight.bold,
            letterSpacing: 1.3,
          ),
        ),
        const SizedBox(height: 12),
        Text(
          title,
          style: const TextStyle(
            fontFamily: 'Georgia',
            fontSize: 29,
            fontWeight: FontWeight.bold,
            color: darkMaroon,
          ),
        ),
        const SizedBox(height: 12),
        Text(
          description,
          style: const TextStyle(color: brown, fontSize: 13, height: 1.7),
        ),
      ],
    );
  }

  Widget _buildEmailForm() {
    return _buildCard(
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildHeader(
              eyebrow: 'ACCOUNT RECOVERY',
              title: 'Forgot password?',
              description:
                  'Enter the Gmail address associated with your '
                  'Lumear Logistics account to continue.',
            ),
            const SizedBox(height: 28),
            const Text(
              'Gmail address',
              style: TextStyle(
                color: darkMaroon,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 8),
            _buildTextField(
              controller: _emailController,
              hintText: 'yourname@gmail.com',
              icon: Icons.mail_outline,
              keyboardType: TextInputType.emailAddress,
              validator: _validateGmail,
            ),
            const SizedBox(height: 24),
            _buildPrimaryButton(
              label: 'Confirm Email',
              icon: Icons.arrow_forward,
              onPressed: _confirmEmail,
              loading: _isLoading,
            ),
            const SizedBox(height: 16),
            const Center(
              child: Text(
                'Demo mode: no email will be sent.',
                style: TextStyle(color: brown, fontSize: 11),
              ),
            ),
            const SizedBox(height: 8),
            _backToLoginButton(),
          ],
        ),
      ),
    );
  }

  Widget _buildCodeForm() {
    return _buildCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildHeader(
            eyebrow: 'EMAIL VERIFICATION',
            title: 'Enter your code',
            description:
                'A verification code is required to continue. '
                'For this demo, use the hardcoded 4-digit code.',
          ),
          const SizedBox(height: 22),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(13),
            decoration: BoxDecoration(
              color: cream,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: borderColor),
            ),
            child: Row(
              children: [
                const Icon(Icons.mail_outline, color: brown, size: 20),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    _emailController.text.trim(),
                    style: const TextStyle(
                      color: darkMaroon,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          const Text(
            '4-digit verification code',
            style: TextStyle(
              color: darkMaroon,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _codeController,
            keyboardType: TextInputType.number,
            maxLength: 4,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 26,
              letterSpacing: 12,
              fontWeight: FontWeight.bold,
              color: darkMaroon,
            ),
            decoration: _inputDecoration(
              hintText: '••••',
              prefixIcon: const Icon(
                Icons.lock_outline,
                color: brown,
                size: 20,
              ),
            ).copyWith(counterText: ''),
          ),
          const SizedBox(height: 10),
          _buildPrimaryButton(
            label: 'Verify Code',
            icon: Icons.verified_outlined,
            onPressed: _verifyCode,
          ),
          const SizedBox(height: 10),
          Center(
            child: TextButton(
              onPressed: () {
                setState(() {
                  _emailSubmitted = false;
                  _codeController.clear();
                });
              },
              child: const Text(
                'Change Gmail address',
                style: TextStyle(
                  color: maroon,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ),
          const Center(
            child: Text(
              'Demo verification code: 1234',
              style: TextStyle(color: brown, fontSize: 11),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildResetPasswordForm() {
    return _buildCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildHeader(
            eyebrow: 'CREATE NEW PASSWORD',
            title: 'Reset password',
            description:
                'Verification complete. Enter your new password '
                'below. This is a UI demo and will not update a '
                'real account yet.',
          ),
          const SizedBox(height: 26),
          const Text(
            'New password',
            style: TextStyle(
              color: darkMaroon,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 8),
          _buildTextField(
            controller: _passwordController,
            hintText: 'At least 6 characters',
            icon: Icons.lock_outline,
            obscureText: _obscurePassword,
            suffixIcon: IconButton(
              onPressed: () {
                setState(() => _obscurePassword = !_obscurePassword);
              },
              icon: Icon(
                _obscurePassword
                    ? Icons.visibility_outlined
                    : Icons.visibility_off_outlined,
                color: brown,
                size: 20,
              ),
            ),
          ),
          const SizedBox(height: 18),
          const Text(
            'Confirm new password',
            style: TextStyle(
              color: darkMaroon,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 8),
          _buildTextField(
            controller: _confirmPasswordController,
            hintText: 'Re-enter your password',
            icon: Icons.lock_reset_outlined,
            obscureText: _obscureConfirmPassword,
            suffixIcon: IconButton(
              onPressed: () {
                setState(() {
                  _obscureConfirmPassword = !_obscureConfirmPassword;
                });
              },
              icon: Icon(
                _obscureConfirmPassword
                    ? Icons.visibility_outlined
                    : Icons.visibility_off_outlined,
                color: brown,
                size: 20,
              ),
            ),
          ),
          const SizedBox(height: 24),
          _buildPrimaryButton(
            label: 'Save New Password',
            icon: Icons.check_circle_outline,
            onPressed: _resetPassword,
          ),
          const SizedBox(height: 12),
          _backToLoginButton(),
        ],
      ),
    );
  }

  Widget _backToLoginButton() {
    return Center(
      child: TextButton.icon(
        onPressed: () {
          Navigator.of(context).pushAndRemoveUntil(
            MaterialPageRoute(builder: (_) => const LoginScreen()),
            (route) => false,
          );
        },
        icon: const Icon(Icons.arrow_back, size: 16),
        label: const Text('Back to Sign In'),
        style: TextButton.styleFrom(
          foregroundColor: maroon,
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 12),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final Widget currentContent = !_emailSubmitted
        ? _buildEmailForm()
        : !_codeVerified
        ? _buildCodeForm()
        : _buildResetPasswordForm();

    final String iconText = !_emailSubmitted
        ? 'Forgot Password'
        : !_codeVerified
        ? 'Verify Email'
        : 'New Password';

    return Scaffold(
      backgroundColor: cream,
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            final isDesktop = constraints.maxWidth >= 800;

            return SingleChildScrollView(
              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),
                child: Column(
                  children: [
                    const SizedBox(height: 30),
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Padding(
                        padding: EdgeInsets.symmetric(
                          horizontal: isDesktop ? 48 : 20,
                        ),
                        child: IconButton(
                          tooltip: 'Back',
                          onPressed: () {
                            if (_codeVerified) {
                              setState(() => _codeVerified = false);
                            } else if (_emailSubmitted) {
                              setState(() => _emailSubmitted = false);
                            } else {
                              Navigator.of(context).maybePop();
                            }
                          },
                          icon: const Icon(Icons.arrow_back, color: darkMaroon),
                        ),
                      ),
                    ),
                    const SizedBox(height: 22),
                    Container(
                      width: 100,
                      height: 100,
                      decoration: BoxDecoration(
                        color: paleCream,
                        shape: BoxShape.circle,
                        border: Border.all(color: borderColor),
                      ),
                      child: Icon(
                        !_emailSubmitted
                            ? Icons.lock_reset_rounded
                            : !_codeVerified
                            ? Icons.mark_email_read_outlined
                            : Icons.password_rounded,
                        size: 48,
                        color: darkMaroon,
                      ),
                    ),
                    const SizedBox(height: 14),
                    Text(
                      iconText.toUpperCase(),
                      style: const TextStyle(
                        color: brown,
                        fontSize: 10,
                        letterSpacing: 2,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 24),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 24),
                      child: Center(
                        child: ConstrainedBox(
                          constraints: const BoxConstraints(maxWidth: 520),
                          child: currentContent,
                        ),
                      ),
                    ),
                    const SizedBox(height: 30),
                    const Text(
                      'LUMEAR LOGISTICS',
                      style: TextStyle(
                        fontSize: 10,
                        letterSpacing: 2,
                        fontWeight: FontWeight.w600,
                        color: brown,
                      ),
                    ),
                    const SizedBox(height: 24),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }
}

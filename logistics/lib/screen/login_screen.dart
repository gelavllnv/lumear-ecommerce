import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../logistics_api.dart';
import 'forgot_password_screen.dart';
import 'dashboard_screen.dart';

const Color darkMaroon = Color(0xFF671B1D);
const Color maroon = Color(0xFF873632);
const Color brown = Color(0xFF9E7161);
const Color cream = Color(0xFFFFF8E7);
const Color paleCream = Color(0xFFFFFDF5);
const Color borderColor = Color(0xFFE8D6C3);

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  static const String _googleServerClientId =
      '283451557966-65afnq0eh6gvnace1sladhp8lqsl9d04.apps.googleusercontent.com';

  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();

  bool _obscurePassword = true;
  bool _keepSignedIn = true;
  bool _isLoading = false;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _signIn() async {
    FocusScope.of(context).unfocus();

    if (!_formKey.currentState!.validate()) return;

    setState(() => _isLoading = true);

    try {
      await LogisticsApi.login(_emailController.text.trim(), _passwordController.text);
    } catch (error) {
      if (mounted) {
        setState(() => _isLoading = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
      }
      return;
    }

    if (!mounted) return;

    setState(() => _isLoading = false);

    if (!context.mounted) return;

    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const DashboardScreen()),
    );
  }

  void _forgotPassword() {
    Navigator.of(context)
        .push(MaterialPageRoute(builder: (_) => const ForgotPasswordScreen()));
  }

  @override
  Widget build(BuildContext context) {
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
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Truck image at the TOP
                    _buildFadedTruckImage(),

                    // Login card below the truck
                    Padding(
                      padding: EdgeInsets.symmetric(
                        horizontal: isDesktop ? 32 : 20,
                        vertical: 0,
                      ),
                      child: Center(
                        child: ConstrainedBox(
                          constraints: const BoxConstraints(maxWidth: 600),
                          child: _buildLoginCard(),
                        ),
                      ),
                    ),

                    const SizedBox(height: 20),

                    const Center(
                      child: Text(
                        'LUMEAR LOGISTICS',
                        style: TextStyle(
                          fontSize: 10,
                          letterSpacing: 2,
                          fontWeight: FontWeight.w600,
                          color: brown,
                        ),
                      ),
                    ),

                    const SizedBox(height: 20),
                  ],
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  // --------------------------------------------------
  // Truck image with fading on all four edges
  // --------------------------------------------------

  Widget _buildFadedTruckImage() {
    return SizedBox(
      width: double.infinity,
      height: 280,
      child: ShaderMask(
        blendMode: BlendMode.dstIn,
        shaderCallback: (Rect bounds) {
          return const LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              Colors.transparent,
              Colors.black,
              Colors.black,
              Colors.transparent,
            ],
            stops: [0.0, 0.12, 0.78, 1.0],
          ).createShader(bounds);
        },
        child: ShaderMask(
          blendMode: BlendMode.dstIn,
          shaderCallback: (Rect bounds) {
            return const RadialGradient(
              center: Alignment.center,
              radius: 0.85,
              colors: [Colors.black, Colors.black, Colors.transparent],
              stops: [0.0, 0.58, 1.0],
            ).createShader(bounds);
          },
          child: Image.asset(
            'assets/images/lumear.png',
            width: double.infinity,
            height: 280,
            fit: BoxFit.contain,
            errorBuilder: (context, error, stackTrace) {
              return const Center(
                child: Icon(
                  Icons.local_shipping_outlined,
                  size: 80,
                  color: brown,
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  // --------------------------------------------------
  // Login card
  // --------------------------------------------------

  Widget _buildLoginCard() {
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
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'WELCOME BACK',
              style: TextStyle(
                color: brown,
                fontSize: 11,
                fontWeight: FontWeight.bold,
                letterSpacing: 1.3,
              ),
            ),

            const SizedBox(height: 12),

            const Text(
              'Sign in',
              style: TextStyle(
                fontFamily: 'Georgia',
                fontSize: 36,
                fontWeight: FontWeight.bold,
                color: darkMaroon,
              ),
            ),

            const SizedBox(height: 8),

            const Text(
              'Manage your logistics operations.',
              style: TextStyle(color: brown, fontSize: 13),
            ),

            const SizedBox(height: 28),

            const Text(
              'Email address',
              style: TextStyle(
                color: darkMaroon,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),

            const SizedBox(height: 8),

            TextFormField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              decoration: _inputDecoration(
                hint: 'Enter your email',
                icon: Icons.mail_outline,
              ),
              validator: (value) {
                if (value == null || value.trim().isEmpty) {
                  return 'Please enter your email.';
                }

                if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$')
                    .hasMatch(value.trim())) {
                  return 'Enter a valid email address.';
                }

                return null;
              },
            ),

            const SizedBox(height: 20),

            Row(
              children: [
                const Text(
                  'Password',
                  style: TextStyle(
                    color: darkMaroon,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const Spacer(),
                GestureDetector(
                  onTap: _forgotPassword,
                  child: const Text(
                    'Forgot password?',
                    style: TextStyle(
                      color: maroon,
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),

            const SizedBox(height: 8),

            TextFormField(
              controller: _passwordController,
              obscureText: _obscurePassword,
              textInputAction: TextInputAction.done,
              onFieldSubmitted: (_) => _signIn(),
              decoration: _inputDecoration(
                hint: 'Enter your password',
                icon: Icons.lock_outline,
                suffix: IconButton(
                  tooltip: _obscurePassword ? 'Show password' : 'Hide password',
                  onPressed: () {
                    setState(() {
                      _obscurePassword = !_obscurePassword;
                    });
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
              validator: (value) {
                if (value == null || value.isEmpty) {
                  return 'Please enter your password.';
                }

                return null;
              },
            ),

            const SizedBox(height: 14),

            Row(
              children: [
                SizedBox(
                  width: 22,
                  height: 22,
                  child: Checkbox(
                    value: _keepSignedIn,
                    activeColor: darkMaroon,
                    onChanged: (value) {
                      setState(() {
                        _keepSignedIn = value ?? false;
                      });
                    },
                  ),
                ),
                const SizedBox(width: 8),
                const Text(
                  'Keep me signed in',
                  style: TextStyle(color: brown, fontSize: 12),
                ),
              ],
            ),

            const SizedBox(height: 24),

            SizedBox(
              width: double.infinity,
              height: 54,
              child: ElevatedButton(
                onPressed: _isLoading ? null : _signIn,
                style: ElevatedButton.styleFrom(
                  backgroundColor: darkMaroon,
                  foregroundColor: Colors.white,
                  disabledBackgroundColor: maroon,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: _isLoading
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            'Sign In to Logistics',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 13,
                            ),
                          ),
                          SizedBox(width: 10),
                          Icon(Icons.arrow_forward, size: 18),
                        ],
                      ),
              ),
            ),

            const SizedBox(height: 22),

            // OR CONTINUE WITH divider
            Row(
              children: [
                Expanded(child: Divider(color: borderColor)),
                const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 12),
                  child: Text(
                    'OR CONTINUE WITH',
                    style: TextStyle(
                      color: brown,
                      fontSize: 10,
                      fontWeight: FontWeight.w600,
                      letterSpacing: 1,
                    ),
                  ),
                ),
                Expanded(child: Divider(color: borderColor)),
              ],
            ),

            const SizedBox(height: 18),

            // Google Sign-In button
            SizedBox(
              width: double.infinity,
              height: 52,
              child: OutlinedButton(
                onPressed: _isLoading ? null : _signInWithGoogle,
                style: OutlinedButton.styleFrom(
                  backgroundColor: Colors.white,
                  foregroundColor: darkMaroon,
                  side: const BorderSide(color: borderColor),
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Image.asset(
                      'assets/images/google.png',
                      width: 23,
                      height: 23,
                      fit: BoxFit.contain,
                      errorBuilder: (context, error, stackTrace) {
                        return const Icon(
                          Icons.g_mobiledata,
                          size: 28,
                          color: Color(0xFF4285F4),
                        );
                      },
                    ),
                    const SizedBox(width: 12),
                    const Text(
                      'Continue with Google',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _signInWithGoogle() async {
    FocusScope.of(context).unfocus();
    if (_isLoading) return;

    setState(() => _isLoading = true);
    try {
      final google = GoogleSignIn.instance;
      await google.initialize(serverClientId: _googleServerClientId);
      final account = await google.authenticate();
      final idToken = account.authentication.idToken;

      if (idToken == null || idToken.isEmpty) {
        throw StateError('Google could not verify your account. Please try again.');
      }

      await LogisticsApi.loginWithGoogle(idToken);
      if (!mounted) return;

      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const DashboardScreen()),
      );
    } on GoogleSignInException catch (error) {
      if (!mounted) return;
      if (error.code != GoogleSignInExceptionCode.canceled) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Google sign-in setup needs checking. Confirm the Logistics Android package and SHA-1 are registered with Google, then try again.',
            ),
          ),
        );
      }
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  // --------------------------------------------------
  // Text field styling
  // --------------------------------------------------

  InputDecoration _inputDecoration({
    required String hint,
    required IconData icon,
    Widget? suffix,
  }) {
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: Color(0xFFAD9489), fontSize: 13),
      prefixIcon: Icon(icon, color: brown, size: 20),
      suffixIcon: suffix,
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
}

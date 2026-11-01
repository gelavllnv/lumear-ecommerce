import 'package:flutter/material.dart';

import 'dashboard_screen.dart';
import 'signup_screen.dart';
import 'forgot_password_screen.dart';
import 'admin/admin_dashboard_screen.dart';
import 'truck/truck_dashboard_screen.dart';
import 'van/van_rider_dashboard_screen.dart';

import 'package:flutter_application_1/models/rider_profile.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen>
    with SingleTickerProviderStateMixin {
  // ============================================================
  // CONTROLLERS
  // ============================================================

  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();

  // ============================================================
  // STATE
  // ============================================================

  bool _obscurePassword = true;
  bool _isLoggingIn = false;
  bool _rememberMe = false;
  bool _loginSuccess = false;

  // ============================================================
  // COLORS
  // ============================================================

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color cream = Color(0xFFE0CFC2);
  static const Color successColor = Color(0xFFB88A5A);
  static const Color smokeGray = Color.fromARGB(255, 255, 254, 254);

  // ============================================================
  // SMOKE ANIMATION
  // ============================================================

  late AnimationController _smokeController;

  @override
  void initState() {
    super.initState();

    _smokeController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2000),
    )..repeat();
  }

  // ============================================================
  // DISPOSE
  // ============================================================

  @override
  void dispose() {
    _smokeController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  // ============================================================
  // NORMALIZE VEHICLE TYPE
  // ============================================================

  String? _canonicalVehicleType(String value) {
    final String normalized = value.toLowerCase().replaceAll(
      RegExp(r'[^a-z0-9]'),
      '',
    );

    return switch (normalized) {
      'motorcycle' || 'motorbike' => 'Motorcycle',
      'tricycle' => 'Tricycle',
      'truck' => 'Truck',
      'van' => 'Van',
      _ => null,
    };
  }

  // ============================================================
  // SHOW MESSAGE
  // ============================================================

  void _showLoginMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          backgroundColor: darkMaroon,
          behavior: SnackBarBehavior.floating,
        ),
      );
  }

  // ============================================================
  // LOGIN
  // ============================================================

  Future<void> _login() async {
    FocusScope.of(context).unfocus();

    if (_isLoggingIn || _loginSuccess) return;

    final String email = _emailController.text.trim().toLowerCase();
    final String password = _passwordController.text;

    if (email.isEmpty || password.isEmpty) {
      _showLoginMessage('Please enter your email and password.');
      return;
    }

    // ============================================================
    // ADMIN LOGIN (DEMO ONLY)
    // ============================================================

    if (email == 'lumear@gmail.com' && password == 'adminOne') {
      setState(() => _isLoggingIn = true);

      await Future.delayed(const Duration(milliseconds: 500));
      if (!mounted) return;

      setState(() {
        _isLoggingIn = false;
        _loginSuccess = true;
      });

      await Future.delayed(const Duration(milliseconds: 500));
      if (!mounted) return;

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => const AdminDashboardScreen()),
      );
      return;
    }

    // ============================================================
    // RIDER LOGIN (LOCAL ONLY)
    // The server API was removed. The rider is matched against the
    // profile saved on this device during sign up (RiderProfile).
    // ============================================================

    setState(() {
      _isLoggingIn = true;
      _loginSuccess = false;
    });

    await Future.delayed(const Duration(milliseconds: 500));
    if (!mounted) return;

    final String savedEmail = RiderProfile.email
        .toString()
        .trim()
        .toLowerCase();

    if (savedEmail.isEmpty || savedEmail != email) {
      setState(() => _isLoggingIn = false);
      _showLoginMessage(
        'No rider account found on this device. Please sign up first.',
      );
      return;
    }

    final String? vehicleType = _canonicalVehicleType(
      RiderProfile.vehicleType.toString(),
    );

    final String jobType = RiderProfile.jobType.toString().trim().toLowerCase();

    if (vehicleType == null) {
      setState(() => _isLoggingIn = false);
      _showLoginMessage('Unsupported vehicle type for this account.');
      return;
    }

    setState(() {
      _isLoggingIn = false;
      _loginSuccess = true;
    });

    await Future.delayed(const Duration(milliseconds: 650));
    if (!mounted) return;

    // ============================================================
    // GO TO DASHBOARD BASED ON VEHICLE TYPE AND JOB
    // ============================================================

    final Widget destination = switch (vehicleType) {
      'Motorcycle' => const DashboardScreen(),
      'Tricycle' => const DashboardScreen(),
      'Van' when jobType == 'delivery' => const DashboardScreen(),
      'Van' when jobType == 'seller pickup' || jobType == 'pickup' =>
        const VanRiderDashboardScreen(),
      'Truck' when jobType == 'delivery' => const DashboardScreen(),
      'Truck' => const TruckDashboardScreen(),
      _ => const DashboardScreen(),
    };

    Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => destination),
    );
  }

  // ============================================================
  // ANIMATED LOGIN BUTTON
  // ============================================================

  Widget _buildLoginButton() {
    final bool compact = _isLoggingIn || _loginSuccess;

    return Center(
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 350),
        curve: Curves.easeInOut,
        width: compact ? 55 : MediaQuery.of(context).size.width,
        height: 55,
        decoration: BoxDecoration(
          color: _loginSuccess ? successColor : darkMaroon,
          borderRadius: BorderRadius.circular(compact ? 100 : 15),
          boxShadow: [
            BoxShadow(
              color: (_loginSuccess ? successColor : darkMaroon).withValues(
                alpha: 0.25,
              ),
              blurRadius: compact ? 16 : 7,
              spreadRadius: compact ? 1 : 0,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: _isLoggingIn || _loginSuccess ? null : _login,
            borderRadius: BorderRadius.circular(100),
            child: Center(
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 250),
                transitionBuilder: (Widget child, Animation<double> animation) {
                  return ScaleTransition(
                    scale: animation,
                    child: FadeTransition(opacity: animation, child: child),
                  );
                },
                child: _isLoggingIn
                    ? const SizedBox(
                        key: ValueKey('loading'),
                        width: 27,
                        height: 27,
                        child: CircularProgressIndicator(
                          strokeWidth: 3,
                          color: Colors.white,
                        ),
                      )
                    : _loginSuccess
                    ? const Icon(
                        Icons.check_rounded,
                        key: ValueKey('success'),
                        color: Colors.white,
                        size: 34,
                      )
                    : const Text(
                        'Log In',
                        key: ValueKey('normal'),
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  // ============================================================
  // CONTINUE WITH GOOGLE BUTTON
  // ============================================================

  Widget _buildGoogleButton() {
    return SizedBox(
      width: double.infinity,
      height: 55,
      child: OutlinedButton(
        onPressed: _isLoggingIn || _loginSuccess
            ? null
            : () {
                FocusScope.of(context).unfocus();
                _showLoginMessage(
                  'Google Sign-In is not connected yet. Firebase setup is required.',
                );
              },
        style: OutlinedButton.styleFrom(
          backgroundColor: Colors.white.withValues(alpha: 0.90),
          foregroundColor: const Color(0xFF333333),
          side: BorderSide(
            color: brownBeige.withValues(alpha: 0.35),
            width: 1.2,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(15),
          ),
          elevation: 0,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // Multicolored Google-style G mark (no extra image asset needed).
            Image.asset(
              'assets/images/google.png',
              width: 25,
              height: 25,
              fit: BoxFit.contain,
            ),
            const SizedBox(width: 12),
            const Text(
              'Continue with Google',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w600,
                color: Color(0xFF333333),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // SMOKE ANIMATION HELPERS
  // ============================================================

  double _smokeProgress(double value, double delay) {
    double progress = value - delay;

    if (progress < 0) {
      progress += 1;
    }

    return progress;
  }

  Widget _buildSmoke({
    required double progress,
    required double startX,
    required double startY,
    required double size,
  }) {
    final double x = startX - (progress * 45);
    final double y = startY - (progress * 15);
    final double currentSize = size + (progress * 12);
    final double opacity = (1 - progress).clamp(0.0, 1.0);

    return Positioned(
      left: x,
      top: y,
      child: Opacity(
        opacity: opacity,
        child: Container(
          width: currentSize,
          height: currentSize,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: smokeGray.withValues(alpha: 0.75),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.20),
                blurRadius: 5,
                spreadRadius: 1,
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ============================================================
  // BUILD LOGIN SCREEN
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      body: Stack(
        children: [
          const Positioned.fill(
            child: IgnorePointer(
              child: CustomPaint(painter: _LoginDecorPainter()),
            ),
          ),
          SafeArea(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 40),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 5),

                  // LUMEAR BRANDING
                  Center(
                    child: Column(
                      children: [
                        const Text(
                          'LUMEAR',
                          style: TextStyle(
                            color: darkMaroon,
                            fontSize: 25,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 6,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Container(
                              width: 25,
                              height: 1,
                              color: brownBeige.withValues(alpha: 0.65),
                            ),
                            const Padding(
                              padding: EdgeInsets.symmetric(horizontal: 8),
                              child: Text(
                                'RIDER',
                                style: TextStyle(
                                  color: brownBeige,
                                  fontSize: 9,
                                  fontWeight: FontWeight.w700,
                                  letterSpacing: 3,
                                ),
                              ),
                            ),
                            Container(
                              width: 25,
                              height: 1,
                              color: brownBeige.withValues(alpha: 0.65),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 20),

                  // LOGO AND SMOKE
                  Transform.translate(
                    offset: const Offset(-25, 0),
                    child: Center(
                      child: SizedBox(
                        width: 260,
                        height: 200,
                        child: Stack(
                          clipBehavior: Clip.none,
                          children: [
                            Positioned(
                              left: 5,
                              bottom: 38,
                              child: SizedBox(
                                width: 100,
                                height: 70,
                                child: AnimatedBuilder(
                                  animation: _smokeController,
                                  builder: (context, child) {
                                    return Stack(
                                      clipBehavior: Clip.none,
                                      children: [
                                        _buildSmoke(
                                          progress: _smokeProgress(
                                            _smokeController.value,
                                            0.00,
                                          ),
                                          startX: 70,
                                          startY: 35,
                                          size: 9,
                                        ),
                                        _buildSmoke(
                                          progress: _smokeProgress(
                                            _smokeController.value,
                                            0.25,
                                          ),
                                          startX: 70,
                                          startY: 35,
                                          size: 12,
                                        ),
                                        _buildSmoke(
                                          progress: _smokeProgress(
                                            _smokeController.value,
                                            0.50,
                                          ),
                                          startX: 70,
                                          startY: 35,
                                          size: 8,
                                        ),
                                        _buildSmoke(
                                          progress: _smokeProgress(
                                            _smokeController.value,
                                            0.75,
                                          ),
                                          startX: 70,
                                          startY: 35,
                                          size: 11,
                                        ),
                                      ],
                                    );
                                  },
                                ),
                              ),
                            ),
                            Center(
                              child: Image.asset(
                                'assets/images/logo.png',
                                width: 200,
                                height: 200,
                                fit: BoxFit.contain,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),

                  const SizedBox(height: 40),

                  // TITLE
                  const Center(
                    child: Text(
                      'Welcome Back!',
                      style: TextStyle(
                        fontSize: 30,
                        fontWeight: FontWeight.bold,
                        color: darkMaroon,
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Center(
                    child: Text(
                      'Login to continue your deliveries',
                      style: TextStyle(fontSize: 15, color: brownBeige),
                    ),
                  ),

                  const SizedBox(height: 20),

                  // EMAIL
                  TextField(
                    controller: _emailController,
                    enabled: !_isLoggingIn && !_loginSuccess,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    autocorrect: false,
                    enableSuggestions: false,
                    decoration: InputDecoration(
                      hintText: 'Enter your email',
                      prefixIcon: const Icon(
                        Icons.email_outlined,
                        color: maroon,
                      ),
                      filled: true,
                      fillColor: Colors.white.withValues(alpha: 0.65),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(15),
                        borderSide: BorderSide.none,
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(15),
                        borderSide: BorderSide.none,
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(15),
                        borderSide: const BorderSide(color: maroon, width: 2),
                      ),
                    ),
                  ),

                  const SizedBox(height: 20),

                  // PASSWORD
                  TextField(
                    controller: _passwordController,
                    enabled: !_isLoggingIn && !_loginSuccess,
                    obscureText: _obscurePassword,
                    textInputAction: TextInputAction.done,
                    onSubmitted: (_) {
                      if (!_isLoggingIn && !_loginSuccess) {
                        _login();
                      }
                    },
                    decoration: InputDecoration(
                      hintText: 'Enter your password',
                      prefixIcon: const Icon(Icons.lock_outline, color: maroon),
                      suffixIcon: IconButton(
                        icon: Icon(
                          _obscurePassword
                              ? Icons.visibility_off_outlined
                              : Icons.visibility_outlined,
                          color: maroon,
                        ),
                        onPressed: _isLoggingIn || _loginSuccess
                            ? null
                            : () {
                                setState(() {
                                  _obscurePassword = !_obscurePassword;
                                });
                              },
                      ),
                      filled: true,
                      fillColor: Colors.white.withValues(alpha: 0.65),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(15),
                        borderSide: BorderSide.none,
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(15),
                        borderSide: BorderSide.none,
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(15),
                        borderSide: const BorderSide(color: maroon, width: 2),
                      ),
                    ),
                  ),

                  const SizedBox(height: 10),

                  // REMEMBER ME AND FORGOT PASSWORD
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          SizedBox(
                            width: 24,
                            height: 24,
                            child: Checkbox(
                              value: _rememberMe,
                              activeColor: darkMaroon,
                              checkColor: Colors.white,
                              side: const BorderSide(color: maroon, width: 1.5),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(4),
                              ),
                              onChanged: _isLoggingIn || _loginSuccess
                                  ? null
                                  : (value) {
                                      setState(() {
                                        _rememberMe = value ?? false;
                                      });
                                    },
                            ),
                          ),
                          const SizedBox(width: 7),
                          const Text(
                            'Remember Me',
                            style: TextStyle(
                              color: brownBeige,
                              fontSize: 13,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                      ),
                      TextButton(
                        onPressed: _isLoggingIn || _loginSuccess
                            ? null
                            : () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (context) =>
                                        const ForgotPasswordScreen(),
                                  ),
                                );
                              },
                        child: const Text(
                          'Forgot Password?',
                          style: TextStyle(
                            color: maroon,
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // LOGIN BUTTON
                  _buildLoginButton(),

                  const SizedBox(height: 20),

                  // OR DIVIDER
                  Row(
                    children: [
                      Expanded(
                        child: Divider(
                          color: brownBeige.withValues(alpha: 0.45),
                          thickness: 1,
                        ),
                      ),
                      const Padding(
                        padding: EdgeInsets.symmetric(horizontal: 14),
                        child: Text(
                          'OR',
                          style: TextStyle(
                            color: brownBeige,
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            letterSpacing: 1.2,
                          ),
                        ),
                      ),
                      Expanded(
                        child: Divider(
                          color: brownBeige.withValues(alpha: 0.45),
                          thickness: 1,
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // CONTINUE WITH GOOGLE
                  _buildGoogleButton(),

                  const SizedBox(height: 25),

                  // SIGN UP
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Text(
                        "Don't have an account?",
                        style: TextStyle(color: brownBeige),
                      ),
                      TextButton(
                        onPressed: _isLoggingIn || _loginSuccess
                            ? null
                            : () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (context) => const SignUpScreen(),
                                  ),
                                );
                              },
                        child: const Text(
                          'Sign Up',
                          style: TextStyle(
                            color: darkMaroon,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ================================================================
// DECORATIVE LOGIN BACKGROUND PAINTER
// ================================================================

class _LoginDecorPainter extends CustomPainter {
  const _LoginDecorPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final backWavePaint = Paint()
      ..color = const Color(0xFF9E7161).withValues(alpha: 0.16)
      ..style = PaintingStyle.fill;

    final frontWavePaint = Paint()
      ..color = const Color(0xFF873632).withValues(alpha: 0.10)
      ..style = PaintingStyle.fill;

    final sideLinePaint = Paint()
      ..color = const Color(0xFF873632).withValues(alpha: 0.10)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.2
      ..strokeCap = StrokeCap.round;

    final backWave = Path()
      ..moveTo(0, size.height * 0.88)
      ..cubicTo(
        size.width * 0.22,
        size.height * 0.84,
        size.width * 0.44,
        size.height * 0.94,
        size.width * 0.64,
        size.height * 0.90,
      )
      ..cubicTo(
        size.width * 0.80,
        size.height * 0.87,
        size.width * 0.91,
        size.height * 0.84,
        size.width,
        size.height * 0.86,
      )
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();

    canvas.drawPath(backWave, backWavePaint);

    final frontWave = Path()
      ..moveTo(0, size.height * 0.92)
      ..cubicTo(
        size.width * 0.20,
        size.height * 0.89,
        size.width * 0.40,
        size.height * 0.97,
        size.width * 0.58,
        size.height * 0.94,
      )
      ..cubicTo(
        size.width * 0.76,
        size.height * 0.91,
        size.width * 0.88,
        size.height * 0.89,
        size.width,
        size.height * 0.91,
      )
      ..lineTo(size.width, size.height)
      ..lineTo(0, size.height)
      ..close();

    canvas.drawPath(frontWave, frontWavePaint);

    final leftTopCurve = Path()
      ..moveTo(0, size.height * 0.18)
      ..cubicTo(
        size.width * 0.07,
        size.height * 0.16,
        size.width * 0.11,
        size.height * 0.20,
        size.width * 0.16,
        size.height * 0.18,
      );

    canvas.drawPath(leftTopCurve, sideLinePaint);

    final leftMidCurve = Path()
      ..moveTo(0, size.height * 0.55)
      ..cubicTo(
        size.width * 0.05,
        size.height * 0.53,
        size.width * 0.10,
        size.height * 0.57,
        size.width * 0.14,
        size.height * 0.55,
      );

    canvas.drawPath(leftMidCurve, sideLinePaint);

    final rightTopCurve = Path()
      ..moveTo(size.width, size.height * 0.27)
      ..cubicTo(
        size.width * 0.93,
        size.height * 0.25,
        size.width * 0.89,
        size.height * 0.29,
        size.width * 0.84,
        size.height * 0.27,
      );

    canvas.drawPath(rightTopCurve, sideLinePaint);

    final rightMidCurve = Path()
      ..moveTo(size.width, size.height * 0.68)
      ..cubicTo(
        size.width * 0.95,
        size.height * 0.66,
        size.width * 0.90,
        size.height * 0.70,
        size.width * 0.86,
        size.height * 0.68,
      );

    canvas.drawPath(rightMidCurve, sideLinePaint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) {
    return false;
  }
}

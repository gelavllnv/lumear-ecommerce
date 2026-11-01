import 'dart:async';

import 'package:flutter/material.dart';
import 'package:audioplayers/audioplayers.dart';

import 'login_screen.dart';

class FlashScreen extends StatefulWidget {
  const FlashScreen({super.key});

  @override
  State<FlashScreen> createState() => _FlashScreenState();
}

class _FlashScreenState extends State<FlashScreen>
    with SingleTickerProviderStateMixin {
  // ============================================================
  // COLORS
  // ============================================================

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color cream = Color(0xFFE0CFC2);

  // Smoke color
  static const Color smokeGray = Color.fromARGB(255, 255, 253, 253);

  // ============================================================
  // ANIMATION
  // ============================================================

  late AnimationController _riderController;
  late Animation<double> _riderAnimation;

  double _opacity = 1.0;

  // ============================================================
  // AUDIO
  // ============================================================

  final AudioPlayer _audioPlayer = AudioPlayer();

  // ============================================================
  // INIT
  // ============================================================

  @override
  void initState() {
    super.initState();

    // Play opening sound
    _playOpeningSound();

    // Rider movement controller
    _riderController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1800),
    );

    // Rider drives in from off-screen on the left and comes to rest
    // visibly on the right side of center (instead of sliding all the
    // way through and disappearing off-screen).
    _riderAnimation = Tween<double>(
      begin: -300,
      end: 90,
    ).animate(CurvedAnimation(parent: _riderController, curve: Curves.easeOut));

    // Start rider animation
    _riderController.forward();

    // After rider animation
    Timer(const Duration(milliseconds: 1900), () {
      if (!mounted) return;

      // Fade entire splash screen
      setState(() {
        _opacity = 0.0;
      });

      // Go to Login Screen
      Timer(const Duration(milliseconds: 800), () async {
        if (!mounted) return;

        // Stop sound before leaving splash
        await _audioPlayer.stop();

        if (!mounted) return;

        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (context) => const LoginScreen()),
        );
      });
    });
  }

  // ============================================================
  // PLAY OPENING SOUND
  // ============================================================

  Future<void> _playOpeningSound() async {
    try {
      // Release the player as soon as playback finishes (no looping,
      // no leftover native resource hanging around).
      await _audioPlayer.setReleaseMode(ReleaseMode.release);

      // On iOS, audio using the default category respects the silent
      // (mute) switch, which is the single most common reason a splash
      // sound appears to "not play at all" on a physical device. The
      // `playback` category makes it play even with the switch on
      // silent. On Android this also nudges audio focus so the sound
      // isn't ducked by another app.
      await _audioPlayer.setAudioContext(
        AudioContext(
          iOS: AudioContextIOS(
            category: AVAudioSessionCategory.playback,
            options: const {AVAudioSessionOptions.mixWithOthers},
          ),
          android: const AudioContextAndroid(
            isSpeakerphoneOn: true,
            stayAwake: false,
            contentType: AndroidContentType.music,
            usageType: AndroidUsageType.media,
            audioFocus: AndroidAudioFocus.gain,
          ),
        ),
      );

      await _audioPlayer.setVolume(1.0);

      // Pre-load the source first, then resume — more reliable than a
      // single play() call on a cold app start on some Android devices.
      await _audioPlayer.setSourceAsset('sounds/opening.mp3');
      await _audioPlayer.resume();
    } catch (error) {
      // If you still hear nothing after this, the almost-always cause
      // is the asset not being registered: confirm the file really
      // exists at assets/sounds/opening.mp3 AND is listed under
      // `flutter: assets:` in pubspec.yaml (either the exact file path
      // or the assets/sounds/ folder), then run a full restart (not
      // hot reload) so the asset bundle gets rebuilt.
      debugPrint('Opening sound error: $error');
    }
  }

  // ============================================================
  // DISPOSE
  // ============================================================

  @override
  void dispose() {
    _riderController.dispose();
    _audioPlayer.dispose();

    super.dispose();
  }

  // ============================================================
  // SMOKE PROGRESS
  // ============================================================

  double _smokeProgress(double value, double delay) {
    double progress = value - delay;

    if (progress < 0) {
      progress += 1;
    }

    return progress;
  }

  // ============================================================
  // CREATE SMOKE
  // ============================================================

  Widget _buildSmoke({
    required double progress,
    required double startX,
    required double startY,
    required double size,
  }) {
    // Smoke moves backward / left
    final double x = startX - (progress * 42);

    // Smoke rises
    final double y = startY - (progress * 15);

    // Smoke gets bigger
    final double currentSize = size + (progress * 12);

    // Smoke fades away
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
            color: smokeGray.withValues(alpha: 0.70),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.15),
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
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,

      body: AnimatedOpacity(
        opacity: _opacity,
        duration: const Duration(milliseconds: 800),
        curve: Curves.easeInOut,

        child: SizedBox.expand(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              // ==================================================
              // MOVING RIDER + SMOKE
              // ==================================================

              AnimatedBuilder(
                animation: _riderController,

                builder: (context, child) {
                  return Transform.translate(
                    offset: Offset(_riderAnimation.value, 0),

                    child: SizedBox(
                      width: 270,
                      height: 220,

                      child: Stack(
                        clipBehavior: Clip.none,

                        children: [
                          // =======================================
                          // SMOKE
                          // =======================================

                          Positioned(
                            left: 5,
                            bottom: 45,

                            child: SizedBox(
                              width: 100,
                              height: 70,

                              child: Stack(
                                clipBehavior: Clip.none,

                                children: [
                                  _buildSmoke(
                                    progress: _smokeProgress(
                                      _riderController.value,
                                      0.00,
                                    ),
                                    startX: 70,
                                    startY: 35,
                                    size: 8,
                                  ),

                                  _buildSmoke(
                                    progress: _smokeProgress(
                                      _riderController.value,
                                      0.20,
                                    ),
                                    startX: 70,
                                    startY: 35,
                                    size: 11,
                                  ),

                                  _buildSmoke(
                                    progress: _smokeProgress(
                                      _riderController.value,
                                      0.40,
                                    ),
                                    startX: 70,
                                    startY: 35,
                                    size: 9,
                                  ),

                                  _buildSmoke(
                                    progress: _smokeProgress(
                                      _riderController.value,
                                      0.60,
                                    ),
                                    startX: 70,
                                    startY: 35,
                                    size: 12,
                                  ),

                                  _buildSmoke(
                                    progress: _smokeProgress(
                                      _riderController.value,
                                      0.80,
                                    ),
                                    startX: 70,
                                    startY: 35,
                                    size: 8,
                                  ),
                                ],
                              ),
                            ),
                          ),

                          // =======================================
                          // RIDER / LOGO
                          // =======================================
                          Center(
                            child: Image.asset(
                              'assets/images/logo.png',

                              width: 220,
                              height: 220,

                              fit: BoxFit.contain,
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),

              const SizedBox(height: 20),

              // ==================================================
              // APP NAME
              // ==================================================
              const Text(
                'My Lumear App',

                style: TextStyle(
                  fontSize: 30,

                  fontWeight: FontWeight.bold,

                  color: darkMaroon,
                ),
              ),

              const SizedBox(height: 10),

              // ==================================================
              // TAGLINE
              // ==================================================
              const Text(
                'Fast. Safe. Reliable.',

                style: TextStyle(
                  fontSize: 16,

                  color: maroon,

                  letterSpacing: 1.2,
                ),
              ),

              const SizedBox(height: 30),

              // ==================================================
              // LOADING
              // ==================================================
              const SizedBox(
                width: 25,
                height: 25,

                child: CircularProgressIndicator(
                  strokeWidth: 3,

                  color: darkMaroon,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

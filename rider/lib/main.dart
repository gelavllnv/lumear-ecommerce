import 'package:flutter/material.dart';

import 'app_settings_controller.dart';
import 'screens/flash_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  await appSettings.loadSettings();

  runApp(const Lumear());
}

class Lumear extends StatefulWidget {
  const Lumear({super.key});

  @override
  State<Lumear> createState() => _LumearState();
}

class _LumearState extends State<Lumear> {
  @override
  void initState() {
    super.initState();

    appSettings.addListener(_refreshApp);
  }

  @override
  void dispose() {
    appSettings.removeListener(_refreshApp);

    super.dispose();
  }

  void _refreshApp() {
    if (!mounted) return;

    setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Lumear',

      debugShowCheckedModeBanner: false,

      // ============================================================
      // LIGHT THEME ONLY
      // ============================================================
      theme: ThemeData(
        useMaterial3: true,

        brightness: Brightness.light,

        scaffoldBackgroundColor: const Color(0xFFE0CFC2),

        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF62191C),
          brightness: Brightness.light,
        ),

        appBarTheme: const AppBarTheme(
          backgroundColor: Color(0xFFE0CFC2),
          foregroundColor: Color(0xFF62191C),
          elevation: 0,
          surfaceTintColor: Colors.transparent,
        ),

        cardColor: Colors.white,

        switchTheme: SwitchThemeData(
          thumbColor: WidgetStateProperty.resolveWith<Color?>((states) {
            if (states.contains(WidgetState.selected)) {
              return const Color(0xFF62191C);
            }

            return null;
          }),
        ),
      ),

      home: const FlashScreen(),
    );
  }
}

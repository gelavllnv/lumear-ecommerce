import 'package:flutter/material.dart';

import 'screen/login_screen.dart';

void main() {
  runApp(const LumearApp());
}

class LumearApp extends StatelessWidget {
  const LumearApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Lumear Logistics',
      theme: ThemeData(
        useMaterial3: true,
        scaffoldBackgroundColor: const Color(0xFFFFF8E7),
      ),
      home: const LoginScreen(),
    );
  }
}

import 'package:flutter/material.dart';

class MapScreen extends StatelessWidget {
  const MapScreen({super.key});

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,

      appBar: AppBar(
        backgroundColor: cream,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          onPressed: () {
            Navigator.pop(context);
          },
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: darkMaroon),
        ),
        title: const Text(
          'Map',
          style: TextStyle(color: darkMaroon, fontWeight: FontWeight.bold),
        ),
      ),

      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.7),
              borderRadius: BorderRadius.circular(22),
              border: Border.all(color: lightBeige),
            ),
            child: const Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                CircleAvatar(
                  radius: 34,
                  backgroundColor: lightBeige,
                  child: Icon(Icons.map_outlined, size: 34, color: darkMaroon),
                ),

                SizedBox(height: 18),

                Text(
                  'Map Temporarily Disabled',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: darkMaroon,
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                  ),
                ),

                SizedBox(height: 8),

                Text(
                  'The map feature will be added again after the main delivery flow is stable.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: brownBeige,
                    fontSize: 13,
                    height: 1.5,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:flutter_application_2/main.dart';

void main() {
  testWidgets('login screen renders and opens the dashboard', (tester) async {
    await tester.pumpWidget(const LumearApp());

    expect(find.text('Sign in'), findsOneWidget);
    expect(find.text('Sign In to Logistics'), findsOneWidget);

    await tester.enterText(
      find.byType(TextFormField).at(0),
      'admin@lumear.com',
    );
    await tester.enterText(find.byType(TextFormField).at(1), 'password123');

    await tester.ensureVisible(find.text('Sign In to Logistics'));
    await tester.tap(find.text('Sign In to Logistics'));
    await tester.pumpAndSettle();

    expect(find.text('Welcome back, Admin!'), findsOneWidget);
    expect(find.text('Overview'), findsOneWidget);
  });
}

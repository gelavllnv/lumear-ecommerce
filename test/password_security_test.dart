import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:flutter_application_1/screen/password_security_screen.dart';
import 'package:flutter_application_1/services/api_service.dart';

void main() {
  setUp(() => ApiService.baseUrl = 'https://example.invalid/api');
  tearDown(() {
    ApiService.baseUrl = '';
    ApiService.setToken(null);
  });

  for (final change in [false, true]) {
    testWidgets(
      '${change ? 'Change' : 'Reset'} waits for email verification and server success',
      (tester) async {
        var requests = 0;
        await http.runWithClient(
          () async {
            await tester.pumpWidget(
              MaterialApp(
                home: PasswordSecurityScreen(
                  connected: true,
                  changePassword: change,
                  initialEmail: 'buyer@example.com',
                ),
              ),
            );
            if (change) {
              await tester.enterText(find.byType(TextFormField), 'OldPass123');
            }
            await tester.tap(find.text('Send verification code'));
            await tester.pumpAndSettle();
            expect(requests, 1);
            final fields = find.byType(TextFormField);
            await tester.enterText(fields.at(0), '000000');
            await tester.enterText(fields.at(1), 'NewPass123');
            await tester.enterText(fields.at(2), 'different');
            await tester.ensureVisible(find.text('Confirm password'));
            await tester.tap(find.text('Confirm password'));
            await tester.pumpAndSettle();
            expect(requests, 1);
            expect(find.text('Passwords do not match.'), findsOneWidget);
            await tester.enterText(fields.at(2), 'NewPass123');
            await tester.ensureVisible(find.text('Confirm password'));
            await tester.tap(find.text('Confirm password'));
            await tester.pumpAndSettle();
            expect(find.text('Invalid or expired code.'), findsOneWidget);
            expect(find.text('Sign in'), findsNothing);
            await tester.enterText(fields.at(0), '654321');
            await tester.ensureVisible(find.text('Confirm password'));
            await tester.tap(find.text('Confirm password'));
            await tester.pumpAndSettle();
            expect(requests, 3);
            expect(find.text('Sign in'), findsOneWidget);
            expect(tester.takeException(), isNull);
          },
          () => MockClient((request) async {
            requests++;
            final body = jsonDecode(request.body);
            final purpose = change ? 'change' : 'reset';
            if (request.url.path.endsWith('/request')) {
              expect(request.url.path, '/api/auth/password/$purpose/request');
              if (change) {
                expect(body['current_password'], 'OldPass123');
                expect(body.containsKey('email'), isFalse);
              } else {
                expect(body['email'], 'buyer@example.com');
              }
              expect(body.containsKey('new_password'), isFalse);
              return http.Response('{"challenge_id":"opaque-proof"}', 200);
            }
            expect(request.url.path, '/api/auth/password/$purpose/confirm');
            expect(body['challenge_id'], 'opaque-proof');
            expect(body['new_password'], 'NewPass123');
            return body['code'] == '654321'
                ? http.Response('{"updated":true}', 200)
                : http.Response('{"message":"Invalid or expired code."}', 422);
          }),
        );
      },
    );
  }

  testWidgets('Preview never sends email or changes credentials', (
    tester,
  ) async {
    await http.runWithClient(
      () async {
        await tester.pumpWidget(
          const MaterialApp(home: PasswordSecurityScreen(connected: false)),
        );
        expect(find.textContaining('No email is sent'), findsOneWidget);
        expect(find.text('Send verification code'), findsNothing);
      },
      () => MockClient(
        (_) async => throw StateError('Unexpected network request'),
      ),
    );
  });
}

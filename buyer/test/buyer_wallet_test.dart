import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:flutter_application_1/screen/buyer_wallet_screen.dart';
import 'package:flutter_application_1/screen/google_otp_screen.dart';
import 'package:flutter_application_1/services/api_service.dart';
import 'package:flutter_application_1/services/buyer_data.dart';

void main() {
  setUp(() => ApiService.baseUrl = 'https://example.invalid/api');
  tearDown(() {
    ApiService.baseUrl = '';
    BuyerData.instance.reset();
  });

  testWidgets('Wallet separates credited balance from pending returns', (
    tester,
  ) async {
    BuyerData.instance.connected = true;
    await http.runWithClient(
      () async {
        await tester.pumpWidget(const MaterialApp(home: BuyerWalletScreen()));
        await tester.pumpAndSettle();
        expect(find.text('₱0.00'), findsOneWidget);
        expect(find.text('No refunds credited yet.'), findsOneWidget);
        expect(find.text('ORDER-1 · approved'), findsOneWidget);
        expect(find.text('₱100.00'), findsOneWidget);
      },
      () => MockClient(
        (r) async => http.Response(
          jsonEncode(
            r.url.path.endsWith('/wallet')
                ? {
                    'balance_minor': 0,
                    'transactions': [],
                    'returns': [
                      {
                        'product': 'Damaged item',
                        'reference': 'ORDER-1',
                        'status': 'approved',
                        'amount_minor': 10000,
                      },
                    ],
                  }
                : [],
          ),
          200,
          headers: {'content-type': 'application/json; charset=utf-8'},
        ),
      ),
    );
  });

  testWidgets('Google OTP errors preserve gate until verified', (tester) async {
    var attempts = 0;
    await http.runWithClient(
      () async {
        await tester.pumpWidget(
          MaterialApp(
            home: Builder(
              builder: (context) => TextButton(
                onPressed: () async {
                  final proof = await Navigator.push<String>(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const GoogleOtpScreen(
                        challengeId: 'challenge',
                        idToken: 'signed-google-token',
                      ),
                    ),
                  );
                  if (context.mounted && proof == 'proof') {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) =>
                            const Scaffold(body: Text('Verified destination')),
                      ),
                    );
                  }
                },
                child: const Text('Start'),
              ),
            ),
          ),
        );
        await tester.tap(find.text('Start'));
        await tester.pumpAndSettle();
        await tester.enterText(find.byType(TextField), '111111');
        await tester.tap(find.text('Verify and continue'));
        await tester.pumpAndSettle();
        expect(find.text('Incorrect code.'), findsOneWidget);
        expect(find.text('Verified destination'), findsNothing);
        await tester.enterText(find.byType(TextField), '123456');
        await tester.tap(find.text('Verify and continue'));
        await tester.pumpAndSettle();
        expect(find.text('Verified destination'), findsOneWidget);
        expect(attempts, 2);
      },
      () => MockClient((r) async {
        attempts++;
        expect(r.url.path, '/api/auth/google/verify');
        final body = jsonDecode(r.body);
        expect(body['challenge_id'], 'challenge');
        return body['code'] == '123456'
            ? http.Response('{"verification_token":"proof"}', 200)
            : http.Response('{"message":"Incorrect code."}', 422);
      }),
    );
  });
}

import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:flutter_application_1/seller/seller_returns_screen.dart';
import 'package:flutter_application_1/services/api_service.dart';

void main() {
  setUp(() => ApiService.baseUrl = 'https://example.invalid/api');
  tearDown(() => ApiService.baseUrl = '');

  testWidgets(
    'Seller reviews return then confirms receipt before wallet refund',
    (tester) async {
      var status = 'requested';
      var posts = 0;
      var response = '';
      await http.runWithClient(
        () async {
          await tester.pumpWidget(
            const MaterialApp(home: SellerReturnsScreen(connected: true)),
          );
          await tester.pumpAndSettle();
          await tester.tap(find.text('Approve return'));
          await tester.pumpAndSettle();
          await tester.tap(find.text('Approve'));
          await tester.pumpAndSettle();
          expect(posts, 0);
          await tester.enterText(
            find.byType(TextFormField),
            'Return to our shop address',
          );
          await tester.tap(find.text('Approve'));
          await tester.pumpAndSettle();
          expect(posts, 1);
          expect(
            find.textContaining('Your response: Return to our shop address'),
            findsOneWidget,
          );
          await tester.tap(find.text('Refund to buyer wallet'));
          await tester.pumpAndSettle();
          final confirm = find.widgetWithText(FilledButton, 'Confirm refund');
          expect(tester.widget<FilledButton>(confirm).onPressed, isNull);
          await tester.tap(find.byType(Checkbox));
          await tester.pumpAndSettle();
          await tester.tap(confirm);
          await tester.pumpAndSettle();
          expect(posts, 2);
          expect(find.text('ORDER-1 · refunded'), findsOneWidget);
          expect(find.text('Refund to buyer wallet'), findsNothing);
          expect(tester.takeException(), isNull);
        },
        () => MockClient((r) async {
          if (r.method == 'POST') {
            posts++;
            final body = jsonDecode(r.body);
            if (r.url.path.endsWith('/approve')) {
              expect(body['response'], 'Return to our shop address');
              response = body['response'];
              status = 'approved';
            } else {
              expect(r.url.path, '/api/seller/returns/7/refund');
              expect(body['items_received'], true);
              expect(body.containsKey('amount'), isFalse);
              status = 'refunded';
            }
            return http.Response('{"message":"Saved"}', 200);
          }
          return http.Response(
            jsonEncode([
              {
                'id': '7',
                'reference': 'ORDER-1',
                'status': status,
                'buyer': 'Buyer',
                'product': 'Basket',
                'reason': 'Damaged',
                'description': 'Broken handle',
                'amount_minor': 10000,
                'response': response,
              },
            ]),
            200,
            headers: {'content-type': 'application/json; charset=utf-8'},
          );
        }),
      );
    },
  );
}

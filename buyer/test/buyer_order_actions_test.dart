import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:flutter_application_1/order_manager.dart';
import 'package:flutter_application_1/screen/orders_screen.dart';
import 'package:flutter_application_1/services/api_service.dart';
import 'package:flutter_application_1/services/buyer_data.dart';

Map<String, dynamic> orderRow(String status) => {
  'id': '19',
  'reference': 'LMR-19',
  'store': 'Test shop',
  'items': [
    {
      'id': '31',
      'product_id': '1',
      'name': 'Basket',
      'price': 10000,
      'quantity': 1,
      'variant': 'Natural',
    },
  ],
  'address': {
    'name': 'Buyer',
    'phone': '09171234567',
    'street': 'Street',
    'city': 'Manila',
    'province': 'Metro Manila',
  },
  'payment_method': 'COD',
  'date': 1800000000,
  'subtotal': 10000,
  'shipping': 5000,
  'total': 15000,
  'status': status == 'delivered'
      ? 'To Receive'
      : status == 'completed'
      ? 'Completed'
      : status == 'cancelled'
      ? 'Cancelled'
      : 'To Ship',
  'raw_status': status,
  'paid': status == 'delivered' || status == 'completed',
  'can_cancel': status == 'pending',
  'can_receive': status == 'delivered',
  'can_refund': status == 'delivered' || status == 'completed',
  'timeline': [],
};

void main() {
  setUp(() {
    ApiService.baseUrl = 'https://example.invalid/api';
    BuyerData.instance.connected = true;
  });
  tearDown(() {
    ApiService.baseUrl = '';
    BuyerData.instance.reset();
    OrderManager.instance.clearOrders();
  });

  for (final receive in [false, true]) {
    testWidgets(
      receive
          ? 'Receipt waits for explicit confirmation then refreshes completion'
          : 'Cancellation requires a reason and server confirmation',
      (tester) async {
        var status = receive ? 'delivered' : 'pending';
        var posts = 0;
        OrderManager.instance.replace([orderRow(status)]);
        await http.runWithClient(
          () async {
            await tester.pumpWidget(const MaterialApp(home: OrdersScreen()));
            final action = find.text(
              receive ? 'Order received' : 'Cancel order',
            );
            await tester.ensureVisible(action);
            await tester.tap(action);
            await tester.pumpAndSettle();
            expect(posts, 0);
            final confirm = find.text(
              receive ? 'Confirm received' : 'Confirm cancellation',
            );
            if (!receive) {
              await tester.tap(confirm);
              await tester.pumpAndSettle();
              expect(posts, 0);
              expect(find.text('Enter at least 3 characters.'), findsOneWidget);
              await tester.enterText(
                find.byType(TextFormField),
                'Wrong item ordered',
              );
            }
            await tester.tap(confirm);
            await tester.pumpAndSettle();
            expect(posts, 1);
            expect(
              OrderManager.instance.orders.single.status,
              receive ? 'Completed' : 'Cancelled',
            );
            expect(
              find.text(receive ? 'Order received' : 'Cancel order'),
              findsNothing,
            );
            expect(tester.takeException(), isNull);
          },
          () => MockClient((r) async {
            if (r.method == 'POST') {
              posts++;
              expect(
                r.url.path,
                '/api/orders/19/${receive ? 'received' : 'cancel'}',
              );
              if (!receive) {
                expect(jsonDecode(r.body)['reason'], 'Wrong item ordered');
              }
              status = receive ? 'completed' : 'cancelled';
              return http.Response('{"message":"Saved"}', 200);
            }
            expect(r.url.path, '/api/buyer/workspace');
            return http.Response(
              jsonEncode({
                'profile': {},
                'capabilities': {},
                'categories': [],
                'products': [],
                'cart': [],
                'addresses': [],
                'orders': [orderRow(status)],
                'wishlist': [],
                'notifications': [],
                'vouchers': [],
              }),
              200,
            );
          }),
        );
      },
    );
  }

  testWidgets(
    'Refund action uses the order item id and displays server rejection',
    (tester) async {
      OrderManager.instance.replace([orderRow('delivered')]);
      var posts = 0;
      await http.runWithClient(
        () async {
          await tester.pumpWidget(const MaterialApp(home: OrdersScreen()));
          await tester.ensureVisible(find.text('Return / Refund'));
          await tester.tap(find.text('Return / Refund'));
          await tester.pumpAndSettle();
          await tester.tap(find.text('Basket'));
          await tester.pumpAndSettle();
          expect(
            find.textContaining('Refund destination: Lumear e-wallet'),
            findsOneWidget,
          );
          await tester.enterText(find.byType(TextFormField).at(0), 'Damaged');
          await tester.enterText(
            find.byType(TextFormField).at(1),
            'Basket arrived with a broken handle',
          );
          await tester.ensureVisible(find.text('Submit return request'));
          await tester.tap(find.text('Submit return request'));
          await tester.pumpAndSettle();
          expect(posts, 1);
          expect(find.text('Wallet setup pending.'), findsOneWidget);
          expect(find.text('Request return / refund'), findsOneWidget);
        },
        () => MockClient((r) async {
          posts++;
          expect(r.url.path, '/api/buyer/returns');
          expect(jsonDecode(r.body)['order_item_id'], '31');
          return http.Response('{"message":"Wallet setup pending."}', 503);
        }),
      );
    },
  );
}

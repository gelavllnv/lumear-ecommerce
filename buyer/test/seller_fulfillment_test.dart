import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:flutter_application_1/services/api_service.dart';
import 'package:flutter_application_1/seller/seller_commerce.dart';
import 'package:flutter_application_1/seller/seller_tools_screen.dart';

void main() {
  testWidgets(
    'Cancellation requires typed reason, retains it on failure and shows it after saving',
    (tester) async {
      final data = _RetryCancellationCommerce();
      addTearDown(data.dispose);
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SellerToolsScreen(commerce: data, section: 'Orders'),
          ),
        ),
      );
      await tester.tap(find.text('SO-1001'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Cancel order'));
      await tester.tap(find.text('Cancel order'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Confirm cancellation'));
      await tester.pumpAndSettle();
      expect(find.text('Enter a cancellation reason.'), findsOneWidget);
      expect(data.orders.first.fulfillmentLabel, 'Pending');
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Cancellation reason'),
        '  Damaged during inspection  ',
      );
      await tester.tap(find.text('Confirm cancellation'));
      await tester.pumpAndSettle();
      expect(find.text('Confirm cancellation'), findsOneWidget);
      expect(
        tester
            .widget<TextFormField>(
              find.widgetWithText(TextFormField, 'Cancellation reason'),
            )
            .controller!
            .text,
        '  Damaged during inspection  ',
      );
      expect(data.orders.first.fulfillmentLabel, 'Pending');
      await tester.tap(find.text('Confirm cancellation'));
      await tester.pumpAndSettle();
      expect(data.orders.first.fulfillmentLabel, 'Cancelled');
      expect(data.orders.first.cancellationReason, 'Damaged during inspection');
      expect(find.byType(ChoiceChip), findsNothing);
      await tester.tap(find.byKey(const ValueKey('order-status-filter')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Cancelled').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('SO-1001'));
      await tester.pumpAndSettle();
      expect(
        find.text('Cancellation: Damaged during inspection'),
        findsOneWidget,
      );
      expect(find.text('Accept order'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
  test(
    'Connected archive and pickup use server state and approved partners only',
    () async {
      ApiService.baseUrl = 'https://example.invalid/api';
      addTearDown(() => ApiService.baseUrl = '');
      final data = SellerCommerce(connected: true);
      addTearDown(data.dispose);
      var archived = false, state = 'pending', fail = true;
      final requests = <String>[];
      await http.runWithClient(
        () async {
          await data.load();
          await expectLater(
            data.markPacked(data.orders.first),
            throwsStateError,
          );
          await expectLater(
            data.accept(data.orders.first),
            throwsA(isA<ApiException>()),
          );
          expect(data.orders.first.fulfillmentLabel, 'Pending');
          fail = false;
          await data.accept(data.orders.first);
          expect(data.orders.first.fulfillmentLabel, 'Accepted');
          await data.markPacked(data.orders.first);
          requests.clear();
          fail = true;
          expect(data.couriers.map((c) => c.id), ['approved']);
          await expectLater(
            data.requestPickup(data.orders.first, 'blocked'),
            throwsStateError,
          );
          expect(requests, isEmpty);
          await expectLater(
            data.requestPickup(data.orders.first, 'approved'),
            throwsA(isA<ApiException>()),
          );
          expect(data.orders.first.fulfillmentLabel, 'Packed');
          fail = false;
          await data.requestPickup(data.orders.first, 'approved');
          expect(data.orders.first.fulfillmentLabel, 'Pickup requested');
          await data.setArchived(data.products.first, true);
          expect(data.products.first.archived, isTrue);
          expect(data.products.first.active, isFalse);
          await data.setArchived(data.products.first, false);
          expect(data.products.first.archived, isFalse);
          expect(requests.where((p) => p.endsWith('/archive')).length, 2);
          state = 'accepted';
          await data.load();
          await data.cancel(data.orders.first, '  Stock damaged  ');
          expect(data.orders.first.fulfillmentLabel, 'Cancelled');
          expect(data.orders.first.cancellationReason, 'Stock damaged');
        },
        () => MockClient((request) async {
          if (request.method == 'GET') {
            return http.Response(
              jsonEncode({
                'shop': {},
                'earnings': {},
                'couriers': [
                  {
                    'id': 'approved',
                    'name': 'Approved Partner',
                    'approved': true,
                  },
                  {
                    'id': 'blocked',
                    'name': 'Blocked Partner',
                    'approved': false,
                  },
                ],
                'products': [
                  {
                    'id': 'p1',
                    'name': 'Basket',
                    'price': 10000,
                    'stock': 3,
                    'category': 'Home and Garden',
                    'description': '',
                    'active': false,
                    'archived': archived,
                  },
                ],
                'orders': [
                  {
                    'id': 'o1',
                    'buyer': 'Buyer',
                    'items': [],
                    'subtotal': 10000,
                    'address': {
                      'street': 'Street',
                      'city': 'City',
                      'province': 'Province',
                    },
                    'date': 1793664000,
                    'commission': 1000,
                    'payment_method': 'COD',
                    'shipping': 5000,
                    'raw_status': state,
                    'payment_status': 'Pending',
                    'cancellation_reason': state == 'cancelled'
                        ? 'Stock damaged'
                        : null,
                  },
                ],
              }),
              200,
            );
          }
          requests.add(request.url.path);
          if (request.url.path.endsWith('/accept')) {
            expect(request.method, 'POST');
            expect(jsonDecode(request.body), isEmpty);
            if (fail) return http.Response('{"message":"Order changed"}', 409);
            state = 'accepted';
          } else if (request.url.path.endsWith('/pack')) {
            expect(state, 'accepted');
            state = 'packed';
          } else if (request.url.path.endsWith('/cancel')) {
            expect(jsonDecode(request.body), {'reason': 'Stock damaged'});
            state = 'cancelled';
          } else if (request.url.path.endsWith('/pickup')) {
            expect(jsonDecode(request.body), {'courier_id': 'approved'});
            if (fail) {
              return http.Response('{"message":"Courier unavailable"}', 409);
            }
            state = 'ready_to_ship';
          } else {
            expect(request.method, 'PATCH');
            expect(request.url.path, '/api/seller/products/p1/archive');
            archived = jsonDecode(request.body)['archived'] as bool;
          }
          return http.Response('{}', 200);
        }),
      );
    },
  );

  for (final width in [360.0, 1200.0]) {
    testWidgets('Archive and restore are accessible at width $width', (
      tester,
    ) async {
      tester.view.physicalSize = Size(width, 900);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final data = SellerCommerce();
      addTearDown(data.dispose);
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SellerToolsScreen(commerce: data, section: 'Products'),
          ),
        ),
      );
      final archive = find.widgetWithText(TextButton, 'Archive').first;
      await tester.ensureVisible(archive);
      await tester.tap(archive);
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Archive'));
      await tester.pumpAndSettle();
      expect(data.products.first.archived, isTrue);
      final chip = find.widgetWithText(ChoiceChip, 'Archived');
      await tester.ensureVisible(chip);
      await tester.tap(chip);
      await tester.pumpAndSettle();
      final restore = find.widgetWithText(TextButton, 'Restore');
      await tester.ensureVisible(restore);
      await tester.tap(restore);
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Restore'));
      await tester.pumpAndSettle();
      expect(data.products.first.archived, isFalse);
      expect(data.products.first.active, isFalse);
      expect(tester.takeException(), isNull);
    });
  }

  test(
    'Pickup requires packing and an approved courier and cannot repeat',
    () async {
      final data = SellerCommerce();
      addTearDown(data.dispose);
      final order = data.orders.first;
      await expectLater(data.requestPickup(order, 'demo'), throwsStateError);
      await expectLater(data.markPacked(order), throwsStateError);
      await data.accept(order);
      expect(order.fulfillmentLabel, 'Accepted');
      await expectLater(data.accept(order), throwsStateError);
      await data.markPacked(order);
      await expectLater(
        data.requestPickup(order, 'unapproved'),
        throwsStateError,
      );
      expect(order.fulfillmentLabel, 'Packed');
      await data.requestPickup(order, 'demo');
      expect(order.fulfillmentLabel, 'Pickup requested');
      expect(order.courierName, 'Lumear Demo Logistics');
      expect(order.status, isNot(SellerOrderStatus.inTransit));
      await expectLater(data.requestPickup(order, 'demo'), throwsStateError);
      await expectLater(data.cancel(order, 'Too late'), throwsStateError);
      await expectLater(data.markPacked(data.orders[2]), throwsStateError);
    },
  );

  test(
    'Archiving preserves product and order history, restores hidden',
    () async {
      final data = SellerCommerce();
      addTearDown(data.dispose);
      final product = data.products[1];
      final orders = data.orders.map((o) => o.id).toList();
      await data.setArchived(product, true);
      final archived = data.products.firstWhere((p) => p.id == product.id);
      expect(archived.active, isFalse);
      expect(archived.archived, isTrue);
      expect(archived.stock, product.stock);
      expect(data.lowStockProducts.any((p) => p.id == product.id), isFalse);
      expect(data.orders.map((o) => o.id), orders);
      await data.setArchived(archived, false);
      final restored = data.products.firstWhere((p) => p.id == product.id);
      expect(restored.archived, isFalse);
      expect(restored.active, isFalse);
    },
  );

  testWidgets(
    'Phone seller selects courier after packing and shipped orders have no actions',
    (tester) async {
      tester.view.physicalSize = const Size(360, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final data = SellerCommerce();
      addTearDown(data.dispose);
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SellerToolsScreen(commerce: data, section: 'Orders'),
          ),
        ),
      );
      await tester.tap(find.text('SO-1001'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Accept order'));
      await tester.tap(find.text('Accept order'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('SO-1001'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Mark packed'));
      await tester.tap(find.text('Mark packed'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('SO-1001'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Request courier pickup'));
      await tester.tap(find.text('Request courier pickup'));
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<FilledButton>(
              find.widgetWithText(FilledButton, 'Request pickup'),
            )
            .onPressed,
        isNull,
      );
      await tester.tap(
        find.descendant(
          of: find.byType(AlertDialog),
          matching: find.byType(DropdownButtonFormField<String>),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('Lumear Demo Logistics').last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Request pickup'));
      await tester.pumpAndSettle();
      expect(data.orders.first.fulfillmentLabel, 'Pickup requested');
      await tester.scrollUntilVisible(
        find.text('SO-1003'),
        180,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(find.text('SO-1003'));
      await tester.pumpAndSettle();
      expect(
        find.text('This order is shipped and has no further seller actions.'),
        findsOneWidget,
      );
      expect(find.text('Request courier pickup'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );
}

class _RetryCancellationCommerce extends SellerCommerce {
  bool fail = true;
  @override
  Future<void> cancel(SellerOrder order, String reason) async {
    if (fail) {
      fail = false;
      throw StateError('Please retry.');
    }
    await super.cancel(order, reason);
  }
}

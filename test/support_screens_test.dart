import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:flutter_application_1/screen/help_center_screen.dart';
import 'package:flutter_application_1/screen/notification_settings_screen.dart';
import 'package:flutter_application_1/services/api_service.dart';
import 'package:flutter_application_1/services/buyer_data.dart';

void main() {
  tearDown(() {
    ApiService.baseUrl = '';
    ApiService.setToken(null);
    BuyerData.instance.reset();
  });

  testWidgets('Help Center searches real guidance and opens an answer', (
    tester,
  ) async {
    await tester.runAsync(() async {
      await tester.pumpWidget(
        const MaterialApp(home: HelpCenterScreen(connected: false)),
      );
      await rootBundle.loadString('assets/data/lumear_help.json');
      await Future<void>.delayed(Duration.zero);
    });
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'damaged');
    await tester.pumpAndSettle();
    expect(
      find.text('How do I report a damaged or incorrect item?'),
      findsOneWidget,
    );
    await tester.tap(find.text('How do I report a damaged or incorrect item?'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Keep the item and packaging'), findsOneWidget);
    expect(find.text('Where can I track my order?'), findsNothing);
  });

  testWidgets(
    'Notification preferences save, survive reopen, and retain draft on failure',
    (tester) async {
      ApiService.baseUrl = 'https://example.invalid/api';
      ApiService.setToken('test-session');
      var values = {
        'order_updates': true,
        'promotions': true,
        'general_updates': true,
      };
      var fail = true;
      await http.runWithClient(
        () async {
          Future<void> mount() => tester.pumpWidget(
            const MaterialApp(
              home: NotificationSettingsScreen(connected: true),
            ),
          );
          await mount();
          await tester.pumpAndSettle();
          final promotions = find.widgetWithText(
            SwitchListTile,
            'Promotions and offers',
          );
          await tester.tap(promotions);
          await tester.pumpAndSettle();
          final save = find.widgetWithText(FilledButton, 'Save preferences');
          await tester.ensureVisible(save);
          await tester.tap(save);
          await tester.pumpAndSettle();
          expect(find.text('Please retry saving.'), findsOneWidget);
          expect(tester.widget<SwitchListTile>(promotions).value, isFalse);
          expect(values['promotions'], isTrue);
          await tester.tap(save);
          await tester.pumpAndSettle();
          expect(values['promotions'], isFalse);
          await tester.pumpWidget(const SizedBox.shrink());
          await mount();
          await tester.pumpAndSettle();
          expect(
            tester
                .widget<SwitchListTile>(
                  find.widgetWithText(SwitchListTile, 'Promotions and offers'),
                )
                .value,
            isFalse,
          );
          expect(find.textContaining('in-app inbox only'), findsOneWidget);
        },
        () => MockClient((request) async {
          if (request.url.path.endsWith('/buyer/workspace')) {
            return http.Response('{}', 200);
          }
          expect(request.headers['Authorization'], 'Bearer test-session');
          if (request.method == 'PATCH') {
            if (fail) {
              fail = false;
              return http.Response('{"message":"Please retry saving."}', 503);
            }
            values = Map<String, bool>.from(jsonDecode(request.body));
          }
          return http.Response(jsonEncode(values), 200);
        }),
      );
    },
  );
}

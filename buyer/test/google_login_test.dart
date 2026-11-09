import 'dart:convert';
import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:flutter_application_1/design/lumear_theme.dart';
import 'package:flutter_application_1/login_page.dart';
import 'package:flutter_application_1/screen/dashboard_screen.dart';
import 'package:flutter_application_1/seller/seller_dashboard_screen.dart';
import 'package:flutter_application_1/services/api_service.dart';
import 'package:flutter_application_1/services/buyer_data.dart';
import 'package:flutter_application_1/services/postal_code_service.dart';

final class _VerificationFile extends PlatformFile {
  @override
  String get name => 'verification.pdf';
  @override
  Future<Uint8List> readAsBytes() async =>
      Uint8List.fromList(utf8.encode('%PDF-1.4 test'));
  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

class _VerificationPicker extends FilePickerPlatform {
  @override
  Future<List<PlatformFile>> pickFiles({
    String? dialogTitle,
    String? initialDirectory,
    FileType type = FileType.any,
    List<String>? allowedExtensions,
    Function(FilePickerStatus)? onFileLoading,
    int compressionQuality = 0,
    AndroidOptions androidOptions = const AndroidOptions(),
    DarwinOptions darwinOptions = const DarwinOptions(),
    WindowsOptions windowsOptions = const WindowsOptions(),
    LinuxOptions linuxOptions = const LinuxOptions(),
    WebOptions webOptions = const WebOptions(),
  }) async => [_VerificationFile()];
}

void main() {
  setUp(() => ApiService.baseUrl = 'https://integration.example.invalid/api');
  tearDown(() {
    ApiService.baseUrl = '';
    ApiService.setToken(null);
    BuyerData.instance.reset();
  });

  Future<void> tapGoogle(WidgetTester tester) async {
    final button = find.widgetWithText(OutlinedButton, 'Continue with Google');
    await tester.ensureVisible(button);
    await tester.tap(button);
    await tester.pumpAndSettle();
    if (find.text('Continue with Google?').evaluate().isNotEmpty) {
      await tester.tap(find.widgetWithText(FilledButton, 'Continue'));
      await tester.pumpAndSettle();
    }
  }

  testWidgets(
    'Demo Google button explains missing integration without opening Google',
    (tester) async {
      var called = false;
      await tester.pumpWidget(
        MaterialApp(
          theme: lumearTheme(),
          home: LoginPage(
            useBackend: false,
            googleIdTokenProvider: () async {
              called = true;
              return 'unused';
            },
          ),
        ),
      );
      await tapGoogle(tester);
      expect(called, isFalse);
      expect(
        find.textContaining('Google sign-in will be available'),
        findsOneWidget,
      );
      expect(find.byType(LoginPage), findsOneWidget);
    },
  );

  testWidgets('Canceling Lumear confirmation never opens Google', (
    tester,
  ) async {
    var called = false;
    await tester.pumpWidget(
      MaterialApp(
        theme: lumearTheme(),
        home: LoginPage(
          useBackend: true,
          googleIdTokenProvider: () async {
            called = true;
            return 'unused';
          },
        ),
      ),
    );
    final button = find.widgetWithText(OutlinedButton, 'Continue with Google');
    await tester.ensureVisible(button);
    await tester.tap(button);
    await tester.pumpAndSettle();
    expect(find.text('Continue with Google?'), findsOneWidget);
    await tester.tap(find.widgetWithText(TextButton, 'Cancel'));
    await tester.pumpAndSettle();
    expect(called, isFalse);
    expect(find.byType(LoginPage), findsOneWidget);
  });

  testWidgets(
    'Canceling Google does not call the backend or open a workspace',
    (tester) async {
      var requests = 0;
      await http.runWithClient(
        () async {
          await tester.pumpWidget(
            MaterialApp(
              theme: lumearTheme(),
              home: LoginPage(
                useBackend: true,
                googleIdTokenProvider: () async => null,
              ),
            ),
          );
          await tapGoogle(tester);
          expect(requests, 0);
          expect(find.byType(LoginPage), findsOneWidget);
          expect(
            tester
                .widget<OutlinedButton>(
                  find.widgetWithText(OutlinedButton, 'Continue with Google'),
                )
                .onPressed,
            isNotNull,
          );
        },
        () => MockClient((_) async {
          requests++;
          return http.Response('{}', 500);
        }),
      );
    },
  );

  for (final role in ['buyer', 'seller']) {
    testWidgets(
      'Google token exchange opens the server assigned $role workspace',
      (tester) async {
        var exchanges = 0;
        await http.runWithClient(
          () async {
            await tester.pumpWidget(
              MaterialApp(
                theme: lumearTheme(),
                home: LoginPage(
                  useBackend: true,
                  googleIdTokenProvider: () async => 'google-id-token',
                ),
              ),
            );
            await tapGoogle(tester);
            expect(exchanges, 1);
            expect(
              find.byType(
                role == 'seller' ? SellerDashboardScreen : DashboardScreen,
              ),
              findsOneWidget,
            );
            expect(tester.takeException(), isNull);
            await tester.pumpWidget(const SizedBox.shrink());
          },
          () => MockClient((request) async {
            if (request.url.path == '/api/auth/google') {
              exchanges++;
              expect(jsonDecode(request.body), {'id_token': 'google-id-token'});
              expect(request.headers['Authorization'], isNull);
              return http.Response(
                jsonEncode({'token': 'lumear-session', 'role': role}),
                200,
              );
            }
            expect(request.headers['Authorization'], 'Bearer lumear-session');
            final response = role == 'buyer'
                ? {
                    'profile': {
                      'name': 'Google Buyer',
                      'email': 'buyer@example.invalid',
                      'phone': '',
                    },
                    'products': [],
                    'cart': [],
                    'orders': [],
                    'addresses': [],
                    'wishlist': [],
                    'notifications': [],
                    'vouchers': [],
                    'capabilities': {},
                  }
                : {
                    'shop': {
                      'owner': 'Seller',
                      'name': 'Google Shop',
                      'email': '',
                      'phone': '',
                      'description': '',
                    },
                    'products': [],
                    'orders': [],
                    'categories': [],
                    'earnings': {},
                  };
            return http.Response(jsonEncode(response), 200);
          }),
        );
      },
    );
  }

  for (final invalidSession in [false, true]) {
    testWidgets(
      'Google ${invalidSession ? 'invalid session' : 'rejection'} never falls back to demo',
      (tester) async {
        await http.runWithClient(
          () async {
            await tester.pumpWidget(
              MaterialApp(
                theme: lumearTheme(),
                home: LoginPage(
                  useBackend: true,
                  googleIdTokenProvider: () async => 'google-id-token',
                ),
              ),
            );
            await tapGoogle(tester);
            expect(find.byType(LoginPage), findsOneWidget);
            expect(find.byType(DashboardScreen), findsNothing);
            expect(find.byType(SellerDashboardScreen), findsNothing);
            expect(
              find.textContaining(
                invalidSession
                    ? 'could not create a Lumear session'
                    : 'Google account not linked',
              ),
              findsOneWidget,
            );
          },
          () => MockClient(
            (_) async => http.Response(
              jsonEncode(
                invalidSession
                    ? {'token': 'invalid-role-session', 'role': 'admin'}
                    : {'message': 'Google account not linked'},
              ),
              invalidSession ? 200 : 403,
            ),
          ),
        );
      },
    );
  }

  for (final role in ['buyer', 'seller']) {
    testWidgets('New Google user chooses $role before account creation', (
      tester,
    ) async {
      final originalPicker = FilePickerPlatform.instance;
      FilePickerPlatform.instance = _VerificationPicker();
      addTearDown(() => FilePickerPlatform.instance = originalPicker);
      var exchanges = 0;
      await tester.runAsync(() async {
        await PostalCodeService.load();
      });
      await http.runWithClient(
        () async {
          await tester.pumpWidget(
            MaterialApp(
              theme: lumearTheme(),
              home: LoginPage(
                useBackend: true,
                googleIdTokenProvider: () async => 'verified-google-token',
              ),
            ),
          );
          await tapGoogle(tester);
          expect(find.text('Create your account'), findsOneWidget);
          expect(exchanges, 1);
          await tester.tap(
            find.text(role == 'buyer' ? 'Buyer' : 'Seller').last,
          );
          await tester.pumpAndSettle();
          Future<void> fill(String label, String value) async {
            final field = find.widgetWithText(TextFormField, label);
            await tester.ensureVisible(field);
            await tester.enterText(field, value);
          }

          final complete = find.widgetWithText(
            FilledButton,
            'Complete registration',
          );
          FocusManager.instance.primaryFocus?.unfocus();
          await tester.pumpAndSettle();
          await tester.ensureVisible(complete);
          await tester.pumpAndSettle();
          await tester.tap(complete);
          await tester.pumpAndSettle();
          expect(exchanges, 1);
          await fill('Full name', 'New Google user');
          await fill('Contact number', '09171234567');
          final dob = find.text('Select your date of birth');
          await tester.ensureVisible(dob);
          await tester.tap(dob);
          await tester.pumpAndSettle();
          await tester.tap(find.text('OK'));
          await tester.pumpAndSettle();
          await fill('Password', 'GooglePassword123');
          await fill('Confirm password', 'GooglePassword123');
          for (final label in ['Password', 'Confirm password']) {
            final target = find.widgetWithText(TextFormField, label);
            Finder input() =>
                find.descendant(of: target, matching: find.byType(TextField));
            expect(tester.widget<TextField>(input()).obscureText, isTrue);
            final toggle = find.byTooltip('Show $label');
            await tester.ensureVisible(toggle);
            await tester.pumpAndSettle();
            await tester.tap(toggle);
            await tester.pumpAndSettle();
            expect(tester.widget<TextField>(input()).obscureText, isFalse);
            await tester.tap(find.byTooltip('Hide $label'));
            await tester.pumpAndSettle();
            expect(tester.widget<TextField>(input()).obscureText, isTrue);
          }
          if (role == 'buyer') {
            await fill('Recipient name', 'New Google user');
            await fill('Delivery contact number', '09171234567');
            await fill('Street, building, or unit', 'Test street');
            Future<void> pick(String label, String value) async {
              final dropdown = find.widgetWithText(
                DropdownButtonFormField<String>,
                label,
              );
              await tester.ensureVisible(dropdown);
              await tester.pumpAndSettle();
              await tester.tap(dropdown);
              await tester.pumpAndSettle();
              await tester.tap(find.text(value).last);
              await tester.pumpAndSettle();
            }

            await pick('Province', 'Laguna');
            await pick('City / Municipality', 'Pila');
            await pick('Barangay', 'Test barangay');
            final postal = tester.widget<TextFormField>(
              find.widgetWithText(TextFormField, 'Postal code'),
            );
            expect(postal.controller!.text, '4010');
            await pick('Province', 'Metro Manila');
            expect(postal.controller!.text, isEmpty);
            final cityDropdown = tester.widget<DropdownButtonFormField<String>>(
              find.widgetWithText(
                DropdownButtonFormField<String>,
                'City / Municipality',
              ),
            );
            expect(cityDropdown.initialValue, isNull);
            await pick('Province', 'Laguna');
            await pick('City / Municipality', 'Pila');
            await pick('Barangay', 'Test barangay');
            expect(postal.controller!.text, '4010');
          } else {
            expect(find.text('Default delivery address'), findsNothing);
            expect(
              find.widgetWithText(TextFormField, 'Recipient name'),
              findsNothing,
            );
            expect(
              find.widgetWithText(TextFormField, 'Postal code'),
              findsNothing,
            );
          }
          if (role == 'seller') {
            await fill('Shop name', 'Google test shop');
            await fill('Shop description', 'Handmade goods from our test shop');
            await fill('TIN number', '123-456-789');
            expect(find.text('Selfie with ID'), findsNothing);
            expect(find.text('Business registration document'), findsNothing);
            FocusManager.instance.primaryFocus?.unfocus();
            await tester.pumpAndSettle();
            await tester.ensureVisible(complete);
            await tester.tap(complete);
            await tester.pumpAndSettle();
            expect(exchanges, 1);
            expect(
              find.text('Select your government-issued id.'),
              findsOneWidget,
            );
            for (var i = 0; i < 1; i++) {
              final select = find
                  .widgetWithText(TextButton, 'Select file')
                  .first;
              await tester.ensureVisible(select);
              await tester.pumpAndSettle();
              await tester.tap(select);
              await tester.pumpAndSettle();
            }
          }
          FocusManager.instance.primaryFocus?.unfocus();
          await tester.pumpAndSettle();
          await tester.ensureVisible(complete);
          await tester.pumpAndSettle();
          await tester.tap(complete);
          await tester.pumpAndSettle();
          expect(exchanges, 2);
          expect(
            find.text('Registration could not be saved. Try again.'),
            findsOneWidget,
          );
          expect(find.text('Create your account'), findsOneWidget);
          expect(find.byType(DashboardScreen), findsNothing);
          final nameField = tester.widget<TextFormField>(
            find.widgetWithText(TextFormField, 'Full name'),
          );
          expect(nameField.controller!.text, 'New Google user');
          if (role == 'buyer') {
            expect(
              tester
                  .widget<TextFormField>(
                    find.widgetWithText(TextFormField, 'Postal code'),
                  )
                  .controller!
                  .text,
              '4010',
            );
          }
          await tester.ensureVisible(complete);
          await tester.pumpAndSettle();
          await tester.tap(complete);
          await tester.pumpAndSettle();
          expect(exchanges, 3);
          expect(
            find.byType(
              role == 'buyer' ? DashboardScreen : SellerDashboardScreen,
            ),
            findsOneWidget,
          );
          expect(tester.takeException(), isNull);
          await tester.pumpWidget(const SizedBox.shrink());
        },
        () => MockClient((request) async {
          if (request.url.host == 'psgc.cloud') {
            final path = request.url.path;
            final rows = path.endsWith('/provinces')
                ? [
                    {'code': 'laguna', 'name': 'Laguna'},
                  ]
                : path.endsWith('/regions')
                ? [
                    {'code': 'ncr', 'name': 'National Capital Region'},
                  ]
                : path.endsWith('/cities-municipalities')
                ? [
                    {'code': 'pila', 'name': 'Pila'},
                  ]
                : [
                    {'code': 'test', 'name': 'Test barangay'},
                  ];
            return http.Response(jsonEncode({'data': rows}), 200);
          }
          if (request.url.path == '/api/auth/google') {
            exchanges++;
            final body = jsonDecode(request.body);
            expect(body['id_token'], 'verified-google-token');
            if (exchanges == 1) {
              expect(body.containsKey('account_type'), isFalse);
              return http.Response(
                '{"requires_account_type":true,"is_new_account":true,"email":"new@example.invalid"}',
                200,
              );
            }
            expect(body['account_type'], role);
            expect(body['date_of_birth'], isNotEmpty);
            expect(body['phone'], '09171234567');
            if (role == 'buyer') {
              expect(body['address']['postal_code'], '4010');
            } else {
              expect(body.containsKey('address'), isFalse);
            }
            expect(body['password'], 'GooglePassword123');
            if (role == 'seller') {
              expect(body['shop_name'], 'Google test shop');
              expect((body['documents'] as Map).keys, equals(['id']));
              expect(body['tax_number'], '123-456-789');
            } else {
              expect(body.containsKey('documents'), isFalse);
            }
            if (exchanges == 2) {
              return http.Response(
                jsonEncode({
                  'message': 'Registration could not be saved. Try again.',
                }),
                503,
              );
            }
            return http.Response(
              jsonEncode({'token': 'new-google-session', 'role': role}),
              200,
            );
          }
          if (role == 'buyer') {
            return http.Response(
              jsonEncode({
                'profile': {
                  'name': 'Google buyer',
                  'email': 'buyer@example.invalid',
                },
                'products': [],
                'cart': [],
                'orders': [],
                'addresses': [],
                'wishlist': [],
                'notifications': [],
                'vouchers': [],
                'capabilities': {},
              }),
              200,
            );
          }
          return http.Response(
            jsonEncode({
              'shop': {
                'owner': 'Google seller',
                'name': 'Google shop',
                'email': 'seller@example.invalid',
                'phone': '',
                'description': '',
                'status': 'pending',
              },
              'products': [],
              'orders': [],
              'earnings': {},
              'categories': [],
            }),
            200,
          );
        }),
      );
    });
  }
}

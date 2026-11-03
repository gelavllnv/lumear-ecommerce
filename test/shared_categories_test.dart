import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_application_1/product_categories.dart';
import 'package:flutter_application_1/seller/seller_commerce.dart';
import 'package:flutter_application_1/seller/seller_product_editor_screen.dart';

void main() {
  tearDown(ProductCategories.reset);
  void shared() => ProductCategories.useShared([
    {'id': '1', 'name': 'Fashion', 'parent_id': null},
    {'id': '2', 'name': 'Clothes', 'parent_id': '1'},
    {'id': '3', 'name': 'Local Picks', 'parent_id': null},
  ]);
  test('Connected categories preserve database names and hierarchy', () {
    shared();
    expect(ProductCategories.names, ['Fashion', 'Local Picks']);
    expect(ProductCategories.canonical('Fashion'), 'Fashion');
    expect(ProductCategories.children('Fashion'), ['Clothes']);
    expect(ProductCategories.children('Local Picks'), isEmpty);
    ProductCategories.reset();
    expect(ProductCategories.names, contains("Women's Apparel"));
  });
  testWidgets('Shared categories without children can save a listing', (
    tester,
  ) async {
    shared();
    SellerProduct? saved;
    await tester.pumpWidget(
      MaterialApp(
        home: Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () async {
                saved = await Navigator.push<SellerProduct>(
                  context,
                  MaterialPageRoute(
                    builder: (_) => const SellerProductEditorScreen(),
                  ),
                );
              },
              child: const Text('Open editor'),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Open editor'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Product name'),
      'Local basket',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Price (₱)'),
      '100',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Available stock'),
      '3',
    );
    final category = find.byType(DropdownButtonFormField<String>).first;
    await tester.ensureVisible(category);
    await tester.tap(category);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Local Picks').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save product'));
    await tester.pumpAndSettle();
    expect(saved?.category, 'Local Picks');
    expect(saved?.subcategory, '');
    expect(tester.takeException(), isNull);
  });
}

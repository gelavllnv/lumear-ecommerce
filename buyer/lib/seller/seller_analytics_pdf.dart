import 'package:flutter/services.dart';
import 'package:pdf/widgets.dart' as pw;

import 'seller_analytics.dart';

Future<Uint8List> buildSellerAnalyticsPdf({
  required SellerAnalyticsSnapshot snapshot,
  required SellerAnalyticsRange selectedRange,
  required String shopName,
  required bool demo,
}) async {
  final font = pw.Font.ttf(
    await rootBundle.load('assets/fonts/roboto-regular.ttf'),
  );
  final logoData = await rootBundle.load('assets/images/logo.png');
  final logo = pw.MemoryImage(
    logoData.buffer.asUint8List(logoData.offsetInBytes, logoData.lengthInBytes),
  );
  final doc = pw.Document(theme: pw.ThemeData.withFont(base: font));
  pw.Widget table(List<String> headers, List<List<String>> rows) =>
      pw.TableHelper.fromTextArray(headers: headers, data: rows);
  doc.addPage(
    pw.MultiPage(
      maxPages: 1000,
      header: (_) => pw.Padding(
        padding: const pw.EdgeInsets.only(bottom: 12),
        child: pw.Row(
          crossAxisAlignment: pw.CrossAxisAlignment.center,
          children: [
            pw.Image(logo, width: 48, height: 48, fit: pw.BoxFit.contain),
            pw.SizedBox(width: 12),
            pw.Expanded(
              child: pw.Text(
                '$shopName — Seller report',
                style: const pw.TextStyle(fontSize: 22),
              ),
            ),
          ],
        ),
      ),
      build: (_) => [
        if (snapshot.notice != null) pw.Text(snapshot.notice!),
        pw.Text('${selectedRange.label} · Philippine time'),
        pw.Text('Previous: ${selectedRange.previous.label}'),
        if (demo) pw.Text('DEMO / SAMPLE DATA'),
        pw.Text(
          'Completed merchandise sales; shipping excluded. Earnings deduct recorded commission and refunds. Conversion = completed orders / shop visits.',
        ),
        table(
          ['Metric', 'Value', 'Comparison'],
          [
            for (final e in snapshot.current.entries)
              [
                e.key,
                e.key == 'conversion' && !snapshot.trafficAvailable
                    ? 'Unavailable'
                    : analyticsValue(e.key, e.value),
                analyticsChange(e.key, snapshot),
              ],
          ],
        ),
        pw.Text('Revenue by day'),
        table(
          ['Date', 'Revenue'],
          [
            for (final r in snapshot.daily)
              ['${r['date']}', analyticsValue('revenue', r['revenue'] as num)],
          ],
        ),
        pw.Text('Product performance'),
        table(
          [
            'Product',
            'Views',
            'Cart %',
            'Purchases',
            'Units',
            'Revenue',
            'Stock',
          ],
          [
            for (final r in snapshot.products)
              [
                for (final k in [
                  'name',
                  'views',
                  'cart_rate',
                  'purchases',
                  'units',
                  'revenue',
                  'stock',
                ])
                  !snapshot.trafficAvailable &&
                          ['views', 'cart_rate'].contains(k)
                      ? 'Unavailable'
                      : k == 'revenue'
                      ? analyticsValue(k, r[k] as num)
                      : '${r[k]}',
              ],
          ],
        ),
        pw.Text(
          'Customers: ${snapshot.customers['new']} new; ${snapshot.customers['returning']} returning; ${snapshot.customers['repeat']} repeat buyers.',
        ),
        for (final r in snapshot.customers['locations'] as List)
          pw.Text('${r['name']}: ${r['buyers']} buyers'),
        pw.Text('Promotion performance'),
        table(
          [
            'Promotion',
            'Views',
            'Claims',
            'Redemptions',
            'Revenue',
            'Conversion',
          ],
          [
            for (final r in snapshot.promotions)
              [
                '${r['name']}',
                snapshot.trafficAvailable ? '${r['views']}' : 'Unavailable',
                snapshot.trafficAvailable ? '${r['claims']}' : 'Unavailable',
                '${r['redemptions']}',
                snapshot.trafficAvailable
                    ? analyticsValue('revenue', r['revenue'] as num)
                    : 'Unavailable',
                snapshot.trafficAvailable
                    ? analyticsValue('conversion', r['conversion'] as num)
                    : 'Unavailable',
              ],
          ],
        ),
        pw.Text('Current order snapshot'),
        for (final e in snapshot.operations.entries)
          pw.Text('${e.key}: ${e.value}'),
        for (final r in snapshot.returns)
          pw.Text(
            'Return ${r['id']} · ${r['order_id']} · ${r['status']} · ${r['reason']}',
          ),
      ],
    ),
  );
  return doc.save();
}

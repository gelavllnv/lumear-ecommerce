import 'dart:typed_data';

import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;

class TruckHandoverPdfService {
  /// Gumagawa ng PDF bytes lang. Ginagamit ng preview screen
  /// para sa preview, print, at share.
  static Future<Uint8List> buildReportBytes({
    required String tripId,
    required String origin,
    required String destination,
    required String eta,
    required String cargo,
    required int totalParcels,
    required int handedOverParcels,
    required int failedParcels,
    required int remainingParcels,
    required String receiverName,
    required String remarks,
    required bool handoverConfirmed,
    PdfPageFormat format = PdfPageFormat.a4,
  }) async {
    final pdf = pw.Document();
    final generatedAt = DateTime.now();

    final countsMatch =
        handedOverParcels + failedParcels + remainingParcels == totalParcels;

    final status = handoverConfirmed
        ? (handedOverParcels == totalParcels &&
                  failedParcels == 0 &&
                  remainingParcels == 0
              ? 'SUCCESSFUL'
              : 'PARTIALLY SUCCESSFUL')
        : 'PENDING CONFIRMATION';

    pdf.addPage(
      pw.MultiPage(
        pageFormat: format,
        margin: const pw.EdgeInsets.all(36),
        build: (context) => [
          pw.Container(
            width: double.infinity,
            padding: const pw.EdgeInsets.all(18),
            decoration: pw.BoxDecoration(
              color: PdfColor.fromHex('#62191C'),
              borderRadius: pw.BorderRadius.circular(8),
            ),
            child: pw.Column(
              crossAxisAlignment: pw.CrossAxisAlignment.start,
              children: [
                pw.Text(
                  'LUMEAR LOGISTICS',
                  style: pw.TextStyle(
                    color: PdfColors.white,
                    fontSize: 11,
                    letterSpacing: 2,
                  ),
                ),
                pw.SizedBox(height: 8),
                pw.Text(
                  'TRUCK HANDOVER REPORT',
                  style: pw.TextStyle(
                    color: PdfColors.white,
                    fontSize: 22,
                    fontWeight: pw.FontWeight.bold,
                  ),
                ),
                pw.SizedBox(height: 8),
                pw.Text(
                  'Trip ID: $tripId',
                  style: const pw.TextStyle(
                    color: PdfColors.white,
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          pw.SizedBox(height: 20),
          pw.Text(
            'REPORT SUMMARY',
            style: pw.TextStyle(fontSize: 14, fontWeight: pw.FontWeight.bold),
          ),
          pw.SizedBox(height: 8),
          _row('Handover Status', status),
          _row('Report Generated', generatedAt.toString().split('.').first),
          _row('Scheduled Arrival', eta),
          pw.SizedBox(height: 20),
          _heading('TRIP INFORMATION'),
          _row('Trip Reference', tripId),
          _row('Origin Hub', origin),
          _row('Destination Hub', destination),
          _row('Cargo Type', cargo),
          pw.SizedBox(height: 20),
          _heading('PARCEL INVENTORY'),
          _row('Total Parcels Loaded', '$totalParcels'),
          _row('Successfully Handed Over', '$handedOverParcels'),
          _row('Failed / Rejected Parcels', '$failedParcels'),
          _row('Remaining Parcels', '$remainingParcels'),
          pw.SizedBox(height: 12),
          pw.Container(
            width: double.infinity,
            padding: const pw.EdgeInsets.all(12),
            decoration: pw.BoxDecoration(
              color: PdfColors.grey100,
              border: pw.Border.all(color: PdfColors.grey400),
              borderRadius: pw.BorderRadius.circular(6),
            ),
            child: pw.Column(
              crossAxisAlignment: pw.CrossAxisAlignment.start,
              children: [
                pw.Text(
                  'Inventory Check',
                  style: pw.TextStyle(fontWeight: pw.FontWeight.bold),
                ),
                pw.SizedBox(height: 6),
                pw.Text(
                  'Handed over + failed + remaining = '
                  '${handedOverParcels + failedParcels + remainingParcels} parcels',
                ),
                pw.SizedBox(height: 4),
                pw.Text(
                  countsMatch
                      ? 'Inventory count matches the total loaded parcels.'
                      : 'WARNING: Parcel counts do not match the total loaded. '
                            'Please verify the inventory.',
                  style: pw.TextStyle(
                    color: countsMatch ? PdfColors.green800 : PdfColors.red,
                    fontSize: 10,
                  ),
                ),
              ],
            ),
          ),
          pw.SizedBox(height: 20),
          _heading('RECEIVING HUB CONFIRMATION'),
          _row('Receiving Hub', destination),
          _row('Received / Verified By', receiverName),
          _row('Handover Confirmed', handoverConfirmed ? 'Yes' : 'No'),
          pw.SizedBox(height: 20),
          _heading('REMARKS / EXCEPTIONS'),
          pw.Container(
            width: double.infinity,
            padding: const pw.EdgeInsets.all(12),
            decoration: pw.BoxDecoration(
              border: pw.Border.all(color: PdfColors.grey400),
              borderRadius: pw.BorderRadius.circular(6),
            ),
            child: pw.Text(
              remarks.trim().isEmpty ? 'No remarks provided.' : remarks,
              style: const pw.TextStyle(fontSize: 10),
            ),
          ),
          pw.SizedBox(height: 28),
          pw.Divider(color: PdfColors.grey400),
          pw.SizedBox(height: 8),
          pw.Text(
            'This report summarizes the truck trip and parcel handover '
            'information provided by the application. Verify the details '
            'against the receiving hub records before treating this report '
            'as an official confirmation.',
            style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700),
          ),
          pw.SizedBox(height: 12),
          pw.Center(
            child: pw.Text(
              'Lumear Logistics | Truck Operations',
              style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700),
            ),
          ),
        ],
      ),
    );

    return pdf.save();
  }

  static pw.Widget _heading(String text) {
    return pw.Container(
      width: double.infinity,
      padding: const pw.EdgeInsets.symmetric(vertical: 8, horizontal: 10),
      decoration: pw.BoxDecoration(
        color: PdfColor.fromHex('#F3E9E1'),
        borderRadius: pw.BorderRadius.circular(4),
      ),
      child: pw.Text(
        text,
        style: pw.TextStyle(
          fontSize: 12,
          fontWeight: pw.FontWeight.bold,
          color: PdfColor.fromHex('#62191C'),
        ),
      ),
    );
  }

  static pw.Widget _row(String label, String value) {
    return pw.Padding(
      padding: const pw.EdgeInsets.symmetric(vertical: 5),
      child: pw.Row(
        crossAxisAlignment: pw.CrossAxisAlignment.start,
        children: [
          pw.SizedBox(
            width: 155,
            child: pw.Text(
              label,
              style: pw.TextStyle(fontSize: 10, fontWeight: pw.FontWeight.bold),
            ),
          ),
          pw.Expanded(
            child: pw.Text(value, style: const pw.TextStyle(fontSize: 10)),
          ),
        ],
      ),
    );
  }
}

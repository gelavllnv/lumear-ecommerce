import 'package:flutter/material.dart';
import 'package:printing/printing.dart';

import 'truck_handover_pdf_service.dart';

class TruckHandoverPreviewScreen extends StatelessWidget {
  const TruckHandoverPreviewScreen({
    super.key,
    required this.tripId,
    required this.origin,
    required this.destination,
    required this.eta,
    required this.cargo,
    required this.totalParcels,
    required this.handedOverParcels,
    required this.failedParcels,
    required this.remainingParcels,
    required this.receiverName,
    required this.remarks,
    required this.handoverConfirmed,
  });

  final String tripId;
  final String origin;
  final String destination;
  final String eta;
  final String cargo;
  final int totalParcels;
  final int handedOverParcels;
  final int failedParcels;
  final int remainingParcels;
  final String receiverName;
  final String remarks;
  final bool handoverConfirmed;

  static const Color _darkMaroon = Color(0xFF62191C);
  static const Color _cream = Color(0xFFF3E9E1);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _cream,
      appBar: AppBar(
        backgroundColor: _darkMaroon,
        foregroundColor: Colors.white,
        title: const Text('Report Preview'),
      ),
      body: PdfPreview(
        pdfFileName: 'truck_handover_report_$tripId.pdf',
        canChangePageFormat: false,
        canChangeOrientation: false,
        canDebug: false,
        allowPrinting: true, // Print button
        allowSharing: true, // Share / Save button
        maxPageWidth: 700,
        scrollViewDecoration: const BoxDecoration(color: _cream),
        loadingWidget: const CircularProgressIndicator(color: _darkMaroon),
        onError: (context, error) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Text(
              'Unable to generate preview: $error',
              textAlign: TextAlign.center,
            ),
          ),
        ),
        build: (format) => TruckHandoverPdfService.buildReportBytes(
          tripId: tripId,
          origin: origin,
          destination: destination,
          eta: eta,
          cargo: cargo,
          totalParcels: totalParcels,
          handedOverParcels: handedOverParcels,
          failedParcels: failedParcels,
          remainingParcels: remainingParcels,
          receiverName: receiverName,
          remarks: remarks,
          handoverConfirmed: handoverConfirmed,
          format: format,
        ),
      ),
    );
  }
}

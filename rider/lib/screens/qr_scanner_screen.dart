import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:audioplayers/audioplayers.dart';

import 'package:flutter_application_1/models/parcel_store.dart';

class QrScannerScreen extends StatefulWidget {
  final Future<void> Function(String qrValue)? onParcelScanned;
  final Future<String?> Function(String qrValue)? onQrScanned;
  final bool createParcel;
  final String scanPurpose;

  const QrScannerScreen({
    super.key,
    this.onParcelScanned,
    this.onQrScanned,
    this.createParcel = true,
    this.scanPurpose = 'payout',
  });

  @override
  State<QrScannerScreen> createState() => _QrScannerScreenState();
}

class _QrScannerScreenState extends State<QrScannerScreen> {
  // ============================================================
  // COLORS
  // ============================================================

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color cream = Color(0xFFE0CFC2);
  static const Color success = Color(0xFF3F7D58);

  // ============================================================
  // SCANNER
  // ============================================================

  late final MobileScannerController _scannerController;

  // ============================================================
  // AUDIO
  // ============================================================

  final AudioPlayer _audioPlayer = AudioPlayer();

  // ============================================================
  // STATE
  // ============================================================

  bool _processing = false;

  String? _lastQrValue;

  DateTime? _lastScanTime;

  OverlayEntry? _notificationEntry;

  // ============================================================
  // INIT
  // ============================================================

  @override
  void initState() {
    super.initState();

    _scannerController = MobileScannerController(
      formats: const [BarcodeFormat.qrCode],
      detectionSpeed: DetectionSpeed.noDuplicates,
      facing: CameraFacing.back,
      autoStart: true,
      torchEnabled: false,
    );
  }

  // ============================================================
  // DISPOSE
  // ============================================================

  @override
  void dispose() {
    _notificationEntry?.remove();
    _notificationEntry = null;

    _audioPlayer.dispose();

    _scannerController.dispose();

    super.dispose();
  }

  // ============================================================
  // SUCCESS SOUND
  // ============================================================

  Future<void> _playScanSuccessSound() async {
    try {
      // Stop previous sound if it is still playing.
      await _audioPlayer.stop();

      // IMPORTANT:
      // If your file is:
      //
      // assets/sounds/scan_success.mp3
      //
      // AssetSource only needs:
      //
      // sounds/scan_success.mp3

      await _audioPlayer.play(
        AssetSource('sounds/notification.mp3'),
        volume: 1.0,
      );
    } catch (error) {
      // Sound errors should NOT stop the parcel from being scanned.
      debugPrint('Scan success sound error: $error');
    }
  }

  // ============================================================
  // QR DETECTION
  // ============================================================

  Future<void> _onDetect(BarcodeCapture capture) async {
    // Prevent multiple detections while processing.
    if (_processing) {
      return;
    }

    // Make sure barcode exists.
    if (capture.barcodes.isEmpty) {
      return;
    }

    final Barcode barcode = capture.barcodes.first;

    final String? rawValue = barcode.rawValue;

    // Invalid QR.
    if (rawValue == null) {
      return;
    }

    final String qrValue = rawValue.trim();

    // Empty QR.
    if (qrValue.isEmpty) {
      return;
    }

    // ==========================================================
    // PREVENT FAST DUPLICATE SCANS
    // ==========================================================

    final DateTime now = DateTime.now();

    if (_lastQrValue == qrValue &&
        _lastScanTime != null &&
        now.difference(_lastScanTime!) < const Duration(seconds: 3)) {
      return;
    }

    _lastQrValue = qrValue;
    _lastScanTime = now;

    if (!mounted) {
      return;
    }

    setState(() {
      _processing = true;
    });

    try {
      // ========================================================
      // STOP CAMERA TEMPORARILY
      // ========================================================

      await _scannerController.stop();

      String successMessage;
      IconData successIcon;

      if (widget.createParcel) {
        final ParcelData parcel = ParcelStore.createParcelFromQr(qrValue);
        debugPrint('QR Scanner created ${parcel.trackingNumber}');
        await _playScanSuccessSound();

        if (widget.onParcelScanned != null) {
          await widget.onParcelScanned!(qrValue);
        }

        successMessage = '${parcel.parcelNumber} added to Pickups.';
        successIcon = Icons.inventory_2_rounded;
      } else {
        final callback = widget.onQrScanned;
        if (callback == null) {
          throw StateError('A QR scan callback is required in generic mode.');
        }

        final String? rejectionMessage = await callback(qrValue);
        if (rejectionMessage != null) {
          if (mounted) {
            _showTopMessage(
              rejectionMessage,
              icon: Icons.error_outline_rounded,
              color: Colors.red,
            );
          }
          return;
        }

        await _playScanSuccessSound();
        successMessage = '${widget.scanPurpose} QR scanned.';
        successIcon = Icons.qr_code_scanner_rounded;
      }

      if (!mounted) {
        return;
      }

      // ========================================================
      // TOP SUCCESS NOTIFICATION
      // ========================================================

      _showTopMessage(successMessage, icon: successIcon, color: success);
    } catch (error) {
      debugPrint('QR Scanner Error: $error');

      if (!mounted) {
        return;
      }

      // ========================================================
      // ERROR NOTIFICATION
      // ========================================================

      _showTopMessage(
        'Unable to process this parcel QR code.',
        icon: Icons.error_outline_rounded,
        color: Colors.red,
      );
    } finally {
      if (!mounted) {
        return;
      }

      setState(() {
        _processing = false;
      });

      // Small delay before scanner becomes active again.
      await Future.delayed(const Duration(milliseconds: 700));

      if (!mounted) {
        return;
      }

      try {
        await _scannerController.start();
      } catch (error) {
        debugPrint('Scanner restart ignored: $error');
      }
    }
  }

  // ============================================================
  // TOP NOTIFICATION
  // ============================================================

  void _showTopMessage(
    String message, {
    required IconData icon,
    required Color color,
  }) {
    // Remove previous notification.
    _notificationEntry?.remove();

    _notificationEntry = null;

    final OverlayState overlay = Overlay.of(context);

    late OverlayEntry entry;

    entry = OverlayEntry(
      builder: (context) {
        return Positioned(
          top: 0,
          left: 0,
          right: 0,
          child: SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
              child: Material(
                color: Colors.transparent,
                child: TweenAnimationBuilder<double>(
                  tween: Tween<double>(begin: 0, end: 1),
                  duration: const Duration(milliseconds: 280),
                  curve: Curves.easeOutCubic,
                  builder: (context, animation, child) {
                    return Transform.translate(
                      offset: Offset(0, -55 * (1 - animation)),
                      child: Opacity(opacity: animation, child: child),
                    );
                  },
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 13,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(17),
                      border: Border(left: BorderSide(color: color, width: 4)),
                      boxShadow: const [
                        BoxShadow(
                          color: Color(0x26000000),
                          blurRadius: 20,
                          offset: Offset(0, 8),
                        ),
                      ],
                    ),
                    child: Row(
                      children: [
                        // ======================================
                        // ICON
                        // ======================================

                        Container(
                          width: 37,
                          height: 37,
                          decoration: BoxDecoration(
                            color: color.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(11),
                          ),
                          child: Icon(icon, color: color, size: 19),
                        ),

                        const SizedBox(width: 11),

                        // ======================================
                        // MESSAGE
                        // ======================================
                        Expanded(
                          child: Text(
                            message,
                            style: const TextStyle(
                              color: darkMaroon,
                              fontSize: 12.5,
                              fontWeight: FontWeight.w600,
                              height: 1.3,
                            ),
                          ),
                        ),

                        // ======================================
                        // CLOSE BUTTON
                        // ======================================
                        GestureDetector(
                          onTap: () {
                            if (entry.mounted) {
                              entry.remove();
                            }

                            if (_notificationEntry == entry) {
                              _notificationEntry = null;
                            }
                          },
                          child: Icon(
                            Icons.close_rounded,
                            size: 17,
                            color: Colors.black.withValues(alpha: 0.30),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );

    _notificationEntry = entry;

    overlay.insert(entry);

    // ==========================================================
    // AUTO HIDE AFTER 2 SECONDS
    // ==========================================================

    Future.delayed(const Duration(seconds: 2), () {
      if (entry.mounted) {
        entry.remove();
      }

      if (_notificationEntry == entry) {
        _notificationEntry = null;
      }
    });
  }

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: cream,
      child: SafeArea(
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(18, 18, 18, 105),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ==================================================
              // TITLE
              // ==================================================

              Text(
                widget.createParcel
                    ? 'Scan Parcel QR'
                    : 'Scan ${_titleCase(widget.scanPurpose)} QR',
                style: const TextStyle(
                  color: darkMaroon,
                  fontSize: 27,
                  fontWeight: FontWeight.bold,
                ),
              ),

              const SizedBox(height: 5),

              Text(
                widget.createParcel
                    ? 'Position the parcel QR code inside the frame.'
                    : 'Position the ${widget.scanPurpose} QR code inside the frame.',
                style: const TextStyle(color: brownBeige, fontSize: 13),
              ),

              const SizedBox(height: 20),

              // ==================================================
              // SCANNER CARD
              // ==================================================
              Container(
                clipBehavior: Clip.antiAlias,
                decoration: BoxDecoration(
                  color: cream,
                  borderRadius: BorderRadius.circular(25),
                  border: Border.all(color: darkMaroon.withValues(alpha: 0.10)),
                  boxShadow: [
                    BoxShadow(
                      color: darkMaroon.withValues(alpha: 0.10),
                      blurRadius: 18,
                      offset: const Offset(0, 8),
                    ),
                  ],
                ),
                child: Column(
                  children: [
                    AspectRatio(
                      aspectRatio: 0.90,
                      child: Stack(
                        fit: StackFit.expand,
                        children: [
                          // ======================================
                          // CAMERA
                          // ======================================

                          MobileScanner(
                            controller: _scannerController,
                            onDetect: _onDetect,
                            fit: BoxFit.cover,
                          ),

                          // ======================================
                          // DARK OVERLAY
                          // ======================================
                          IgnorePointer(
                            child: CustomPaint(
                              painter: ScannerOverlayPainter(),
                            ),
                          ),

                          // ======================================
                          // QR FRAME
                          // ======================================
                          Center(
                            child: SizedBox(
                              width: 235,
                              height: 235,
                              child: const Stack(
                                children: [
                                  _ScannerCorner(alignment: Alignment.topLeft),
                                  _ScannerCorner(alignment: Alignment.topRight),
                                  _ScannerCorner(
                                    alignment: Alignment.bottomLeft,
                                  ),
                                  _ScannerCorner(
                                    alignment: Alignment.bottomRight,
                                  ),
                                ],
                              ),
                            ),
                          ),

                          // ======================================
                          // PROCESSING
                          // ======================================
                          if (_processing)
                            Container(
                              color: Colors.black.withValues(alpha: 0.55),
                              child: Center(
                                child: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    const CircularProgressIndicator(
                                      color: Colors.white,
                                    ),

                                    const SizedBox(height: 15),

                                    Text(
                                      widget.createParcel
                                          ? 'Processing parcel...'
                                          : 'Checking ${widget.scanPurpose}...',
                                      style: TextStyle(
                                        color: Colors.white,
                                        fontSize: 13,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),

                    // ==================================================
                    // FLASH BUTTON
                    // ==================================================
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(14),
                      child: ValueListenableBuilder<MobileScannerState>(
                        valueListenable: _scannerController,
                        builder: (context, state, child) {
                          final bool torchOn =
                              state.torchState == TorchState.on;

                          return ElevatedButton.icon(
                            onPressed: _processing
                                ? null
                                : () async {
                                    await _scannerController.toggleTorch();
                                  },
                            icon: Icon(
                              torchOn
                                  ? Icons.flash_on_rounded
                                  : Icons.flash_off_rounded,
                            ),
                            label: Text(
                              torchOn ? 'Turn Flash Off' : 'Turn Flash On',
                            ),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: darkMaroon,
                              foregroundColor: cream,
                              disabledBackgroundColor: darkMaroon.withValues(
                                alpha: 0.5,
                              ),
                              disabledForegroundColor: cream.withValues(
                                alpha: 0.5,
                              ),
                              elevation: 0,
                              minimumSize: const Size(double.infinity, 48),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 16),

              // ==================================================
              // INFO CARD
              // ==================================================
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.65),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      Icons.info_outline_rounded,
                      color: darkMaroon,
                      size: 20,
                    ),

                    SizedBox(width: 10),

                    Expanded(
                      child: Text(
                        widget.createParcel
                            ? 'After scanning, the parcel will be processed and added to your Pickups.'
                            : widget.scanPurpose == 'payout'
                            ? 'Scanning does not mark a payout as paid. Verify the claim details before confirming payment.'
                            : 'The QR contents are checked by the current workflow. Scanning does not create or mark a parcel as collected.',
                        style: const TextStyle(
                          color: brownBeige,
                          fontSize: 12,
                          height: 1.4,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _titleCase(String value) {
    if (value.isEmpty) return value;
    return '${value[0].toUpperCase()}${value.substring(1)}';
  }
}

// ============================================================
// SCANNER CORNERS
// ============================================================

class _ScannerCorner extends StatelessWidget {
  final Alignment alignment;

  const _ScannerCorner({required this.alignment});

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: alignment,
      child: Container(
        width: 28,
        height: 28,
        decoration: BoxDecoration(
          border: Border(
            top: alignment.y < 0
                ? const BorderSide(color: Colors.white, width: 4)
                : BorderSide.none,

            bottom: alignment.y > 0
                ? const BorderSide(color: Colors.white, width: 4)
                : BorderSide.none,

            left: alignment.x < 0
                ? const BorderSide(color: Colors.white, width: 4)
                : BorderSide.none,

            right: alignment.x > 0
                ? const BorderSide(color: Colors.white, width: 4)
                : BorderSide.none,
          ),
        ),
      ),
    );
  }
}

// ============================================================
// DARK SCANNER OVERLAY
// ============================================================

class ScannerOverlayPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    const double scannerSize = 235;

    final Rect fullRect = Offset.zero & size;

    final Rect scannerRect = Rect.fromCenter(
      center: Offset(size.width / 2, size.height / 2),
      width: scannerSize,
      height: scannerSize,
    );

    final RRect scannerRRect = RRect.fromRectAndRadius(
      scannerRect,
      const Radius.circular(26),
    );

    final Path backgroundPath = Path()..addRect(fullRect);

    final Path scannerPath = Path()..addRRect(scannerRRect);

    final Path overlayPath = Path.combine(
      PathOperation.difference,
      backgroundPath,
      scannerPath,
    );

    final Paint paint = Paint()..color = Colors.black.withValues(alpha: 0.30);

    canvas.drawPath(overlayPath, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) {
    return false;
  }
}

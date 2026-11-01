import 'package:flutter/material.dart';

class ParcelDeliveryScreen extends StatefulWidget {
  final String parcelNumber;
  final String trackingNumber;
  final String recipient;
  final String phone;
  final String address;
  final double codAmount;
  final double riderFee;

  final double customerLatitude;
  final double customerLongitude;

  const ParcelDeliveryScreen({
    super.key,
    required this.parcelNumber,
    required this.trackingNumber,
    required this.recipient,
    required this.phone,
    required this.address,
    required this.codAmount,
    required this.riderFee,
    required this.customerLatitude,
    required this.customerLongitude,
  });

  @override
  State<ParcelDeliveryScreen> createState() => _ParcelDeliveryScreenState();
}

class _ParcelDeliveryScreenState extends State<ParcelDeliveryScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);

  String _status = 'Out for Delivery';
  bool _isConfirmingDelivery = false;

  // ============================================
  // ARRIVED AT DESTINATION
  // ============================================
  void _arrivedAtDestination() {
    setState(() {
      _status = 'Arrived at Destination';
    });

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('You have arrived at the customer destination.'),
      ),
    );
  }

  // ============================================
  // CONFIRM DELIVERY
  // ============================================
  void _confirmDelivery() {
    if (_isConfirmingDelivery || _status == 'Delivered') return;

    _isConfirmingDelivery = true;
    bool confirmationSubmitted = false;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return AlertDialog(
          backgroundColor: cream,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          title: const Row(
            children: [
              Icon(Icons.inventory_2_outlined, color: darkMaroon),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Confirm Delivery',
                  style: TextStyle(
                    color: darkMaroon,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          content: Text(
            'Confirm that parcel ${widget.parcelNumber} was received by ${widget.recipient}?',
            style: const TextStyle(color: brownBeige),
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(dialogContext);
              },
              child: const Text('Cancel', style: TextStyle(color: brownBeige)),
            ),
            ElevatedButton(
              onPressed: () {
                if (confirmationSubmitted) return;
                confirmationSubmitted = true;
                Navigator.pop(dialogContext);
                _showSuccessDialog();
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: darkMaroon,
                foregroundColor: Colors.white,
              ),
              child: const Text('Confirm'),
            ),
          ],
        );
      },
    ).whenComplete(() {
      _isConfirmingDelivery = false;
    });
  }

  // ============================================
  // DELIVERY SUCCESS
  // ============================================
  void _showSuccessDialog() {
    setState(() {
      _status = 'Delivered';
    });

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return AlertDialog(
          backgroundColor: cream,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(22),
          ),
          title: const Column(
            children: [
              Icon(Icons.check_circle, color: Colors.green, size: 65),
              SizedBox(height: 10),
              Text(
                'Parcel Delivered!',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: darkMaroon,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                widget.parcelNumber,
                style: const TextStyle(
                  fontSize: 17,
                  fontWeight: FontWeight.bold,
                  color: darkMaroon,
                ),
              ),
              const SizedBox(height: 6),
              Text(widget.recipient, style: const TextStyle(color: brownBeige)),
              const SizedBox(height: 18),
              const Text(
                'Rider Fee Earned',
                style: TextStyle(fontSize: 12, color: brownBeige),
              ),
              Text(
                '₱${widget.riderFee.toStringAsFixed(0)}',
                style: const TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.bold,
                  color: darkMaroon,
                ),
              ),
            ],
          ),
          actions: [
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.pop(dialogContext);

                  // RETURN TRUE TO DELIVERS SCREEN
                  Navigator.pop(context, true);
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: darkMaroon,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
                child: const Text(
                  'Done',
                  style: TextStyle(fontWeight: FontWeight.bold),
                ),
              ),
            ),
          ],
        );
      },
    );
  }

  // ============================================
  // BUILD
  // ============================================
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,

      appBar: AppBar(
        backgroundColor: cream,
        elevation: 0,
        iconTheme: const IconThemeData(color: darkMaroon),
        title: Text(
          'Parcel ${widget.parcelNumber}',
          style: const TextStyle(
            color: darkMaroon,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),

      body: Column(
        children: [
          // ============================================
          // LOCATION SUMMARY
          // ============================================
          _EntranceAnimation(
            delay: 30,
            child: Container(
              width: double.infinity,
              margin: const EdgeInsets.fromLTRB(18, 12, 18, 0),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.72),
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: lightBeige),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const CircleAvatar(
                    backgroundColor: lightBeige,
                    child: Icon(Icons.location_on_outlined, color: darkMaroon),
                  ),

                  const SizedBox(width: 12),

                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Delivery Location',
                          style: TextStyle(
                            color: darkMaroon,
                            fontWeight: FontWeight.bold,
                            fontSize: 14,
                          ),
                        ),

                        const SizedBox(height: 4),

                        Text(
                          widget.address,
                          style: const TextStyle(
                            color: brownBeige,
                            fontSize: 12,
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),

          const SizedBox(height: 12),

          // ============================================
          // DELIVERY DETAILS
          // ============================================
          Expanded(
            child: Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(18, 18, 18, 10),
              decoration: const BoxDecoration(
                color: cream,
                borderRadius: BorderRadius.vertical(top: Radius.circular(25)),
              ),
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // ============================================
                    // STATUS CARD
                    // ============================================
                    _EntranceAnimation(
                      delay: 90,
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: lightBeige,
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Row(
                          children: [
                            const CircleAvatar(
                              backgroundColor: darkMaroon,
                              child: Icon(
                                Icons.local_shipping_outlined,
                                color: Colors.white,
                              ),
                            ),

                            const SizedBox(width: 12),

                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text(
                                    'Delivery Status',
                                    style: TextStyle(
                                      fontSize: 11,
                                      color: brownBeige,
                                    ),
                                  ),

                                  const SizedBox(height: 2),

                                  Text(
                                    _status,
                                    style: TextStyle(
                                      fontSize: 17,
                                      fontWeight: FontWeight.bold,
                                      color: _status == 'Delivered'
                                          ? Colors.green
                                          : darkMaroon,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                    const SizedBox(height: 16),

                    // ============================================
                    // RECIPIENT
                    // ============================================
                    _EntranceAnimation(
                      delay: 140,
                      child: _infoCard(
                        icon: Icons.person_outline,
                        label: 'Recipient',
                        value: widget.recipient,
                      ),
                    ),

                    const SizedBox(height: 10),

                    // ============================================
                    // PHONE
                    // ============================================
                    _EntranceAnimation(
                      delay: 180,
                      child: _infoCard(
                        icon: Icons.phone_outlined,
                        label: 'Contact Number',
                        value: widget.phone,
                      ),
                    ),

                    const SizedBox(height: 10),

                    // ============================================
                    // ADDRESS
                    // ============================================
                    _EntranceAnimation(
                      delay: 220,
                      child: _infoCard(
                        icon: Icons.location_on_outlined,
                        label: 'Delivery Address',
                        value: widget.address,
                      ),
                    ),

                    const SizedBox(height: 10),

                    // ============================================
                    // TRACKING
                    // ============================================
                    _EntranceAnimation(
                      delay: 260,
                      child: _infoCard(
                        icon: Icons.qr_code_2,
                        label: 'Tracking Number',
                        value: widget.trackingNumber,
                      ),
                    ),

                    const SizedBox(height: 10),

                    // ============================================
                    // PAYMENT
                    // ============================================
                    _EntranceAnimation(
                      delay: 300,
                      child: _infoCard(
                        icon: Icons.payments_outlined,
                        label: 'Payment',
                        value: widget.codAmount > 0
                            ? 'COD - Collect ₱${widget.codAmount.toStringAsFixed(0)}'
                            : 'Paid - No collection',
                      ),
                    ),

                    const SizedBox(height: 10),

                    // ============================================
                    // RIDER FEE
                    // ============================================
                    _EntranceAnimation(
                      delay: 340,
                      child: _infoCard(
                        icon: Icons.account_balance_wallet_outlined,
                        label: 'Rider Fee',
                        value: '₱${widget.riderFee.toStringAsFixed(0)}',
                      ),
                    ),

                    const SizedBox(height: 20),

                    // ============================================
                    // MAIN ACTION BUTTON
                    // ============================================
                    if (_status != 'Delivered')
                      _EntranceAnimation(
                        delay: 390,
                        child: SizedBox(
                          width: double.infinity,
                          height: 52,
                          child: ElevatedButton.icon(
                            onPressed: _status == 'Out for Delivery'
                                ? _arrivedAtDestination
                                : _confirmDelivery,
                            icon: Icon(
                              _status == 'Out for Delivery'
                                  ? Icons.location_on_outlined
                                  : Icons.check_circle_outline,
                            ),
                            label: Text(
                              _status == 'Out for Delivery'
                                  ? 'Arrived at Destination'
                                  : 'Confirm Delivery',
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: darkMaroon,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(15),
                              ),
                            ),
                          ),
                        ),
                      ),

                    const SizedBox(height: 20),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ============================================
  // INFO CARD
  // ============================================
  Widget _infoCard({
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.72),
        borderRadius: BorderRadius.circular(15),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: darkMaroon, size: 23),

          const SizedBox(width: 12),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(fontSize: 10, color: brownBeige),
                ),

                const SizedBox(height: 3),

                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.bold,
                    color: darkMaroon,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ============================================================
// SUBTLE ENTRANCE ANIMATION
// ============================================================

class _EntranceAnimation extends StatefulWidget {
  final Widget child;
  final int delay;

  const _EntranceAnimation({required this.child, required this.delay});

  @override
  State<_EntranceAnimation> createState() => _EntranceAnimationState();
}

class _EntranceAnimationState extends State<_EntranceAnimation> {
  bool _show = false;

  @override
  void initState() {
    super.initState();

    Future.delayed(Duration(milliseconds: widget.delay), () {
      if (!mounted) return;

      setState(() {
        _show = true;
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedOpacity(
      opacity: _show ? 1 : 0,
      duration: const Duration(milliseconds: 380),
      curve: Curves.easeOutCubic,
      child: AnimatedSlide(
        offset: _show ? Offset.zero : const Offset(0, 0.025),
        duration: const Duration(milliseconds: 380),
        curve: Curves.easeOutCubic,
        child: AnimatedScale(
          scale: _show ? 1 : 0.985,
          duration: const Duration(milliseconds: 380),
          curve: Curves.easeOutCubic,
          child: widget.child,
        ),
      ),
    );
  }
}

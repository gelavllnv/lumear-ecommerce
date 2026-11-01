import 'package:flutter/material.dart';

class SortingCenterReceiptScreen extends StatelessWidget {
  final String requestId;
  final String sellerName;
  final String sellerPhone;
  final String pickupAddress;
  final String sortingCenter;
  final List<String> trackingNumbers;
  final DateTime deliveredAt;

  const SortingCenterReceiptScreen({
    super.key,
    required this.requestId,
    required this.sellerName,
    required this.sellerPhone,
    required this.pickupAddress,
    required this.sortingCenter,
    required this.trackingNumbers,
    required this.deliveredAt,
  });

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);
  static const Color success = Color(0xFF2E7D32);

  String _formatTime(DateTime time) {
    int hour = time.hour;
    final String period = hour >= 12 ? 'PM' : 'AM';

    if (hour == 0) {
      hour = 12;
    } else if (hour > 12) {
      hour -= 12;
    }

    final String minute = time.minute.toString().padLeft(2, '0');
    return '$hour:$minute $period';
  }

  String _formatDate(DateTime time) {
    const List<String> months = [
      'January',
      'February',
      'March',
      'April',
      'May',
      'June',
      'July',
      'August',
      'September',
      'October',
      'November',
      'December',
    ];

    return '${months[time.month - 1]} ${time.day}, ${time.year}';
  }

  Widget _dashedDivider() {
    return LayoutBuilder(
      builder: (context, constraints) {
        const double dashWidth = 6;
        const double dashGap = 4;
        final int count = (constraints.maxWidth / (dashWidth + dashGap))
            .floor();

        return Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: List.generate(
            count,
            (_) => Container(width: dashWidth, height: 1.5, color: lightBeige),
          ),
        );
      },
    );
  }

  Widget _row(String label, String value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: const TextStyle(color: brownBeige, fontSize: 12),
            ),
          ),
          Expanded(
            child: Text(
              value.trim().isEmpty ? 'Not provided' : value,
              textAlign: TextAlign.right,
              style: TextStyle(
                color: darkMaroon,
                fontSize: 13,
                fontWeight: bold ? FontWeight.w800 : FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          Navigator.of(context).popUntil((route) => route.isFirst);
        }
      },
      child: Scaffold(
        backgroundColor: cream,
        appBar: AppBar(
          backgroundColor: cream,
          foregroundColor: darkMaroon,
          automaticallyImplyLeading: false,
          title: const Text(
            'Handoff Receipt',
            style: TextStyle(fontWeight: FontWeight.bold),
          ),
        ),
        body: SafeArea(
          child: Column(
            children: [
              Expanded(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(18, 8, 18, 18),
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(22),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.06),
                          blurRadius: 14,
                          offset: const Offset(0, 5),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Header
                        Center(
                          child: Column(
                            children: [
                              Container(
                                width: 70,
                                height: 70,
                                decoration: BoxDecoration(
                                  color: success.withValues(alpha: 0.12),
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(
                                  Icons.check_circle_rounded,
                                  color: success,
                                  size: 48,
                                ),
                              ),
                              const SizedBox(height: 14),
                              const Text(
                                'Delivered to Sorting Center',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  color: darkMaroon,
                                  fontSize: 20,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                '${_formatTime(deliveredAt)} • ${_formatDate(deliveredAt)}',
                                style: const TextStyle(
                                  color: brownBeige,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),

                        const SizedBox(height: 20),
                        _dashedDivider(),
                        const SizedBox(height: 14),

                        // Reference
                        _row('Pickup Request', requestId, bold: true),
                        _row('Sorting Center', sortingCenter),

                        const SizedBox(height: 8),
                        _dashedDivider(),
                        const SizedBox(height: 14),

                        // Seller
                        const Text(
                          'PICKED UP FROM',
                          style: TextStyle(
                            color: maroon,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.8,
                          ),
                        ),
                        const SizedBox(height: 6),
                        _row('Seller', sellerName),
                        _row('Contact', sellerPhone),
                        _row('Address', pickupAddress),

                        const SizedBox(height: 8),
                        _dashedDivider(),
                        const SizedBox(height: 14),

                        // Parcels
                        const Text(
                          'PARCELS HANDED OVER',
                          style: TextStyle(
                            color: maroon,
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.8,
                          ),
                        ),
                        const SizedBox(height: 10),

                        if (trackingNumbers.isEmpty)
                          const Text(
                            'No parcels were listed for this pickup.',
                            style: TextStyle(color: brownBeige, fontSize: 12),
                          ),

                        ...trackingNumbers.map(
                          (tracking) => Padding(
                            padding: const EdgeInsets.symmetric(vertical: 5),
                            child: Row(
                              children: [
                                const Icon(
                                  Icons.inventory_2_outlined,
                                  color: darkMaroon,
                                  size: 18,
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Text(
                                    tracking,
                                    style: const TextStyle(
                                      color: darkMaroon,
                                      fontSize: 13,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ),
                                const Icon(
                                  Icons.check_circle_rounded,
                                  color: success,
                                  size: 18,
                                ),
                              ],
                            ),
                          ),
                        ),

                        const SizedBox(height: 10),
                        _dashedDivider(),
                        const SizedBox(height: 10),

                        _row(
                          'Total Parcels',
                          '${trackingNumbers.length}',
                          bold: true,
                        ),

                        const SizedBox(height: 14),

                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.symmetric(
                            horizontal: 14,
                            vertical: 12,
                          ),
                          decoration: BoxDecoration(
                            color: success.withValues(alpha: 0.08),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Row(
                            children: [
                              Icon(
                                Icons.verified_rounded,
                                color: success,
                                size: 20,
                              ),
                              SizedBox(width: 9),
                              Expanded(
                                child: Text(
                                  'Status: Delivered to Sorting Center',
                                  style: TextStyle(
                                    color: success,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w800,
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
              ),

              // Done button
              Container(
                padding: const EdgeInsets.fromLTRB(18, 10, 18, 16),
                child: SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton.icon(
                    onPressed: () {
                      Navigator.of(context).popUntil((route) => route.isFirst);
                    },
                    icon: const Icon(Icons.done_all_rounded),
                    label: const Text(
                      'Done',
                      style: TextStyle(fontWeight: FontWeight.w800),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: darkMaroon,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

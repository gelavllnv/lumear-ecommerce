import 'package:flutter/material.dart';
import 'package:flutter_application_1/models/payout_claim.dart';
import 'package:flutter_application_1/services/rider_earnings_service.dart';

/// Rider wallet.
///
/// Salary rule per successful delivery:
/// Shipping fee = PHP 50
/// Logistics share = 20% = PHP 10
/// Rider earnings = 80% = PHP 40
///
/// Lumear Logistics handles the actual payout.
class EarningsScreen extends StatelessWidget {
  const EarningsScreen({super.key});

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color beige = Color(0xFF9E7161);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color border = Color(0xFFEDE1D8);
  static const Color muted = Color(0xFF8B6F63);

  // Shipping fee and allocation.
  static const double shippingFee = 50.00;
  static const double logisticsRate = 0.20;
  static const double riderRate = 0.80;

  static const double logisticsShare = shippingFee * logisticsRate;
  static const double riderEarning = shippingFee * riderRate;

  String _formatPeso(double amount) {
    return '₱${amount.toStringAsFixed(2)}';
  }

  @override
  Widget build(BuildContext context) {
    final RiderEarningsService service = RiderEarningsService.instance;

    return Scaffold(
      backgroundColor: cream,
      body: SafeArea(
        child: ValueListenableBuilder<List<EarningTransaction>>(
          valueListenable: service.transactions,
          builder: (context, transactions, _) {
            return SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(20, 18, 20, 125),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'My Wallet',
                    style: TextStyle(
                      color: darkMaroon,
                      fontSize: 28,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    '₱40 earnings for every successful delivery.',
                    style: TextStyle(color: muted, fontSize: 13),
                  ),
                  const SizedBox(height: 22),

                  _buildWalletCard(service.totalEarnings),
                  const SizedBox(height: 14),

                  Row(
                    children: [
                      Expanded(
                        child: _miniStat(
                          icon: Icons.local_shipping_rounded,
                          color: Colors.green.shade700,
                          label: 'Successful deliveries',
                          value: '${service.successfulDeliveryCount}',
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _miniStat(
                          icon: Icons.payments_rounded,
                          color: maroon,
                          label: 'Earnings per delivery',
                          value: _formatPeso(riderEarning),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 14),
                  _buildShippingBreakdown(),

                  const SizedBox(height: 14),
                  _buildPayoutInfo(),

                  const SizedBox(height: 24),
                  _sectionTitle('Wallet Credits'),
                  const SizedBox(height: 10),

                  if (transactions.isEmpty)
                    _emptyCard(
                      'No credits yet',
                      'Your ₱40 earnings will appear here after '
                          'each successful delivery.',
                    )
                  else
                    ...transactions.map(_transactionTile),

                  const SizedBox(height: 12),
                  const Center(
                    child: Text(
                      'Rider earnings are credited after successful '
                      'delivery. Lumear Logistics handles the payout.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: beige, fontSize: 11, height: 1.5),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  // WALLET CARD

  Widget _buildWalletCard(double balance) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [darkMaroon, maroon],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: darkMaroon.withValues(alpha: 0.2),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.account_balance_wallet_outlined,
                color: Colors.white70,
                size: 20,
              ),
              const SizedBox(width: 8),
              const Expanded(
                child: Text(
                  'WALLET BALANCE',
                  style: TextStyle(
                    color: Colors.white70,
                    fontSize: 12,
                    letterSpacing: 1,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 5,
                ),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: const Text(
                  'Demo Mode',
                  style: TextStyle(color: Colors.white, fontSize: 10),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            _formatPeso(balance),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 34,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Credited by Lumear Logistics',
            style: TextStyle(color: cream, fontSize: 12),
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Row(
              children: [
                Icon(Icons.check_circle_outline, color: Colors.white, size: 18),
                SizedBox(width: 8),
                Expanded(
                  child: Text(
                    '₱40 credited per successful delivery',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // SHIPPING FEE BREAKDOWN

  Widget _buildShippingBreakdown() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Shipping Fee Breakdown',
            style: TextStyle(
              color: darkMaroon,
              fontSize: 15,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Allocation for every successful delivery',
            style: TextStyle(color: muted, fontSize: 11),
          ),
          const SizedBox(height: 16),

          _breakdownRow(
            label: 'Total shipping fee',
            amount: shippingFee,
            bold: true,
          ),
          const SizedBox(height: 12),

          _breakdownRow(
            label: 'Lumear Logistics (20%)',
            amount: logisticsShare,
          ),
          const SizedBox(height: 12),

          _breakdownRow(
            label: 'Rider earnings (80%)',
            amount: riderEarning,
            amountColor: Colors.green.shade700,
            bold: true,
          ),

          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: const Row(
              children: [
                Expanded(
                  flex: 20,
                  child: SizedBox(height: 8, child: ColoredBox(color: maroon)),
                ),
                Expanded(
                  flex: 80,
                  child: SizedBox(
                    height: 8,
                    child: ColoredBox(color: Color(0xFF4C9A68)),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Logistics receives ₱10. The rider receives ₱40.',
            style: TextStyle(color: muted, fontSize: 11, height: 1.4),
          ),
        ],
      ),
    );
  }

  Widget _breakdownRow({
    required String label,
    required double amount,
    Color? amountColor,
    bool bold = false,
  }) {
    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              color: darkMaroon,
              fontSize: 12,
              fontWeight: bold ? FontWeight.w800 : FontWeight.w500,
            ),
          ),
        ),
        Text(
          _formatPeso(amount),
          style: TextStyle(
            color: amountColor ?? darkMaroon,
            fontSize: 13,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }

  // PAYOUT INFORMATION

  Widget _buildPayoutInfo() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: cream,
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.payments_outlined, size: 19, color: maroon),
          ),
          const SizedBox(width: 12),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Payout handled by Lumear Logistics',
                  style: TextStyle(
                    color: darkMaroon,
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                SizedBox(height: 3),
                Text(
                  'Your wallet records your earnings. Logistics '
                  'handles the actual payout. No withdrawal button '
                  'is needed in this demo.',
                  style: TextStyle(
                    color: Colors.black54,
                    fontSize: 11.5,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // SHARED WIDGETS

  Widget _sectionTitle(String title) {
    return Text(
      title,
      style: const TextStyle(
        color: darkMaroon,
        fontSize: 17,
        fontWeight: FontWeight.w800,
      ),
    );
  }

  Widget _miniStat({
    required IconData icon,
    required Color color,
    required String label,
    required String value,
  }) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, size: 19, color: color),
          ),
          const SizedBox(height: 10),
          Text(
            value,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(color: beige, fontSize: 11)),
        ],
      ),
    );
  }

  Widget _transactionTile(EarningTransaction transaction) {
    final Color accent = Colors.green.shade700;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: border),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(Icons.south_west_rounded, color: accent),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Delivery: ${transaction.recipient}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  transaction.trackingNumber,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.black54, fontSize: 11),
                ),
                const SizedBox(height: 3),
                const Text(
                  '80% rider share • Successful delivery',
                  style: TextStyle(color: beige, fontSize: 11),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Text(
            '+${_formatPeso(transaction.amount)}',
            style: TextStyle(
              color: accent,
              fontSize: 15,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  Widget _emptyCard(String title, String message) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: border),
      ),
      child: Column(
        children: [
          const Icon(
            Icons.account_balance_wallet_outlined,
            size: 36,
            color: beige,
          ),
          const SizedBox(height: 8),
          Text(
            title,
            style: const TextStyle(
              color: darkMaroon,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: beige, fontSize: 12, height: 1.4),
          ),
        ],
      ),
    );
  }
}

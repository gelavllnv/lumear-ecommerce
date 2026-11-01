import 'package:flutter/material.dart';

import '../../models/van_pickup_request.dart';

/// Rider wallet.
///
/// Lumear Logistics (not the seller) pays the rider. Every successful hub
/// handover credits the rider's wallet, and Logistics handles the actual
/// payout/salary outside the app, so there is no withdraw button here.
class VanEarningsScreen extends StatelessWidget {
  final List<VanPickupRequest> pickups;

  const VanEarningsScreen({super.key, required this.pickups});

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color beige = Color(0xFF9E7161);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color border = Color(0xFFEDE1D8);
  static const Color pendingColor = Color(0xFF815C13);

  // Wallet credit per pickup, set by Lumear Logistics.
  // Currently 10% of the pickup total. Change this (or switch to a flat
  // per-parcel rate in _creditFor) once Logistics confirms the final rate.
  static const double creditRate = 0.10;

  // A wallet credit is released only after a successful hub handover.
  bool _isCredited(VanPickupRequest pickup) {
    return pickup.status == VanPickupStatus.handedOver;
  }

  double _creditFor(VanPickupRequest pickup) {
    return pickup.totalAmount * creditRate;
  }

  List<VanPickupRequest> get _credited {
    final result = pickups.where(_isCredited).toList();

    result.sort((a, b) {
      final dateA = a.handedOverAt ?? DateTime.fromMillisecondsSinceEpoch(0);
      final dateB = b.handedOverAt ?? DateTime.fromMillisecondsSinceEpoch(0);

      return dateB.compareTo(dateA);
    });

    return result;
  }

  List<VanPickupRequest> get _pending {
    return pickups.where((pickup) => !_isCredited(pickup)).toList();
  }

  double get _walletBalance {
    return _credited.fold(0.0, (total, pickup) => total + _creditFor(pickup));
  }

  double get _totalPending {
    return _pending.fold(0.0, (total, pickup) => total + _creditFor(pickup));
  }

  String _peso(double amount) => '₱${amount.toStringAsFixed(2)}';

  String _formatDate(DateTime? date) {
    if (date == null) return '';

    final local = date.toLocal();
    final day = local.day.toString().padLeft(2, '0');
    final month = local.month.toString().padLeft(2, '0');

    return '$day/$month/${local.year}';
  }

  @override
  Widget build(BuildContext context) {
    final credited = _credited;
    final pending = _pending;

    return Scaffold(
      backgroundColor: const Color(0xFFFFFAF6),
      appBar: AppBar(
        automaticallyImplyLeading: false,
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          'My Wallet',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _buildWalletCard(),
          const SizedBox(height: 14),

          Row(
            children: [
              Expanded(
                child: _miniStat(
                  icon: Icons.check_circle_outline,
                  color: Colors.green.shade700,
                  label: 'Completed handovers',
                  value: '${credited.length}',
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _miniStat(
                  icon: Icons.hourglass_top_rounded,
                  color: pendingColor,
                  label: 'Incoming credits',
                  value: _peso(_totalPending),
                ),
              ),
            ],
          ),

          const SizedBox(height: 14),
          _buildPayoutInfo(),

          const SizedBox(height: 22),
          _sectionTitle('Wallet Credits'),
          const SizedBox(height: 10),

          if (credited.isEmpty)
            _emptyCard(
              'No credits yet',
              'Lumear Logistics will credit your wallet after each pickup '
                  'is successfully handed over to the hub.',
            )
          else
            ...credited.map(
              (pickup) => _transactionTile(
                pickup,
                completed: true,
                subtitle: pickup.handedOverAt == null
                    ? 'Credited by Lumear Logistics'
                    : 'Credited by Lumear Logistics • '
                          '${_formatDate(pickup.handedOverAt)}',
              ),
            ),

          if (pending.isNotEmpty) ...[
            const SizedBox(height: 22),
            _sectionTitle('Incoming Credits'),
            const SizedBox(height: 10),
            ...pending.map(
              (pickup) => _transactionTile(
                pickup,
                completed: false,
                subtitle: '${pickup.statusLabel} • Not yet credited',
              ),
            ),
          ],

          const SizedBox(height: 12),

          const Text(
            'Credits are released by Lumear Logistics after a successful '
            'hub handover. Incoming credits are estimates until the '
            'handover is confirmed.',
            textAlign: TextAlign.center,
            style: TextStyle(color: beige, fontSize: 11, height: 1.5),
          ),

          const SizedBox(height: 12),
        ],
      ),
    );
  }

  // WALLET CARD

  Widget _buildWalletCard() {
    return Container(
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
            _peso(_walletBalance),
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
        ],
      ),
    );
  }

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
                  'Your wallet shows what you have earned. Logistics '
                  'takes care of paying your salary, so no withdrawal '
                  'is needed.',
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

  Widget _transactionTile(
    VanPickupRequest pickup, {
    required bool completed,
    required String subtitle,
  }) {
    final accent = completed ? Colors.green.shade700 : pendingColor;
    final credit = _creditFor(pickup);

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
            child: Icon(
              completed ? Icons.south_west_rounded : Icons.schedule_rounded,
              color: accent,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Pickup: ${pickup.sellerName}',
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
                  '${pickup.id} • ${pickup.parcelCount} '
                  '${pickup.parcelCount == 1 ? 'parcel' : 'parcels'}',
                  style: const TextStyle(color: Colors.black54, fontSize: 11),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: beige, fontSize: 11),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Text(
            '${completed ? '+' : ''}${_peso(credit)}',
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

import 'package:flutter/material.dart';

class AssignmentScreen extends StatelessWidget {
  const AssignmentScreen({super.key});

  // ============================================================
  // COLORS
  // ============================================================

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color textMuted = Color(0xFF8B6F63);

  static const Color success = Color(0xFF2E7D32);

  // ============================================================
  // DESIGN TOKENS
  // ============================================================

  static const double radiusSm = 14;
  static const double radiusMd = 18;
  static const double radiusLg = 24;
  static const double radiusXl = 28;

  // Two-layer shadow: a tight, dark contact shadow plus a soft, wide
  // ambient shadow. This is what makes cards read as "designed" rather
  // than the default single flat drop-shadow.
  static List<BoxShadow> get shadowSoft => [
    BoxShadow(
      color: darkMaroon.withValues(alpha: 0.05),
      blurRadius: 4,
      offset: const Offset(0, 1),
    ),
    BoxShadow(
      color: darkMaroon.withValues(alpha: 0.07),
      blurRadius: 24,
      offset: const Offset(0, 10),
    ),
  ];

  static BoxDecoration get cardDecoration => BoxDecoration(
    color: surface,
    borderRadius: BorderRadius.circular(radiusLg),
    border: Border.all(color: darkMaroon.withValues(alpha: 0.06)),
    boxShadow: shadowSoft,
  );

  // ============================================================
  // DEMO ASSIGNMENT DATA
  // Later: replace these with Laravel API data.
  // ============================================================

  static const String assignedHub = 'Victoria Hub';
  static const String assignedArea = 'Brgy. Masapang';
  static const String assignedShift = '8:00 AM - 5:00 PM';
  static const int assignedParcels = 12;
  static const int deliveredParcels = 0;

  // ============================================================
  // BUILD
  // ============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      body: SafeArea(
        child: SingleChildScrollView(
          // Space for floating navbar
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 115),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ==================================================
              // HEADER
              // ==================================================
              _EntranceAnimation(
                delay: 30,
                child: Row(
                  children: [
                    Container(
                      decoration: BoxDecoration(
                        color: surface,
                        borderRadius: BorderRadius.circular(14),
                        boxShadow: shadowSoft,
                      ),
                      child: IconButton(
                        tooltip: 'Back to Profile',
                        onPressed: () => Navigator.maybePop(context),
                        icon: const Icon(
                          Icons.arrow_back_rounded,
                          color: darkMaroon,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    const Text(
                      'Assignment',
                      style: TextStyle(
                        color: darkMaroon,
                        fontSize: 27,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.4,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 5),

              const _EntranceAnimation(
                delay: 50,
                child: Text(
                  'Review your assigned hub, route, shift, and parcels.',
                  style: TextStyle(color: textMuted, fontSize: 12.5),
                ),
              ),

              const SizedBox(height: 22),

              // ==================================================
              // CURRENT ASSIGNMENT (hero card)
              // ==================================================
              const _EntranceAnimation(
                delay: 80,
                child: _CurrentAssignmentCard(),
              ),

              const SizedBox(height: 14),

              // ==================================================
              // QUICK STATS ROW
              // ==================================================
              const _EntranceAnimation(delay: 110, child: _QuickStatsRow()),

              const SizedBox(height: 28),

              // ==================================================
              // ASSIGNED PARCELS HEADER
              // ==================================================
              const _EntranceAnimation(
                delay: 150,
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Assigned Parcels',
                        style: TextStyle(
                          color: darkMaroon,
                          fontSize: 18.5,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.3,
                        ),
                      ),
                    ),
                    _ParcelCountBadge(count: assignedParcels),
                  ],
                ),
              ),

              const SizedBox(height: 12),

              // ==================================================
              // PARCEL LIST PLACEHOLDER
              // ==================================================
              const _EntranceAnimation(
                delay: 190,
                child: _AssignedParcelsPlaceholder(),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ============================================================
// CURRENT ASSIGNMENT CARD (hero)
// ============================================================

class _CurrentAssignmentCard extends StatelessWidget {
  const _CurrentAssignmentCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(AssignmentScreen.radiusXl),
        boxShadow: AssignmentScreen.shadowSoft,
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AssignmentScreen.radiusXl),
        child: Column(
          children: [
            // ======================================================
            // GRADIENT HEADER — area name + active status
            // ======================================================
            Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(20, 20, 18, 22),
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    AssignmentScreen.darkMaroon,
                    AssignmentScreen.maroon,
                  ],
                ),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: Colors.white.withValues(alpha: 0.18),
                      ),
                    ),
                    child: const Icon(
                      Icons.location_on_rounded,
                      color: Colors.white,
                      size: 23,
                    ),
                  ),

                  const SizedBox(width: 13),

                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'CURRENT ASSIGNMENT',
                          style: TextStyle(
                            color: Colors.white70,
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                            letterSpacing: 1.0,
                          ),
                        ),
                        SizedBox(height: 5),
                        Text(
                          AssignmentScreen.assignedArea,
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 19,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.2,
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(width: 8),

                  const _ActiveStatusBadge(),
                ],
              ),
            ),

            // ======================================================
            // DETAIL ROWS
            // ======================================================
            Container(
              width: double.infinity,
              color: AssignmentScreen.surface,
              padding: const EdgeInsets.fromLTRB(20, 18, 20, 20),
              child: Column(
                children: const [
                  _AssignmentInfoRow(
                    icon: Icons.warehouse_rounded,
                    label: 'Hub',
                    value: AssignmentScreen.assignedHub,
                  ),
                  SizedBox(height: 14),
                  Divider(height: 1, thickness: 1, color: Color(0x0F62191C)),
                  SizedBox(height: 14),
                  _AssignmentInfoRow(
                    icon: Icons.route_rounded,
                    label: 'Delivery Area',
                    value: AssignmentScreen.assignedArea,
                  ),
                  SizedBox(height: 14),
                  Divider(height: 1, thickness: 1, color: Color(0x0F62191C)),
                  SizedBox(height: 14),
                  _AssignmentInfoRow(
                    icon: Icons.schedule_rounded,
                    label: 'Shift',
                    value: AssignmentScreen.assignedShift,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ============================================================
// ACTIVE STATUS BADGE
// ============================================================

class _ActiveStatusBadge extends StatelessWidget {
  const _ActiveStatusBadge();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.white.withValues(alpha: 0.22)),
      ),
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          _Pulse(),
          SizedBox(width: 6),
          Text(
            'Active',
            style: TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _Pulse extends StatelessWidget {
  const _Pulse();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 7,
      height: 7,
      decoration: const BoxDecoration(
        color: Color(0xFF7BE38B),
        shape: BoxShape.circle,
      ),
    );
  }
}

// ============================================================
// QUICK STATS ROW
// ============================================================

class _QuickStatsRow extends StatelessWidget {
  const _QuickStatsRow();

  @override
  Widget build(BuildContext context) {
    final progress = AssignmentScreen.assignedParcels == 0
        ? 0.0
        : AssignmentScreen.deliveredParcels / AssignmentScreen.assignedParcels;

    return Row(
      children: [
        Expanded(
          child: _StatCard(
            icon: Icons.inventory_2_rounded,
            label: 'Parcels',
            value: '${AssignmentScreen.assignedParcels}',
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: _StatCard(
            icon: Icons.check_circle_rounded,
            label: 'Delivered',
            value: '${AssignmentScreen.deliveredParcels}',
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: _StatCard(
            icon: Icons.donut_large_rounded,
            label: 'Progress',
            value: '${(progress * 100).round()}%',
          ),
        ),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;

  const _StatCard({
    required this.icon,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      decoration: BoxDecoration(
        color: AssignmentScreen.surface,
        borderRadius: BorderRadius.circular(AssignmentScreen.radiusMd),
        border: Border.all(
          color: AssignmentScreen.darkMaroon.withValues(alpha: 0.06),
        ),
        boxShadow: [
          BoxShadow(
            color: AssignmentScreen.darkMaroon.withValues(alpha: 0.04),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AssignmentScreen.maroon, size: 18),
          const SizedBox(height: 10),
          Text(
            value,
            style: const TextStyle(
              color: AssignmentScreen.darkMaroon,
              fontSize: 17,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.3,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: const TextStyle(
              color: AssignmentScreen.textMuted,
              fontSize: 10.5,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

// ============================================================
// ASSIGNMENT INFORMATION ROW
// ============================================================

class _AssignmentInfoRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;

  const _AssignmentInfoRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(
            color: AssignmentScreen.lightBeige.withValues(alpha: 0.32),
            borderRadius: BorderRadius.circular(11),
          ),
          child: Icon(icon, color: AssignmentScreen.darkMaroon, size: 17),
        ),

        const SizedBox(width: 12),

        Expanded(
          child: Text(
            label,
            style: const TextStyle(
              color: AssignmentScreen.textMuted,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),

        const SizedBox(width: 12),

        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(
              color: AssignmentScreen.darkMaroon,
              fontSize: 12.5,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ],
    );
  }
}

// ============================================================
// PARCEL COUNT BADGE
// ============================================================

class _ParcelCountBadge extends StatelessWidget {
  final int count;

  const _ParcelCountBadge({required this.count});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 6),
      decoration: BoxDecoration(
        color: AssignmentScreen.darkMaroon.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        '$count',
        style: const TextStyle(
          color: AssignmentScreen.darkMaroon,
          fontSize: 12,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

// ============================================================
// ASSIGNED PARCELS PLACEHOLDER (empty state)
// ============================================================

class _AssignedParcelsPlaceholder extends StatelessWidget {
  const _AssignedParcelsPlaceholder();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 34),
      decoration: BoxDecoration(
        color: AssignmentScreen.surface,
        borderRadius: BorderRadius.circular(AssignmentScreen.radiusLg),
        border: Border.all(
          color: AssignmentScreen.darkMaroon.withValues(alpha: 0.10),
          width: 1.2,
        ),
      ),
      child: Column(
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  AssignmentScreen.lightBeige.withValues(alpha: 0.55),
                  AssignmentScreen.lightBeige.withValues(alpha: 0.25),
                ],
              ),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.inventory_2_outlined,
              color: AssignmentScreen.darkMaroon,
              size: 28,
            ),
          ),

          const SizedBox(height: 16),

          const Text(
            'Assigned parcels will appear here',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: AssignmentScreen.darkMaroon,
              fontSize: 15,
              fontWeight: FontWeight.w800,
            ),
          ),

          const SizedBox(height: 6),

          const Text(
            'Parcels assigned by Logistics for Brgy. Masapang will be shown in this section.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: AssignmentScreen.textMuted,
              fontSize: 11.5,
              height: 1.45,
            ),
          ),
        ],
      ),
    );
  }
}

// ============================================================
// ENTRANCE ANIMATION
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
      if (!mounted) {
        return;
      }

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

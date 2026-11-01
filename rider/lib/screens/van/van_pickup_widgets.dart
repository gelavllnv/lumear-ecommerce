import 'package:flutter/material.dart';

import '../../models/van_pickup_request.dart';

const Color vanDarkMaroon = Color(0xFF62191C);
const Color vanMaroon = Color(0xFF873632);
const Color vanBrownBeige = Color(0xFF9E7161);
const Color vanLightBeige = Color(0xFFCAAE9F);
const Color vanCream = Color(0xFFF3E9E1);

class VanPickupStatusChip extends StatelessWidget {
  final VanPickupStatus status;

  const VanPickupStatusChip({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    Color backgroundColor;
    Color foregroundColor;

    switch (status) {
      case VanPickupStatus.assigned:
        backgroundColor = const Color(0xFFFFE8B2);
        foregroundColor = const Color(0xFF795500);
        break;
      case VanPickupStatus.inProgress:
        backgroundColor = const Color(0xFFDCEBFF);
        foregroundColor = const Color(0xFF174A8B);
        break;
      case VanPickupStatus.arrived:
        backgroundColor = const Color(0xFFDCEBFF);
        foregroundColor = const Color(0xFF174A8B);
      case VanPickupStatus.collected:
        backgroundColor = const Color(0xFFE4DDF8);
        foregroundColor = const Color(0xFF513A87);
        break;
      case VanPickupStatus.sellerConfirmed:
        backgroundColor = const Color(0xFFD9F2DF);
        foregroundColor = const Color(0xFF873632);
        break;
      case VanPickupStatus.handedOver:
        backgroundColor = const Color(0xFFD9F2DF);
        foregroundColor = const Color(0xFF873632);
        break;
      case VanPickupStatus.failed:
        backgroundColor = const Color(0xFFF8E6E6);
        foregroundColor = const Color(0xFFB71C1C);
        break;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        statusLabel(status),
        style: TextStyle(
          color: foregroundColor,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

String statusLabel(VanPickupStatus status) {
  switch (status) {
    case VanPickupStatus.assigned:
      return 'Assigned';
    case VanPickupStatus.inProgress:
      return 'In Progress';
    case VanPickupStatus.arrived:
      return 'Arrived';
    case VanPickupStatus.collected:
      return 'Waiting for Seller Confirmation';
    case VanPickupStatus.sellerConfirmed:
      return 'Seller Confirmed';
    case VanPickupStatus.handedOver:
      return 'Handed Over';
    case VanPickupStatus.failed:
      return 'Failed';
  }
}

class VanPickupCard extends StatelessWidget {
  final VanPickupRequest pickup;
  final VoidCallback onTap;

  const VanPickupCard({super.key, required this.pickup, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      color: Colors.white,
      elevation: 1,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: Color(0xFFE8D6C3)),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      color: vanCream,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.storefront_outlined,
                      color: vanDarkMaroon,
                      size: 25,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          pickup.sellerName,
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                            color: vanDarkMaroon,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          pickup.id,
                          style: const TextStyle(
                            fontSize: 12,
                            color: Colors.black54,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: vanBrownBeige),
                ],
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  const Icon(
                    Icons.location_on_outlined,
                    size: 18,
                    color: vanBrownBeige,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      pickup.fullAddress,
                      style: const TextStyle(fontSize: 13),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  const Icon(
                    Icons.inventory_2_outlined,
                    size: 18,
                    color: vanBrownBeige,
                  ),
                  const SizedBox(width: 6),
                  Text(
                    '${pickup.parcelCount} parcels',
                    style: const TextStyle(fontSize: 13),
                  ),
                  const Spacer(),
                  VanPickupStatusChip(status: pickup.status),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class VanPickupInfoRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;

  const VanPickupInfoRow({
    super.key,
    required this.icon,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: vanBrownBeige, size: 21),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(color: Colors.black54, fontSize: 12),
                ),
                const SizedBox(height: 4),
                Text(
                  value.isEmpty ? 'Not provided' : value,
                  style: const TextStyle(
                    color: vanDarkMaroon,
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
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

class VanPickupSectionCard extends StatelessWidget {
  final String title;
  final IconData icon;
  final Widget child;

  const VanPickupSectionCard({
    super.key,
    required this.title,
    required this.icon,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE8D6C3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: vanMaroon),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    color: vanDarkMaroon,
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          child,
        ],
      ),
    );
  }
}

import 'package:flutter/material.dart';

import 'package:flutter_application_1/models/parcel_store.dart';
import 'package:flutter_application_1/screens/assignment_screen.dart';
import 'package:flutter_application_1/screens/sorting_center_receipt_screen.dart';

class SortingCenterHandoffScreen extends StatefulWidget {
  final String requestId;

  const SortingCenterHandoffScreen({super.key, required this.requestId});

  @override
  State<SortingCenterHandoffScreen> createState() =>
      _SortingCenterHandoffScreenState();
}

class _SortingCenterHandoffScreenState
    extends State<SortingCenterHandoffScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFE0CFC2);
  static const Color success = Color(0xFF2E7D32);
  static const Color danger = Color(0xFFB3261E);

  bool _sellerAcknowledged = false;
  bool _isWorking = false;

  void _showMessage(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          backgroundColor: isError ? danger : darkMaroon,
          content: Text(message),
        ),
      );
  }

  Widget _infoTile({
    required IconData icon,
    required String title,
    required String value,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: cream.withValues(alpha: 0.65),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(icon, color: darkMaroon, size: 21),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(color: brownBeige, fontSize: 11),
                ),
                const SizedBox(height: 4),
                Text(
                  value.trim().isEmpty ? 'Not provided' : value,
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionCard({
    required String title,
    required IconData icon,
    required List<Widget> children,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(17),
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: lightBeige.withValues(alpha: 0.7)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, color: darkMaroon, size: 21),
              const SizedBox(width: 9),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          ...children,
        ],
      ),
    );
  }

  Future<void> _continueToSortingCenter(SellerPickupRequest pickup) async {
    if (_isWorking) return;

    if (!_sellerAcknowledged) {
      _showMessage(
        'Please record the seller handover acknowledgment first.',
        isError: true,
      );
      return;
    }

    setState(() => _isWorking = true);

    try {
      if (pickup.status == SellerPickupStatus.pickedUp) {
        final started = ParcelStore.beginSellerPickupHeadingToSortingCenter(
          pickup.requestId,
        );

        if (!started) {
          _showMessage(
            'Unable to start the sorting-center handoff. '
            'Check the current pickup status.',
            isError: true,
          );
          return;
        }
      } else if (pickup.status != SellerPickupStatus.headingToSortingCenter) {
        _showMessage(
          'This pickup must be confirmed before heading to the sorting center.',
          isError: true,
        );
        return;
      }

      if (!mounted) return;

      // Kunin muna ang data bago mag-navigate.
      final trackingNumbers = pickup.expectedParcels
          .map((parcel) => parcel.trackingNumber)
          .toList();

      await Navigator.pushReplacement<void, void>(
        context,
        MaterialPageRoute<void>(
          builder: (_) => SortingCenterReceiptScreen(
            requestId: pickup.requestId,
            sellerName: pickup.sellerName,
            sellerPhone: pickup.sellerPhone ?? '',
            pickupAddress: pickup.pickupAddress,
            sortingCenter: AssignmentScreen.assignedHub,
            trackingNumbers: trackingNumbers,
            deliveredAt: DateTime.now(),
          ),
        ),
      );
    } catch (_) {
      if (mounted) {
        _showMessage('Unable to continue. Please try again.', isError: true);
      }
    } finally {
      if (mounted) {
        setState(() => _isWorking = false);
      }
    }
  }

  Widget _bottomAction(SellerPickupRequest pickup) {
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(18, 12, 18, 16),
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: darkMaroon.withValues(alpha: 0.08),
              blurRadius: 12,
              offset: const Offset(0, -3),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              width: double.infinity,
              height: 52,
              child: ElevatedButton.icon(
                onPressed: _isWorking
                    ? null
                    : () => _continueToSortingCenter(pickup),
                icon: _isWorking
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.warehouse_rounded),
                label: Text(
                  _isWorking ? 'Please wait...' : 'Proceed to Sorting Center',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: darkMaroon,
                  foregroundColor: Colors.white,
                  disabledBackgroundColor: lightBeige,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(13),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Make sure the collected parcels are secured before departure.',
              textAlign: TextAlign.center,
              style: TextStyle(color: brownBeige, fontSize: 10),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPage(SellerPickupRequest pickup) {
    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: cream,
        foregroundColor: darkMaroon,
        title: const Text('Sorting Center Handoff'),
      ),
      bottomNavigationBar: _bottomAction(pickup),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
        children: [
          // Header
          Container(
            padding: const EdgeInsets.all(19),
            decoration: BoxDecoration(
              color: darkMaroon,
              borderRadius: BorderRadius.circular(18),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(11),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(
                        Icons.warehouse_rounded,
                        color: Colors.white,
                        size: 27,
                      ),
                    ),
                    const SizedBox(width: 12),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Pickup Completed',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          SizedBox(height: 4),
                          Text(
                            'Review the handover details',
                            style: TextStyle(
                              color: Color(0xFFE0CFC2),
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Icon(
                      Icons.check_circle_rounded,
                      color: Color(0xFF9FE0A4),
                      size: 27,
                    ),
                  ],
                ),
                const SizedBox(height: 20),
                const Text(
                  'PICKUP REQUEST',
                  style: TextStyle(
                    color: Color(0xFFE0CFC2),
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  pickup.requestId,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 16),

          // Parcel summary
          Row(
            children: [
              Expanded(
                child: _summaryCard(
                  icon: Icons.inventory_2_outlined,
                  label: 'Expected Parcels',
                  value: '${pickup.expectedParcels.length}',
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _summaryCard(
                  icon: Icons.check_circle_outline_rounded,
                  label: 'Pickup Status',
                  value:
                      pickup.status == SellerPickupStatus.headingToSortingCenter
                      ? 'In Transit'
                      : 'Collected',
                ),
              ),
            ],
          ),

          const SizedBox(height: 16),

          // Seller information
          _sectionCard(
            title: 'Seller Information',
            icon: Icons.storefront_outlined,
            children: [
              _infoTile(
                icon: Icons.store_outlined,
                title: 'Seller Name',
                value: pickup.sellerName,
              ),
              _infoTile(
                icon: Icons.phone_outlined,
                title: 'Contact Number',
                value: pickup.sellerPhone ?? '',
              ),
              _infoTile(
                icon: Icons.location_on_outlined,
                title: 'Pickup Address',
                value: pickup.pickupAddress,
              ),
              _infoTile(
                icon: Icons.confirmation_number_outlined,
                title: 'Pickup Request ID',
                value: pickup.requestId,
              ),
            ],
          ),

          // Sorting center information
          _sectionCard(
            title: 'Destination',
            icon: Icons.warehouse_outlined,
            children: [
              _infoTile(
                icon: Icons.local_shipping_outlined,
                title: 'Assigned Sorting Center',
                value: AssignmentScreen.assignedHub,
              ),
              const Text(
                'Bring all collected parcels to the assigned sorting center '
                'for receiving and verification.',
                style: TextStyle(color: brownBeige, fontSize: 12, height: 1.5),
              ),
            ],
          ),

          // Parcel list
          _sectionCard(
            title: 'Collected Parcels',
            icon: Icons.inventory_2_outlined,
            children: [
              if (pickup.expectedParcels.isEmpty)
                const Text(
                  'No parcels are listed for this pickup.',
                  style: TextStyle(color: brownBeige, fontSize: 12),
                ),
              ...pickup.expectedParcels.map((parcel) {
                return Container(
                  margin: const EdgeInsets.only(bottom: 9),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: cream.withValues(alpha: 0.45),
                    borderRadius: BorderRadius.circular(11),
                    border: Border.all(
                      color: lightBeige.withValues(alpha: 0.7),
                    ),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.inventory_2_rounded,
                        color: darkMaroon,
                        size: 22,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          parcel.trackingNumber,
                          style: const TextStyle(
                            color: darkMaroon,
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                      const Icon(
                        Icons.check_circle_rounded,
                        color: success,
                        size: 20,
                      ),
                    ],
                  ),
                );
              }),
              const SizedBox(height: 5),
              Text(
                'Total listed parcels: ${pickup.expectedParcels.length}',
                style: const TextStyle(
                  color: darkMaroon,
                  fontWeight: FontWeight.w800,
                  fontSize: 12,
                ),
              ),
            ],
          ),

          // Seller handover confirmation
          _sectionCard(
            title: 'Seller Handover Confirmation',
            icon: Icons.fact_check_outlined,
            children: [
              Container(
                padding: const EdgeInsets.all(13),
                decoration: BoxDecoration(
                  color: _sellerAcknowledged
                      ? success.withValues(alpha: 0.08)
                      : cream.withValues(alpha: 0.55),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: _sellerAcknowledged
                        ? success.withValues(alpha: 0.35)
                        : lightBeige,
                  ),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      _sellerAcknowledged
                          ? Icons.verified_rounded
                          : Icons.pending_actions_rounded,
                      color: _sellerAcknowledged ? success : brownBeige,
                      size: 25,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _sellerAcknowledged
                                ? 'Acknowledgment Recorded'
                                : 'Awaiting Acknowledgment',
                            style: const TextStyle(
                              color: darkMaroon,
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            _sellerAcknowledged
                                ? 'You recorded that the seller acknowledged '
                                      'the parcel handover.'
                                : 'Confirm this only after the seller has '
                                      'acknowledged that the parcels were handed over.',
                            style: const TextStyle(
                              color: brownBeige,
                              fontSize: 11,
                              height: 1.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              CheckboxListTile(
                value: _sellerAcknowledged,
                onChanged: (value) {
                  setState(() {
                    _sellerAcknowledged = value ?? false;
                  });
                },
                activeColor: darkMaroon,
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                title: const Text(
                  'Seller acknowledged the parcel handover',
                  style: TextStyle(
                    color: darkMaroon,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                subtitle: const Text(
                  'Check only after confirming with the seller.',
                  style: TextStyle(color: brownBeige, fontSize: 10),
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Note: This acknowledgment is currently recorded only in '
                'this screen. It is not yet a seller-signed confirmation '
                'or a record saved to Laravel.',
                style: TextStyle(color: brownBeige, fontSize: 11, height: 1.5),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _summaryCard({
    required IconData icon,
    required String label,
    required String value,
  }) {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: lightBeige.withValues(alpha: 0.7)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: maroon, size: 23),
          const SizedBox(height: 12),
          Text(label, style: const TextStyle(color: brownBeige, fontSize: 10)),
          const SizedBox(height: 5),
          Text(
            value,
            style: const TextStyle(
              color: darkMaroon,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<List<SellerPickupRequest>>(
      valueListenable: ParcelStore.sellerPickups,
      builder: (context, pickups, _) {
        SellerPickupRequest? pickup;

        for (final item in pickups) {
          if (item.requestId == widget.requestId) {
            pickup = item;
            break;
          }
        }

        if (pickup == null) {
          return Scaffold(
            backgroundColor: cream,
            appBar: AppBar(
              backgroundColor: cream,
              foregroundColor: darkMaroon,
              title: const Text('Sorting Center Handoff'),
            ),
            body: const Center(child: Text('Pickup request not found.')),
          );
        }

        return _buildPage(pickup);
      },
    );
  }
}

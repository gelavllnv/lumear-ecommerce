import 'package:flutter/material.dart';
import 'package:flutter_application_1/models/rider_profile.dart';

import 'pre_trip_inspection_screen.dart';

class TruckInfoScreen extends StatefulWidget {
  const TruckInfoScreen({super.key});

  @override
  State<TruckInfoScreen> createState() => _TruckInfoScreenState();
}

class _TruckInfoScreenState extends State<TruckInfoScreen> {
  static const Color darkMaroon = Color(0xFF671B1D);
  static const Color maroon = Color(0xFF873632);
  static const Color brown = Color(0xFF9E7161);
  static const Color cream = Color(0xFFFFF8E7);
  static const Color paleCream = Color(0xFFFFFDF5);
  static const Color border = Color(0xFFE8D6C3);

  String get truckId => 'Not assigned by Logistics';
  String get riderId => RiderProfile.displayId.isEmpty
      ? 'Not available'
      : RiderProfile.displayId;
  String get plateNumber => RiderProfile.plateNumber.trim().isEmpty
      ? 'Not assigned'
      : RiderProfile.plateNumber.trim();
  String get truckType => RiderProfile.vehicleType.trim().isEmpty
      ? 'Truck'
      : RiderProfile.vehicleType.trim();
  String get truckModel => 'Set by Logistics';
  String get capacity => 'Set by assigned truck';
  String get registrationDocumentStatus =>
      RiderProfile.orCrImage.trim().isEmpty ? 'Not on file' : 'Uploaded';
  String get registrationStatus => RiderProfile.status.trim().isEmpty
      ? 'Waiting for Logistics'
      : RiderProfile.status.trim();
  String get assignedHub => RiderProfile.logisticsProvider.trim().isEmpty
      ? 'Not assigned'
      : RiderProfile.logisticsProvider.trim();
  String get deliveryArea {
    final parts = [
      RiderProfile.barangay,
      RiderProfile.municipality,
      RiderProfile.province,
    ].where((part) => part.trim().isNotEmpty).toList();
    return parts.isEmpty ? 'Not assigned' : parts.join(', ');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          'Truck Information',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          // The truck dashboard keeps its floating navbar over the page.
          // Reserve enough scroll space so the inspection action can clear it.
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 145),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildTruckHeader(),
              const SizedBox(height: 22),
              _sectionTitle(Icons.local_shipping_outlined, 'Vehicle Details'),
              const SizedBox(height: 10),
              _buildVehicleDetails(),
              const SizedBox(height: 8),
              const Text(
                'Load capacity depends on the exact assigned truck. Follow its registered payload rating; Logistics should provide this value before loading.',
                style: TextStyle(
                  color: Colors.black54,
                  fontSize: 12,
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 22),
              _sectionTitle(Icons.folder_outlined, 'Vehicle Documents'),
              const SizedBox(height: 10),
              _buildDocuments(),
              const SizedBox(height: 22),
              _sectionTitle(Icons.fact_check_outlined, 'Pre-Trip Inspection'),
              const SizedBox(height: 10),
              _inspectionShortcut(context),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildTruckHeader() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: darkMaroon,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 58,
                height: 58,
                decoration: BoxDecoration(
                  color: cream.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(
                  Icons.local_shipping_rounded,
                  color: cream,
                  size: 34,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Assigned Vehicle',
                      style: const TextStyle(color: cream, fontSize: 13),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Truck not assigned',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 21,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.09),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Row(
              children: [
                const Icon(Icons.pin_outlined, color: cream, size: 23),
                const SizedBox(width: 10),
                const Expanded(
                  child: Text('Plate Number', style: TextStyle(color: cream)),
                ),
                Text(
                  plateNumber,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionTitle(IconData icon, String title) {
    return Row(
      children: [
        Icon(icon, color: darkMaroon, size: 23),
        const SizedBox(width: 9),
        Text(
          title,
          style: const TextStyle(
            color: darkMaroon,
            fontSize: 17,
            fontWeight: FontWeight.bold,
          ),
        ),
      ],
    );
  }

  Widget _buildVehicleDetails() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: paleCream,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: border),
      ),
      child: Column(
        children: [
          _detailRow(Icons.tag_rounded, 'Truck ID', truckId),
          const Divider(height: 25, color: border),
          _detailRow(Icons.badge_outlined, 'Rider ID', riderId),
          const Divider(height: 25, color: border),
          _detailRow(Icons.directions_car_outlined, 'Vehicle Type', truckType),
          const Divider(height: 25, color: border),
          _detailRow(Icons.fire_truck_outlined, 'Vehicle Model', truckModel),
          const Divider(height: 25, color: border),
          _detailRow(Icons.pin_outlined, 'Plate Number', plateNumber),
          const Divider(height: 25, color: border),
          _detailRow(Icons.warehouse_outlined, 'Assigned Hub', assignedHub),
          const Divider(height: 25, color: border),
          _detailRow(Icons.place_outlined, 'Delivery Area', deliveryArea),
          const Divider(height: 25, color: border),
          _detailRow(Icons.scale_outlined, 'Load Capacity', capacity),
          const Divider(height: 25, color: border),
          _detailRow(
            Icons.verified_outlined,
            'Registration',
            registrationStatus,
          ),
        ],
      ),
    );
  }

  Widget _inspectionShortcut(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: paleCream,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Complete the vehicle safety checklist before leaving for a trip.',
            style: TextStyle(color: Colors.black54, fontSize: 13, height: 1.45),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute<void>(
                  builder: (_) => const PreTripInspectionScreen(),
                ),
              ),
              icon: const Icon(Icons.fact_check_outlined),
              label: const Text('Open inspection and report'),
              style: ElevatedButton.styleFrom(
                backgroundColor: darkMaroon,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(13),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _detailRow(IconData icon, String label, String value) {
    return Row(
      children: [
        Icon(icon, color: brown, size: 22),
        const SizedBox(width: 12),
        Expanded(
          child: Text(
            label,
            style: const TextStyle(color: Colors.black54, fontSize: 13),
          ),
        ),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: const TextStyle(
              color: darkMaroon,
              fontWeight: FontWeight.bold,
              fontSize: 13,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildDocuments() {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: paleCream,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: border),
      ),
      child: Column(
        children: [
          _documentRow(
            icon: Icons.description_outlined,
            title: 'OR / CR registration document',
            subtitle: 'Upload or view the file in My Documents',
            status: registrationDocumentStatus,
          ),
          const Divider(height: 25, color: border),
          _documentRow(
            icon: Icons.badge_outlined,
            title: 'Vehicle Insurance',
            subtitle: 'Not connected to a vehicle record yet',
            status: 'Not linked',
          ),
          const SizedBox(height: 8),
          const Align(
            alignment: Alignment.centerLeft,
            child: Text(
              'Vehicle model, rated capacity, and insurance status update when Logistics links a truck record.',
              style: TextStyle(color: brown, fontSize: 11),
            ),
          ),
        ],
      ),
    );
  }

  Widget _documentRow({
    required IconData icon,
    required String title,
    required String subtitle,
    required String status,
  }) {
    final bool onFile = status == 'On File' || status == 'Uploaded';

    return Row(
      children: [
        Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            color: cream,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, color: maroon),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  color: darkMaroon,
                  fontWeight: FontWeight.bold,
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                subtitle,
                style: const TextStyle(color: Colors.black54, fontSize: 11),
              ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
          decoration: BoxDecoration(
            color: onFile ? Colors.green.withValues(alpha: 0.10) : cream,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text(
            status,
            style: TextStyle(
              color: onFile ? Colors.green.shade700 : brown,
              fontSize: 10,
              fontWeight: FontWeight.bold,
            ),
          ),
        ),
      ],
    );
  }

}

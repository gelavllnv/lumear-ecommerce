import 'package:flutter/material.dart';

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

  // Hardcoded truck details for the prototype.
  final String truckId = 'TRK-001';
  final String plateNumber = 'ABC-1234';
  final String truckType = 'Closed Van';
  final String truckModel = 'Delivery Truck';
  final String capacity = '2,000 kg';
  final String registrationStatus = 'Registered';

  final Map<String, bool> inspectionItems = {
    'Brakes': false,
    'Tires and wheels': false,
    'Headlights and signal lights': false,
    'Engine and oil': false,
    'Fuel level': false,
    'Battery': false,
    'Mirrors and windshield': false,
    'Horn': false,
    'Seatbelts': false,
    'Vehicle body and doors': false,
  };

  bool inspectionSaved = false;
  bool needsAttention = false;

  int get checkedCount =>
      inspectionItems.values.where((checked) => checked).length;

  bool get allItemsChecked =>
      inspectionItems.values.every((checked) => checked);

  String get inspectionStatus {
    if (needsAttention) return 'Needs Attention';
    if (inspectionSaved && allItemsChecked) return 'Passed';
    if (inspectionSaved) return 'Inspection Saved';
    if (checkedCount > 0) return 'In Progress';
    return 'Not Started';
  }

  Color get inspectionStatusColor {
    if (needsAttention) return Colors.red.shade700;
    if (inspectionSaved && allItemsChecked) return Colors.green.shade700;
    if (inspectionSaved || checkedCount > 0) return brown;
    return Colors.grey.shade700;
  }

  void _saveInspection() {
    if (checkedCount == 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please complete the inspection checklist first.'),
          backgroundColor: darkMaroon,
        ),
      );
      return;
    }

    setState(() {
      inspectionSaved = true;
    });

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          'Inspection saved locally: $checkedCount of '
          '${inspectionItems.length} items checked.',
        ),
        backgroundColor: darkMaroon,
      ),
    );
  }

  Future<void> _reportProblem() async {
    final TextEditingController controller = TextEditingController();

    final String? problem = await showDialog<String>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          backgroundColor: paleCream,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          title: const Text(
            'Report Vehicle Issue',
            style: TextStyle(color: darkMaroon, fontWeight: FontWeight.bold),
          ),
          content: TextField(
            controller: controller,
            maxLines: 4,
            decoration: InputDecoration(
              hintText: 'Describe the issue with the truck...',
              filled: true,
              fillColor: cream,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: border),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: border),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: maroon),
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text('Cancel', style: TextStyle(color: brown)),
            ),
            ElevatedButton(
              onPressed: () {
                final String value = controller.text.trim();
                if (value.isNotEmpty) {
                  Navigator.pop(dialogContext, value);
                }
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: darkMaroon,
                foregroundColor: Colors.white,
              ),
              child: const Text('Submit'),
            ),
          ],
        );
      },
    );

    controller.dispose();

    if (problem != null && mounted) {
      setState(() {
        needsAttention = true;
        inspectionSaved = false;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Issue recorded locally. Please notify Logistics or maintenance.',
          ),
          backgroundColor: darkMaroon,
        ),
      );
    }
  }

  void _resetInspection() {
    setState(() {
      for (final key in inspectionItems.keys) {
        inspectionItems[key] = false;
      }
      inspectionSaved = false;
      needsAttention = false;
    });
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
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildTruckHeader(),
              const SizedBox(height: 22),
              _sectionTitle(Icons.local_shipping_outlined, 'Vehicle Details'),
              const SizedBox(height: 10),
              _buildVehicleDetails(),
              const SizedBox(height: 22),
              _sectionTitle(Icons.folder_outlined, 'Vehicle Documents'),
              const SizedBox(height: 10),
              _buildDocuments(),
              const SizedBox(height: 22),
              _sectionTitle(Icons.fact_check_outlined, 'Pre-Trip Inspection'),
              const SizedBox(height: 8),
              const Text(
                'Inspect the vehicle before starting your assigned trip. '
                'Check each item only after verifying it.',
                style: TextStyle(
                  color: Colors.black54,
                  fontSize: 13,
                  height: 1.5,
                ),
              ),
              const SizedBox(height: 14),
              _buildInspectionProgress(),
              const SizedBox(height: 12),
              _buildInspectionChecklist(),
              const SizedBox(height: 16),
              _buildInspectionStatus(),
              const SizedBox(height: 20),
              _buildActionButtons(),
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
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Assigned Vehicle',
                      style: TextStyle(color: cream, fontSize: 13),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Truck TRK-001',
                      style: TextStyle(
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
          _detailRow(Icons.directions_car_outlined, 'Vehicle Type', truckType),
          const Divider(height: 25, color: border),
          _detailRow(Icons.fire_truck_outlined, 'Vehicle Model', truckModel),
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
            title: 'Official Receipt (OR)',
            subtitle: 'Vehicle registration receipt',
            status: 'On File',
          ),
          const Divider(height: 25, color: border),
          _documentRow(
            icon: Icons.article_outlined,
            title: 'Certificate of Registration (CR)',
            subtitle: 'Vehicle registration document',
            status: 'On File',
          ),
          const Divider(height: 25, color: border),
          _documentRow(
            icon: Icons.badge_outlined,
            title: 'Vehicle Insurance',
            subtitle: 'Insurance document',
            status: 'Check',
          ),
          const SizedBox(height: 8),
          const Align(
            alignment: Alignment.centerLeft,
            child: Text(
              'Document statuses are sample values. '
              'Actual files are not uploaded in this prototype.',
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
    final bool onFile = status == 'On File';

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

  Widget _buildInspectionProgress() {
    final double progress = checkedCount / inspectionItems.length;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: paleCream,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: border),
      ),
      child: Column(
        children: [
          Row(
            children: [
              const Icon(Icons.checklist_rounded, color: maroon, size: 24),
              const SizedBox(width: 10),
              const Expanded(
                child: Text(
                  'Inspection Progress',
                  style: TextStyle(
                    color: darkMaroon,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              Text(
                '$checkedCount/${inspectionItems.length}',
                style: const TextStyle(
                  color: darkMaroon,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 8,
              backgroundColor: border,
              valueColor: const AlwaysStoppedAnimation<Color>(maroon),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInspectionChecklist() {
    return Container(
      decoration: BoxDecoration(
        color: paleCream,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: border),
      ),
      child: Column(
        children: inspectionItems.entries.map((entry) {
          return CheckboxListTile(
            value: entry.value,
            onChanged: (value) {
              setState(() {
                inspectionItems[entry.key] = value ?? false;
                inspectionSaved = false;
              });
            },
            activeColor: darkMaroon,
            checkColor: Colors.white,
            controlAffinity: ListTileControlAffinity.leading,
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 12,
              vertical: 2,
            ),
            title: Text(
              entry.key,
              style: const TextStyle(
                color: darkMaroon,
                fontSize: 13,
                fontWeight: FontWeight.w500,
              ),
            ),
            secondary: Icon(
              entry.value
                  ? Icons.check_circle_outline_rounded
                  : Icons.circle_outlined,
              color: entry.value ? Colors.green.shade700 : brown,
              size: 21,
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _buildInspectionStatus() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: paleCream,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: border),
      ),
      child: Row(
        children: [
          Icon(
            needsAttention
                ? Icons.warning_amber_rounded
                : inspectionSaved && allItemsChecked
                ? Icons.check_circle_rounded
                : Icons.fact_check_outlined,
            color: inspectionStatusColor,
            size: 28,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Current Status',
                  style: TextStyle(color: Colors.black54, fontSize: 12),
                ),
                const SizedBox(height: 4),
                Text(
                  inspectionStatus,
                  style: TextStyle(
                    color: inspectionStatusColor,
                    fontWeight: FontWeight.bold,
                    fontSize: 15,
                  ),
                ),
              ],
            ),
          ),
          if (inspectionSaved && allItemsChecked && !needsAttention)
            const Icon(Icons.verified_rounded, color: Colors.green, size: 24),
        ],
      ),
    );
  }

  Widget _buildActionButtons() {
    return Column(
      children: [
        SizedBox(
          width: double.infinity,
          height: 52,
          child: ElevatedButton.icon(
            onPressed: _saveInspection,
            icon: const Icon(Icons.save_outlined),
            label: const Text('Save Inspection'),
            style: ElevatedButton.styleFrom(
              backgroundColor: darkMaroon,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
          ),
        ),
        const SizedBox(height: 10),
        SizedBox(
          width: double.infinity,
          height: 50,
          child: OutlinedButton.icon(
            onPressed: _reportProblem,
            icon: const Icon(Icons.report_problem_outlined),
            label: const Text('Report Vehicle Issue'),
            style: OutlinedButton.styleFrom(
              foregroundColor: darkMaroon,
              side: const BorderSide(color: maroon),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
          ),
        ),
        const SizedBox(height: 10),
        TextButton.icon(
          onPressed: _resetInspection,
          icon: const Icon(Icons.refresh_rounded, size: 18),
          label: const Text('Reset Checklist'),
          style: TextButton.styleFrom(foregroundColor: brown),
        ),
      ],
    );
  }
}

import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_application_1/models/rider_profile.dart';
import 'package:shared_preferences/shared_preferences.dart';

class PreTripInspectionScreen extends StatefulWidget {
  const PreTripInspectionScreen({super.key});

  @override
  State<PreTripInspectionScreen> createState() =>
      _PreTripInspectionScreenState();
}

class _PreTripInspectionScreenState extends State<PreTripInspectionScreen> {
  static const Color _maroon = Color(0xFF671B1D);
  static const Color _brown = Color(0xFF9E7161);
  static const Color _cream = Color(0xFFFFF8E7);
  static const Color _surface = Color(0xFFFFFDF5);
  static const Color _border = Color(0xFFE8D6C3);

  final Map<String, bool> _checks = {
    'Brakes and parking brake': false,
    'Tires, wheels, and spare tire': false,
    'Headlights, brake lights, and signals': false,
    'Engine, oil, coolant, and visible leaks': false,
    'Fuel level and battery': false,
    'Mirrors, windshield, and wipers': false,
    'Horn and seatbelts': false,
    'Doors, body, and cargo restraints': false,
    'First aid kit and warning triangle': false,
    'Registration and required documents': false,
  };

  Map<String, dynamic>? _report;
  bool _loading = true;
  bool _saving = false;

  int get _checkedCount => _checks.values.where((checked) => checked).length;
  bool get _allChecked => _checkedCount == _checks.length;
  String get _storageKey {
    final riderKey = RiderProfile.riderId.trim().isNotEmpty
        ? RiderProfile.riderId.trim()
        : RiderProfile.email.trim();
    return 'truck_pre_trip_report_${riderKey.isEmpty ? 'rider' : riderKey}';
  }

  String get _assignedHub => RiderProfile.logisticsProvider.trim().isEmpty
      ? 'Hub not assigned'
      : RiderProfile.logisticsProvider.trim();

  String get _deliveryArea {
    final parts = [
      RiderProfile.barangay,
      RiderProfile.municipality,
      RiderProfile.province,
    ].where((part) => part.trim().isNotEmpty).toList();
    return parts.isEmpty ? 'Area not assigned' : parts.join(', ');
  }

  @override
  void initState() {
    super.initState();
    _loadReport();
  }

  Future<void> _loadReport() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString(_storageKey);
    if (!mounted) return;
    setState(() {
      if (saved != null) {
        try {
          _report = jsonDecode(saved) as Map<String, dynamic>;
          if (_report?['status'] == 'Passed') {
            for (final key in _checks.keys) {
              _checks[key] = true;
            }
          }
        } catch (_) {
          _report = null;
        }
      }
      _loading = false;
    });
  }

  Future<void> _submitReport({String? issue}) async {
    if (_saving) return;
    if (issue == null && !_allChecked) {
      _message('Complete every check before submitting a passed inspection.');
      return;
    }
    setState(() => _saving = true);
    final report = <String, dynamic>{
      'status': issue == null ? 'Passed' : 'Needs Attention',
      'submittedAt': DateTime.now().toIso8601String(),
      'checkedCount': _checkedCount,
      'itemCount': _checks.length,
      'issue': issue,
      'rider': RiderProfile.name,
      'riderId': RiderProfile.displayId,
      'truckPlate': RiderProfile.plateNumber,
      'hub': _assignedHub,
      'area': _deliveryArea,
    };
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_storageKey, jsonEncode(report));
      if (!mounted) return;
      setState(() => _report = report);
      _message(issue == null
          ? 'Pre-trip inspection report saved on this device.'
          : 'Vehicle issue report saved on this device. Notify Logistics before departure.');
    } catch (_) {
      if (mounted) {
        _message('Unable to save the report. Please try again.');
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _reportIssue() async {
    final controller = TextEditingController();
    final issue = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: _surface,
        title: const Text('Report a vehicle issue'),
        content: TextField(
          controller: controller,
          autofocus: true,
          minLines: 3,
          maxLines: 5,
          decoration: const InputDecoration(
            hintText: 'Describe the defect or safety concern',
            border: OutlineInputBorder(),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              final value = controller.text.trim();
              if (value.isNotEmpty) Navigator.pop(dialogContext, value);
            },
            child: const Text('Save report'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (issue != null && mounted) await _submitReport(issue: issue);
  }

  Future<void> _startNewInspection() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_storageKey);
    if (!mounted) return;
    setState(() {
      _report = null;
      for (final key in _checks.keys) {
        _checks[key] = false;
      }
    });
  }

  void _message(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _cream,
      appBar: AppBar(
        backgroundColor: _maroon,
        foregroundColor: Colors.white,
        title: const Text('Pre-Trip Inspection'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: _maroon))
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 18, 16, 110),
              children: [
                _assignmentCard(),
                const SizedBox(height: 18),
                if (_report != null) ...[
                  _reportCard(),
                  const SizedBox(height: 18),
                ],
                _checklistCard(),
                const SizedBox(height: 14),
                _progressCard(),
                const SizedBox(height: 18),
                SizedBox(
                  height: 52,
                  child: FilledButton.icon(
                    onPressed: _saving || !_allChecked || _report != null
                        ? null
                        : () => _submitReport(),
                    icon: _saving
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.assignment_turned_in_outlined),
                    label: const Text('Submit inspection report'),
                    style: FilledButton.styleFrom(
                      backgroundColor: _maroon,
                      foregroundColor: Colors.white,
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                OutlinedButton.icon(
                  onPressed: _saving ? null : _reportIssue,
                  icon: const Icon(Icons.report_gmailerrorred_outlined),
                  label: const Text('Report a defect or safety issue'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: _maroon,
                    side: const BorderSide(color: _maroon),
                    minimumSize: const Size.fromHeight(50),
                  ),
                ),
                if (_report != null) ...[
                  const SizedBox(height: 8),
                  TextButton.icon(
                    onPressed: _startNewInspection,
                    icon: const Icon(Icons.refresh_rounded),
                    label: const Text('Start a new inspection'),
                    style: TextButton.styleFrom(foregroundColor: _brown),
                  ),
                ],
                const SizedBox(height: 10),
                const Text(
                  'Reports are saved on this device. If an issue is found, contact Logistics and do not start the trip until it is cleared.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: _brown, fontSize: 12, height: 1.45),
                ),
              ],
            ),
    );
  }

  Widget _assignmentCard() => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      color: _maroon,
      borderRadius: BorderRadius.circular(20),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'TODAY’S VEHICLE CHECK',
          style: TextStyle(
            color: Color(0xFFF0D6BE),
            fontSize: 11,
            fontWeight: FontWeight.bold,
            letterSpacing: 1.1,
          ),
        ),
        const SizedBox(height: 8),
        Text(
          RiderProfile.plateNumber.trim().isEmpty
              ? 'Truck assignment pending'
              : 'Truck • ${RiderProfile.plateNumber}',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 21,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 14),
        _assignmentLine(Icons.warehouse_outlined, 'Hub', _assignedHub),
        const SizedBox(height: 8),
        _assignmentLine(Icons.place_outlined, 'Delivery area', _deliveryArea),
      ],
    ),
  );

  Widget _assignmentLine(IconData icon, String label, String value) => Row(
    children: [
      Icon(icon, color: const Color(0xFFF0D6BE), size: 18),
      const SizedBox(width: 8),
      Text('$label: ', style: const TextStyle(color: Color(0xFFF0D6BE))),
      Expanded(
        child: Text(
          value,
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
        ),
      ),
    ],
  );

  Widget _checklistCard() => Container(
    decoration: BoxDecoration(
      color: _surface,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: _border),
    ),
    child: Column(
      children: [
        const ListTile(
          leading: Icon(Icons.fact_check_outlined, color: _maroon),
          title: Text('Safety checklist', style: TextStyle(fontWeight: FontWeight.bold)),
          subtitle: Text('Check each item after physically inspecting it.'),
        ),
        const Divider(height: 1, color: _border),
        ..._checks.entries.map((entry) => CheckboxListTile(
          value: entry.value,
          onChanged: _report == null
              ? (value) => setState(() => _checks[entry.key] = value ?? false)
              : null,
          activeColor: _maroon,
          controlAffinity: ListTileControlAffinity.leading,
          title: Text(entry.key, style: const TextStyle(fontSize: 14)),
          dense: true,
        )),
      ],
    ),
  );

  Widget _progressCard() => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: _surface,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: _border),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Expanded(
              child: Text('Inspection progress', style: TextStyle(fontWeight: FontWeight.bold, color: _maroon)),
            ),
            Text('$_checkedCount/${_checks.length}', style: const TextStyle(fontWeight: FontWeight.bold, color: _maroon)),
          ],
        ),
        const SizedBox(height: 10),
        LinearProgressIndicator(
          value: _checkedCount / _checks.length,
          color: _maroon,
          backgroundColor: _border,
          minHeight: 8,
          borderRadius: BorderRadius.circular(8),
        ),
      ],
    ),
  );

  Widget _reportCard() {
    final passed = _report?['status'] == 'Passed';
    final submittedAt = DateTime.tryParse(_report?['submittedAt']?.toString() ?? '');
    final statusColor = passed ? Colors.green.shade700 : Colors.red.shade700;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: passed ? const Color(0xFFEAF4E7) : const Color(0xFFFFECE7),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: passed ? Colors.green.shade200 : Colors.red.shade200),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(passed ? Icons.verified_outlined : Icons.warning_amber_rounded, color: statusColor),
              const SizedBox(width: 8),
              Expanded(child: Text('Inspection report • ${_report?['status']}', style: TextStyle(color: statusColor, fontWeight: FontWeight.bold, fontSize: 16))),
            ],
          ),
          const SizedBox(height: 10),
          Text('${_report?['checkedCount']}/${_report?['itemCount']} items checked'),
          if (submittedAt != null) ...[
            const SizedBox(height: 4),
            Text('Submitted ${submittedAt.toLocal()}'),
          ],
          if (_report?['issue'] != null) ...[
            const SizedBox(height: 8),
            Text('Issue: ${_report?['issue']}', style: const TextStyle(fontWeight: FontWeight.w600)),
          ],
        ],
      ),
    );
  }
}

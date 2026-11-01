import 'package:flutter/material.dart';

class AdminRidersScreen extends StatefulWidget {
  const AdminRidersScreen({super.key});

  @override
  State<AdminRidersScreen> createState() => _AdminRidersScreenState();
}

class _AdminRidersScreenState extends State<AdminRidersScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color cream = Color(0xFFE0CFC2);
  static const Color background = Color(0xFFF7F3F1);

  static const Color success = Color(0xFF3F7D58);
  static const Color warning = Color(0xFFB7791F);
  static const Color danger = Color(0xFFB3261E);

  // Room for the floating nav bar at the bottom of the dashboard.
  static const double _navClearance = 130;

  String _searchText = '';

  // Demo rider profiles. Account status is managed by Logistics.
  final List<Map<String, dynamic>> riders = [
    {
      'name': 'Earl Chumacera',
      'id': 'RID-001',
      'email': 'earl@lumear.com',
      'phone': '0912 345 6789',
      'status': 'Active',
    },
    {
      'name': 'Juan Dela Cruz',
      'id': 'RID-002',
      'email': 'juan@lumear.com',
      'phone': '0917 555 2011',
      'status': 'Active',
    },
    {
      'name': 'Pedro Santos',
      'id': 'RID-003',
      'email': 'pedro@lumear.com',
      'phone': '0998 332 2211',
      'status': 'Suspended',
    },
    {
      'name': 'Mark Reyes',
      'id': 'RID-004',
      'email': 'mark@lumear.com',
      'phone': '0922 111 2233',
      'status': 'Terminated',
    },
  ];

  @override
  Widget build(BuildContext context) {
    final List<Map<String, dynamic>> filteredRiders = riders.where((rider) {
      final String search = _searchText.toLowerCase();

      return rider['name'].toString().toLowerCase().contains(search) ||
          rider['id'].toString().toLowerCase().contains(search) ||
          rider['email'].toString().toLowerCase().contains(search);
    }).toList();

    return Container(
      color: background,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Rider Information',
                  style: TextStyle(
                    color: darkMaroon,
                    fontSize: 20,
                    fontWeight: FontWeight.w700,
                  ),
                ),

                const SizedBox(height: 4),

                const Text(
                  'View rider account and contact details.',
                  style: TextStyle(color: brownBeige, fontSize: 12),
                ),

                const SizedBox(height: 18),

                TextField(
                  onChanged: (value) {
                    setState(() {
                      _searchText = value;
                    });
                  },
                  decoration: InputDecoration(
                    hintText: 'Search name, rider ID or email...',
                    prefixIcon: const Icon(Icons.search_rounded),
                    filled: true,
                    fillColor: Colors.white,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(18),
                      borderSide: BorderSide.none,
                    ),
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 10),

          Expanded(
            child: filteredRiders.isEmpty
                ? const Center(
                    child: Text(
                      'No riders found.',
                      style: TextStyle(color: brownBeige),
                    ),
                  )
                : ListView.builder(
                    padding: EdgeInsets.fromLTRB(16, 4, 16, _navClearance),
                    itemCount: filteredRiders.length,
                    itemBuilder: (context, index) {
                      return _riderCard(filteredRiders[index]);
                    },
                  ),
          ),
        ],
      ),
    );
  }

  Widget _riderCard(Map<String, dynamic> rider) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: () {
          _showRiderDetails(rider);
        },
        child: Padding(
          padding: const EdgeInsets.all(15),
          child: Row(
            children: [
              Stack(
                children: [
                  CircleAvatar(
                    radius: 24,
                    backgroundColor: cream,
                    child: Text(
                      rider['name'].toString().substring(0, 1),
                      style: const TextStyle(
                        color: darkMaroon,
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),

              const SizedBox(width: 13),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            rider['name'],
                            style: const TextStyle(
                              color: darkMaroon,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 2),

                    Text(
                      rider['id'],
                      style: const TextStyle(color: brownBeige, fontSize: 10.5),
                    ),

                    const SizedBox(height: 4),

                    _statusText(rider['status']),
                  ],
                ),
              ),

              const Icon(Icons.chevron_right_rounded, color: brownBeige),
            ],
          ),
        ),
      ),
    );
  }

  Widget _statusText(String status) {
    Color color = success;

    if (status == 'Suspended') {
      color = warning;
    }

    if (status == 'Terminated') {
      color = danger;
    }

    return Text(
      status,
      style: TextStyle(
        color: color,
        fontSize: 10.5,
        fontWeight: FontWeight.w700,
      ),
    );
  }

  // ============================================================
  // RIDER DETAILS (view only)
  // ============================================================

  void _showRiderDetails(Map<String, dynamic> rider) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (sheetContext) {
        return Container(
          height: MediaQuery.sizeOf(sheetContext).height * 0.92,
          decoration: const BoxDecoration(
            color: background,
            borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
          ),
          child: Column(
            children: [
              Container(
                margin: const EdgeInsets.only(top: 10),
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: brownBeige.withValues(alpha: 0.35),
                  borderRadius: BorderRadius.circular(4),
                ),
              ),

              Expanded(
                child: ListView(
                  padding: const EdgeInsets.all(20),
                  children: [
                    Row(
                      children: [
                        CircleAvatar(
                          radius: 28,
                          backgroundColor: cream,
                          child: Text(
                            rider['name'].toString().substring(0, 1),
                            style: const TextStyle(
                              color: darkMaroon,
                              fontSize: 20,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),

                        const SizedBox(width: 14),

                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                rider['name'],
                                style: const TextStyle(
                                  color: darkMaroon,
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              Text(
                                rider['id'],
                                style: const TextStyle(color: brownBeige),
                              ),
                              const SizedBox(height: 3),
                              _statusText(rider['status']),
                            ],
                          ),
                        ),

                        IconButton(
                          onPressed: () {
                            Navigator.pop(sheetContext);
                          },
                          icon: const Icon(Icons.close_rounded),
                        ),
                      ],
                    ),

                    const SizedBox(height: 24),

                    _sectionHeading('Rider Information'),

                    _infoCard(
                      children: [
                        _infoRow(
                          Icons.person_outline_rounded,
                          'Full name',
                          rider['name'],
                        ),
                        _divider(),
                        _infoRow(Icons.email_outlined, 'Email', rider['email']),
                        _divider(),
                        _infoRow(Icons.phone_outlined, 'Phone', rider['phone']),
                        _divider(),
                        _infoRow(Icons.badge_outlined, 'Rider ID', rider['id']),
                        _divider(),
                        _infoRow(
                          Icons.verified_user_outlined,
                          'Account status',
                          rider['status'],
                        ),
                      ],
                    ),

                    const SizedBox(height: 25),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _sectionHeading(String title) {
    return Text(
      title,
      style: const TextStyle(
        color: darkMaroon,
        fontSize: 15,
        fontWeight: FontWeight.w700,
      ),
    );
  }

  Widget _infoCard({required List<Widget> children}) {
    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(children: children),
    );
  }

  Widget _infoRow(IconData icon, String label, String value) {
    return Row(
      children: [
        Container(
          width: 39,
          height: 39,
          decoration: BoxDecoration(
            color: cream,
            borderRadius: BorderRadius.circular(11),
          ),
          child: Icon(icon, color: darkMaroon, size: 19),
        ),

        const SizedBox(width: 11),

        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(color: brownBeige, fontSize: 9.5),
              ),
              Text(
                value,
                style: const TextStyle(
                  color: darkMaroon,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _divider() {
    return const Divider(height: 20, color: Color(0xFFF0E8E4));
  }
}

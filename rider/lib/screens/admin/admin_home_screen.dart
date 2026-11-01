import 'package:flutter/material.dart';

import '../../models/bug_report.dart';
import '../../services/bug_report_service.dart';

class AdminHomeScreen extends StatelessWidget {
  const AdminHomeScreen({super.key, this.onViewAllReports});

  final VoidCallback? onViewAllReports;

  static const Color darkMaroon = Color(0xFF62191C);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color cream = Color(0xFFE0CFC2);
  static const Color background = Color(0xFFF7F3F1);

  @override
  Widget build(BuildContext context) {
    return Container(
      color: background,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 18, 16, 130),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [darkMaroon, Color(0xFF471114)],
              ),
              borderRadius: BorderRadius.circular(24),
            ),
            child: const Row(
              children: [
                CircleAvatar(
                  radius: 28,
                  backgroundColor: cream,
                  child: Icon(
                    Icons.admin_panel_settings_rounded,
                    color: darkMaroon,
                    size: 31,
                  ),
                ),
                SizedBox(width: 15),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Good day, Admin',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      SizedBox(height: 5),
                      Text(
                        'Lumear Administration',
                        style: TextStyle(color: cream, fontSize: 12),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          const Text(
            'System Overview',
            style: TextStyle(
              color: darkMaroon,
              fontSize: 18,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 12),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 1.35,
            children: const [
              _SummaryCard(
                icon: Icons.groups_rounded,
                value: '24',
                title: 'Total Riders',
              ),
              _SummaryCard(
                icon: Icons.bug_report_rounded,
                value: '5',
                title: 'Bug Reports',
              ),
            ],
          ),
          const SizedBox(height: 26),
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Recent Bug Reports',
                  style: TextStyle(
                    color: darkMaroon,
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              TextButton.icon(
                onPressed: onViewAllReports,
                icon: const Icon(Icons.arrow_forward_rounded, size: 16),
                label: const Text('View all'),
                style: TextButton.styleFrom(foregroundColor: darkMaroon),
              ),
            ],
          ),
          const SizedBox(height: 8),
          AnimatedBuilder(
            animation: BugReportService.instance,
            builder: (context, _) {
              final List<BugReport> reports = BugReportService.instance.reports
                  .take(3)
                  .toList();

              if (reports.isEmpty) {
                return Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 22,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: const Column(
                    children: [
                      Icon(Icons.inbox_outlined, color: brownBeige, size: 28),
                      SizedBox(height: 8),
                      Text(
                        'No bug reports yet',
                        style: TextStyle(
                          color: darkMaroon,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                );
              }

              return Column(
                children: [
                  for (int index = 0; index < reports.length; index++) ...[
                    _RecentReportItem(report: reports[index]),
                    if (index < reports.length - 1) const SizedBox(height: 8),
                  ],
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

class _RecentReportItem extends StatelessWidget {
  const _RecentReportItem({required this.report});

  final BugReport report;

  @override
  Widget build(BuildContext context) {
    final Color statusColor = switch (report.status) {
      'Resolved' => const Color(0xFF2E7D32),
      'Under Review' => const Color(0xFFB7791F),
      _ => AdminHomeScreen.darkMaroon,
    };

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: AdminHomeScreen.cream,
              borderRadius: BorderRadius.circular(11),
            ),
            child: const Icon(
              Icons.bug_report_outlined,
              color: AdminHomeScreen.darkMaroon,
              size: 20,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  report.description,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AdminHomeScreen.darkMaroon,
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  '${report.affectedScreen} • ${report.riderName}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AdminHomeScreen.brownBeige,
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Text(
            report.status,
            style: TextStyle(
              color: statusColor,
              fontSize: 10,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({
    required this.icon,
    required this.value,
    required this.title,
  });

  final IconData icon;
  final String value;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: AdminHomeScreen.cream,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: AdminHomeScreen.darkMaroon, size: 21),
          ),
          const Spacer(),
          Text(
            value,
            style: const TextStyle(
              color: AdminHomeScreen.darkMaroon,
              fontSize: 22,
              fontWeight: FontWeight.w800,
            ),
          ),
          Text(
            title,
            style: const TextStyle(
              color: AdminHomeScreen.brownBeige,
              fontSize: 10.5,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

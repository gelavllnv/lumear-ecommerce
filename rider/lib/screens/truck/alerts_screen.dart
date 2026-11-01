import 'package:flutter/material.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Theme
// ─────────────────────────────────────────────────────────────────────────────

abstract final class AppColors {
  static const Color darkMaroon = Color(0xFF671B1D);
  static const Color maroon = Color(0xFF873632);
  static const Color brown = Color(0xFF9E7161);
  static const Color cream = Color(0xFFFFF8E7);
  static const Color paleCream = Color(0xFFFFFDF5);
  static const Color border = Color(0xFFE8D6C3);
}

// ─────────────────────────────────────────────────────────────────────────────
// Models
// ─────────────────────────────────────────────────────────────────────────────

enum AlertCategory {
  inspection('Inspection', Icons.fact_check_outlined),
  fuel('Fuel', Icons.local_gas_station_outlined),
  trip('Trip', Icons.local_shipping_outlined),
  maintenance('Maintenance', Icons.build_outlined),
  handover('Handover', Icons.inventory_2_outlined);

  const AlertCategory(this.label, this.icon);
  final String label;
  final IconData icon;
}

enum AlertFilter {
  all('All'),
  unread('Unread'),
  read('Read');

  const AlertFilter(this.label);
  final String label;
}

@immutable
class DriverAlert {
  const DriverAlert({
    required this.id,
    required this.title,
    required this.message,
    required this.category,
    required this.timestamp,
    this.isRead = false,
  });

  final int id;
  final String title;
  final String message;
  final AlertCategory category;
  final DateTime timestamp;
  final bool isRead;

  IconData get icon => category.icon;

  DriverAlert copyWith({bool? isRead}) => DriverAlert(
    id: id,
    title: title,
    message: message,
    category: category,
    timestamp: timestamp,
    isRead: isRead ?? this.isRead,
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Date helpers (no intl dependency)
// ─────────────────────────────────────────────────────────────────────────────

String _sectionFor(DateTime t) {
  final now = DateTime.now();
  final today = DateTime(now.year, now.month, now.day);
  final day = DateTime(t.year, t.month, t.day);
  final diff = today.difference(day).inDays;
  if (diff <= 0) return 'Today';
  if (diff == 1) return 'Yesterday';
  return 'Earlier';
}

String _formatClock(DateTime t) {
  final hour = t.hour % 12 == 0 ? 12 : t.hour % 12;
  final minute = t.minute.toString().padLeft(2, '0');
  final suffix = t.hour >= 12 ? 'PM' : 'AM';
  return '$hour:$minute $suffix';
}

String _formatStamp(DateTime t) {
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];
  final section = _sectionFor(t);
  if (section == 'Today' || section == 'Yesterday') {
    return '$section • ${_formatClock(t)}';
  }
  return '${months[t.month - 1]} ${t.day} • ${_formatClock(t)}';
}

List<DriverAlert> _sampleAlerts() {
  final n = DateTime.now();
  DateTime at(int daysAgo, int hour, int minute) =>
      DateTime(n.year, n.month, n.day - daysAgo, hour, minute);

  return [
    DriverAlert(
      id: 1,
      title: 'Pre-Trip Inspection',
      message: 'Complete your vehicle inspection before starting your assigned trip.',
      category: AlertCategory.inspection,
      timestamp: at(0, 7, 0),
    ),
    DriverAlert(
      id: 2,
      title: 'Check Fuel Level',
      message: 'Make sure your truck has enough fuel before travelling to the Logistics Hub.',
      category: AlertCategory.fuel,
      timestamp: at(0, 7, 15),
    ),
    DriverAlert(
      id: 3,
      title: 'Assigned Trip Reminder',
      message: 'Trip TRP-2301 is assigned to you. Review the trip details before departure.',
      category: AlertCategory.trip,
      timestamp: at(0, 7, 30),
    ),
    DriverAlert(
      id: 4,
      title: 'Tire Inspection',
      message:
          'Remember to inspect tire pressure and check for visible damage.',
      category: AlertCategory.maintenance,
      timestamp: at(1, 16, 0),
      isRead: true,
    ),
    DriverAlert(
      id: 5,
      title: 'Brake System Reminder',
      message:
          'Check the braking system before operating the assigned vehicle.',
      category: AlertCategory.maintenance,
      timestamp: at(1, 15, 30),
      isRead: true,
    ),
    DriverAlert(
      id: 6,
      title: 'Handover Update',
      message:
          'Review the handover instructions for your assigned Logistics Hub.',
      category: AlertCategory.handover,
      timestamp: at(9, 10, 0),
      isRead: true,
    ),
    DriverAlert(
      id: 7,
      title: 'Regular Maintenance',
      message:
          'Check whether your vehicle is due for its scheduled maintenance.',
      category: AlertCategory.maintenance,
      timestamp: at(10, 8, 0),
      isRead: true,
    ),
  ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Screen
// ─────────────────────────────────────────────────────────────────────────────

class AlertsScreen extends StatefulWidget {
  const AlertsScreen({super.key});

  @override
  State<AlertsScreen> createState() => _AlertsScreenState();
}

class _AlertsScreenState extends State<AlertsScreen> {
  List<DriverAlert> _alerts = _sampleAlerts();
  AlertFilter _filter = AlertFilter.all;
  AlertCategory? _category; // null = all categories

  // ── Derived data ───────────────────────────────────────────────────────────

  int get _unreadCount => _alerts.where((a) => !a.isRead).length;

  bool _matchesCategory(DriverAlert a) =>
      _category == null || a.category == _category;

  int _countFor(AlertFilter filter) {
    final inCategory = _alerts.where(_matchesCategory);
    return switch (filter) {
      AlertFilter.all => inCategory.length,
      AlertFilter.unread => inCategory.where((a) => !a.isRead).length,
      AlertFilter.read => inCategory.where((a) => a.isRead).length,
    };
  }

  List<DriverAlert> get _visibleAlerts {
    final list = _alerts.where((a) {
      final matchesStatus = switch (_filter) {
        AlertFilter.all => true,
        AlertFilter.unread => !a.isRead,
        AlertFilter.read => a.isRead,
      };
      return matchesStatus && _matchesCategory(a);
    }).toList()..sort((a, b) => b.timestamp.compareTo(a.timestamp));
    return list;
  }

  /// Flattens alerts into a list of section header strings and alerts.
  List<Object> _withSectionHeaders(List<DriverAlert> alerts) {
    final entries = <Object>[];
    String? lastSection;
    for (final alert in alerts) {
      final section = _sectionFor(alert.timestamp);
      if (section != lastSection) {
        entries.add(section);
        lastSection = section;
      }
      entries.add(alert);
    }
    return entries;
  }

  // ── Actions ────────────────────────────────────────────────────────────────

  void _showMessage(
    String text, {
    String? actionLabel,
    VoidCallback? onAction,
  }) {
    final messenger = ScaffoldMessenger.of(context);
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(text),
          backgroundColor: AppColors.darkMaroon,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          action: actionLabel == null
              ? null
              : SnackBarAction(
                  label: actionLabel,
                  textColor: AppColors.cream,
                  onPressed: onAction ?? () {},
                ),
        ),
      );
  }

  void _setRead(int id, bool isRead) {
    setState(() {
      _alerts = [
        for (final a in _alerts) a.id == id ? a.copyWith(isRead: isRead) : a,
      ];
    });
  }

  void _markAllAsRead() {
    setState(() {
      _alerts = [for (final a in _alerts) a.copyWith(isRead: true)];
    });
    _showMessage('All alerts marked as read.');
  }

  void _deleteAlert(DriverAlert alert) {
    setState(() {
      _alerts = _alerts.where((a) => a.id != alert.id).toList();
    });
    _showMessage(
      'Alert removed.',
      actionLabel: 'Undo',
      onAction: () => setState(() => _alerts = [..._alerts, alert]),
    );
  }

  void _openAlert(DriverAlert alert) {
    if (!alert.isRead) _setRead(alert.id, true);

    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: AppColors.paleCream,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        icon: Container(
          width: 58,
          height: 58,
          decoration: BoxDecoration(
            color: AppColors.cream,
            borderRadius: BorderRadius.circular(16),
          ),
          child: Icon(alert.icon, color: AppColors.maroon, size: 30),
        ),
        title: Text(
          alert.title,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: AppColors.darkMaroon,
            fontWeight: FontWeight.bold,
          ),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: AppColors.cream,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                alert.category.label,
                style: const TextStyle(
                  color: AppColors.maroon,
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            const SizedBox(height: 12),
            Text(
              alert.message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.black87, height: 1.5),
            ),
            const SizedBox(height: 14),
            Text(
              _formatStamp(alert.timestamp),
              style: const TextStyle(color: AppColors.brown, fontSize: 12),
            ),
          ],
        ),
        actionsPadding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
        actions: [
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () {
                    _setRead(alert.id, false);
                    Navigator.pop(dialogContext);
                  },
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.darkMaroon,
                    side: const BorderSide(color: AppColors.border),
                    padding: const EdgeInsets.symmetric(vertical: 13),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: const Text('Mark unread'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: ElevatedButton(
                  onPressed: () => Navigator.pop(dialogContext),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.darkMaroon,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 13),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: const Text('Close'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // ── Build ──────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final visible = _visibleAlerts;
    final entries = _withSectionHeaders(visible);

    return Scaffold(
      backgroundColor: AppColors.cream,
      appBar: AppBar(
        backgroundColor: AppColors.darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          'Alerts & Reminders',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        actions: [
          IconButton(
            tooltip: 'Mark all as read',
            onPressed: _unreadCount == 0 ? null : _markAllAsRead,
            icon: const Icon(Icons.done_all_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: Column(
          children: [
            _SummaryCard(unreadCount: _unreadCount),
            _StatusFilterBar(
              selected: _filter,
              countFor: _countFor,
              onSelected: (f) => setState(() => _filter = f),
            ),
            _CategoryChips(
              selected: _category,
              onSelected: (c) => setState(() => _category = c),
            ),
            Expanded(
              child: visible.isEmpty
                  ? _EmptyState(filter: _filter, category: _category)
                  : ListView.builder(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
                      itemCount: entries.length,
                      itemBuilder: (context, index) {
                        final entry = entries[index];
                        if (entry is String) return _SectionHeader(entry);
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _buildDismissible(entry as DriverAlert),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }

  /// Swipe right: toggle read/unread. Swipe left: delete (with undo).
  Widget _buildDismissible(DriverAlert alert) {
    return Dismissible(
      key: ValueKey(alert.id),
      background: _SwipeBackground(
        alignment: Alignment.centerLeft,
        color: AppColors.brown,
        icon: alert.isRead
            ? Icons.mark_email_unread_outlined
            : Icons.done_rounded,
        label: alert.isRead ? 'Mark unread' : 'Mark read',
      ),
      secondaryBackground: _SwipeBackground(
        alignment: Alignment.centerRight,
        color: Colors.red.shade700,
        icon: Icons.delete_outline_rounded,
        label: 'Delete',
      ),
      confirmDismiss: (direction) async {
        if (direction == DismissDirection.startToEnd) {
          _setRead(alert.id, !alert.isRead);
          return false; // keep the card, we only toggled its state
        }
        return true;
      },
      onDismissed: (_) => _deleteAlert(alert),
      child: _AlertCard(alert: alert, onTap: () => _openAlert(alert)),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Widgets
// ─────────────────────────────────────────────────────────────────────────────

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.unreadCount});

  final int unreadCount;

  @override
  Widget build(BuildContext context) {
    final headline = unreadCount == 0
        ? 'You are all caught up!'
        : '$unreadCount unread alert${unreadCount == 1 ? '' : 's'}';

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.fromLTRB(16, 16, 16, 12),
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.darkMaroon,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(
              unreadCount == 0
                  ? Icons.notifications_none_rounded
                  : Icons.notifications_active_outlined,
              color: AppColors.cream,
              size: 30,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Driver Notifications',
                  style: TextStyle(color: AppColors.cream, fontSize: 13),
                ),
                const SizedBox(height: 5),
                AnimatedSwitcher(
                  duration: const Duration(milliseconds: 200),
                  child: Text(
                    headline,
                    key: ValueKey(headline),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                const SizedBox(height: 5),
                const Text(
                  'Stay informed and drive safely.',
                  style: TextStyle(color: AppColors.cream, fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusFilterBar extends StatelessWidget {
  const _StatusFilterBar({
    required this.selected,
    required this.countFor,
    required this.onSelected,
  });

  final AlertFilter selected;
  final int Function(AlertFilter) countFor;
  final ValueChanged<AlertFilter> onSelected;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
      child: Row(
        children: [
          for (final filter in AlertFilter.values)
            Expanded(
              child: Padding(
                padding: EdgeInsets.only(
                  right: filter == AlertFilter.values.last ? 0 : 8,
                ),
                child: _StatusTab(
                  label: filter.label,
                  count: countFor(filter),
                  isSelected: selected == filter,
                  onTap: () => onSelected(filter),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _StatusTab extends StatelessWidget {
  const _StatusTab({
    required this.label,
    required this.count,
    required this.isSelected,
    required this.onTap,
  });

  final String label;
  final int count;
  final bool isSelected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: isSelected,
      label: '$label, $count',
      child: Material(
        color: isSelected ? AppColors.darkMaroon : AppColors.paleCream,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(
            color: isSelected ? AppColors.darkMaroon : AppColors.border,
          ),
        ),
        child: InkWell(
          onTap: onTap,
          customBorder: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Column(
              children: [
                Text(
                  label,
                  style: TextStyle(
                    color: isSelected ? Colors.white : AppColors.darkMaroon,
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '$count',
                  style: TextStyle(
                    color: isSelected ? AppColors.cream : AppColors.brown,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _CategoryChips extends StatelessWidget {
  const _CategoryChips({required this.selected, required this.onSelected});

  final AlertCategory? selected;
  final ValueChanged<AlertCategory?> onSelected;

  @override
  Widget build(BuildContext context) {
    Widget chip({
      required String label,
      required IconData icon,
      required bool isSelected,
      required VoidCallback onTap,
    }) {
      return Padding(
        padding: const EdgeInsets.only(right: 8),
        child: ChoiceChip(
          avatar: Icon(
            icon,
            size: 16,
            color: isSelected ? Colors.white : AppColors.brown,
          ),
          label: Text(label),
          selected: isSelected,
          showCheckmark: false,
          onSelected: (_) => onTap(),
          backgroundColor: AppColors.paleCream,
          selectedColor: AppColors.maroon,
          side: BorderSide(
            color: isSelected ? AppColors.maroon : AppColors.border,
          ),
          labelStyle: TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w600,
            color: isSelected ? Colors.white : AppColors.darkMaroon,
          ),
        ),
      );
    }

    return SizedBox(
      height: 44,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        children: [
          chip(
            label: 'All types',
            icon: Icons.apps_rounded,
            isSelected: selected == null,
            onTap: () => onSelected(null),
          ),
          for (final category in AlertCategory.values)
            chip(
              label: category.label,
              icon: category.icon,
              isSelected: selected == category,
              // Tapping the active chip again clears the category filter.
              onTap: () => onSelected(selected == category ? null : category),
            ),
        ],
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 8, 4, 10),
      child: Text(
        label.toUpperCase(),
        style: const TextStyle(
          color: AppColors.brown,
          fontSize: 11,
          fontWeight: FontWeight.bold,
          letterSpacing: 1.1,
        ),
      ),
    );
  }
}

class _SwipeBackground extends StatelessWidget {
  const _SwipeBackground({
    required this.alignment,
    required this.color,
    required this.icon,
    required this.label,
  });

  final Alignment alignment;
  final Color color;
  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      alignment: alignment,
      padding: const EdgeInsets.symmetric(horizontal: 22),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, color: Colors.white, size: 26),
          const SizedBox(height: 2),
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 10,
              fontWeight: FontWeight.bold,
            ),
          ),
        ],
      ),
    );
  }
}

class _AlertCard extends StatelessWidget {
  const _AlertCard({required this.alert, required this.onTap});

  final DriverAlert alert;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isRead = alert.isRead;
    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(18),
      side: BorderSide(
        color: isRead
            ? AppColors.border
            : AppColors.maroon.withValues(alpha: 0.5),
        width: isRead ? 1 : 1.4,
      ),
    );

    return Semantics(
      button: true,
      label:
          '${isRead ? 'Read' : 'Unread'} alert. ${alert.title}. '
          '${alert.message}. ${_formatStamp(alert.timestamp)}',
      excludeSemantics: true,
      child: Material(
        color: AppColors.paleCream,
        shape: shape,
        child: InkWell(
          onTap: onTap,
          customBorder: shape,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: isRead
                        ? AppColors.cream
                        : AppColors.maroon.withValues(alpha: 0.10),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(
                    alert.icon,
                    color: isRead ? AppColors.brown : AppColors.maroon,
                    size: 24,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Text(
                              alert.title,
                              style: TextStyle(
                                color: AppColors.darkMaroon,
                                fontSize: 14,
                                fontWeight: isRead
                                    ? FontWeight.w600
                                    : FontWeight.bold,
                              ),
                            ),
                          ),
                          if (!isRead)
                            Container(
                              width: 9,
                              height: 9,
                              margin: const EdgeInsets.only(top: 5, left: 6),
                              decoration: const BoxDecoration(
                                color: AppColors.maroon,
                                shape: BoxShape.circle,
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        alert.message,
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.black54,
                          fontSize: 12,
                          height: 1.45,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          const Icon(
                            Icons.access_time_rounded,
                            color: AppColors.brown,
                            size: 14,
                          ),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              _formatStamp(alert.timestamp),
                              style: const TextStyle(
                                color: AppColors.brown,
                                fontSize: 11,
                              ),
                            ),
                          ),
                          Text(
                            alert.category.label,
                            style: const TextStyle(
                              color: AppColors.brown,
                              fontSize: 10,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.filter, required this.category});

  final AlertFilter filter;
  final AlertCategory? category;

  @override
  Widget build(BuildContext context) {
    final scope = category == null
        ? ''
        : ' in ${category!.label.toLowerCase()}';

    final message = switch (filter) {
      AlertFilter.unread => 'You have no unread alerts$scope.',
      AlertFilter.read => 'No read alerts to display$scope.',
      AlertFilter.all => 'There are no alerts$scope right now.',
    };

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(30),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 86,
              height: 86,
              decoration: const BoxDecoration(
                color: AppColors.paleCream,
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.notifications_none_rounded,
                color: AppColors.brown,
                size: 46,
              ),
            ),
            const SizedBox(height: 18),
            const Text(
              'All Clear!',
              style: TextStyle(
                color: AppColors.darkMaroon,
                fontSize: 20,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.black54, fontSize: 13),
            ),
          ],
        ),
      ),
    );
  }
}

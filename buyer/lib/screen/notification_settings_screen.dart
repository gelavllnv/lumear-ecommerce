import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../services/buyer_data.dart';
import '../services/demo_accounts.dart';

class NotificationSettingsScreen extends StatefulWidget {
  const NotificationSettingsScreen({super.key, required this.connected});
  final bool connected;
  static final Map<String, Map<String, bool>> previewPreferences = {};
  static bool previewAllows(String title) {
    final preferences =
        previewPreferences[DemoAccounts.current?.email ?? 'preview'];
    final value = title.toLowerCase();
    if (value.contains('security') || value.contains('password')) return true;
    final key =
        value.contains('offer') ||
            value.contains('promotion') ||
            value.contains('voucher')
        ? 'promotions'
        : value.contains('order') ||
              value.contains('payment') ||
              value.contains('delivery')
        ? 'order_updates'
        : 'general_updates';
    return preferences?[key] ?? true;
  }

  @override
  State<NotificationSettingsScreen> createState() =>
      _NotificationSettingsScreenState();
}

class _NotificationSettingsScreenState
    extends State<NotificationSettingsScreen> {
  Map<String, bool> values = {
    'order_updates': true,
    'promotions': true,
    'general_updates': true,
  };
  bool loading = true, saving = false, loadFailed = false;
  String? error, notice;
  String get demoId => DemoAccounts.current?.email ?? 'preview';
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
      loadFailed = false;
    });
    try {
      final result = widget.connected
          ? await ApiService.get('/notification-preferences')
          : NotificationSettingsScreen.previewPreferences[demoId] ?? values;
      if (!mounted) return;
      setState(
        () =>
            values = {for (final key in values.keys) key: result[key] == true},
      );
    } catch (e) {
      if (mounted) {
        setState(() {
          error = e.toString();
          loadFailed = true;
        });
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> save() async {
    setState(() {
      saving = true;
      error = null;
      notice = null;
    });
    try {
      if (widget.connected) {
        await ApiService.patch('/notification-preferences', body: values);
      } else {
        NotificationSettingsScreen.previewPreferences[demoId] = Map.of(values);
      }
      if (!mounted) return;
      setState(
        () => notice = widget.connected
            ? 'Notification preferences saved to your account.'
            : 'Preview preferences saved for this session.',
      );
      if (widget.connected) {
        try {
          await BuyerData.instance.refresh();
        } catch (_) {
          if (mounted) {
            setState(
              () => notice = 'Preferences saved. Reopen Notifications to refresh the list.',
            );
          }
        }
      }
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Notification Settings')),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : ListView(
            padding: const EdgeInsets.all(20),
            children: [
              Text(
                'Choose your updates',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              const Text(
                'Choose which optional updates appear in your in-app notification inbox. Security notices remain visible.',
              ),
              if (!widget.connected)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 12),
                  child: Text(
                    'Preview mode: preferences reset when the app restarts.',
                  ),
                ),
              for (final item in const [
                (
                  'order_updates',
                  'Order updates',
                  'Order, payment, and delivery status updates',
                ),
                (
                  'promotions',
                  'Promotions and offers',
                  'Vouchers, shop offers, and marketplace promotions',
                ),
                (
                  'general_updates',
                  'General updates',
                  'Other account and marketplace announcements',
                ),
              ])
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(item.$2),
                  subtitle: Text(item.$3),
                  value: values[item.$1]!,
                  onChanged: saving || loadFailed
                      ? null
                      : (v) => setState(() {
                          values[item.$1] = v;
                          notice = null;
                        }),
                ),
              const Divider(),
              const ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Icon(Icons.phone_android),
                title: Text('Phone push notifications'),
                subtitle: Text(
                  'Not available yet. These settings control the in-app inbox only.',
                ),
              ),
              if (error != null) ...[
                Text(
                  error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
                TextButton(
                  onPressed: saving ? null : (loadFailed ? load : save),
                  child: const Text('Retry'),
                ),
              ],
              if (notice != null)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: Text(notice!),
                ),
              FilledButton(
                onPressed: saving || loadFailed ? null : save,
                child: Text(saving ? 'Saving…' : 'Save preferences'),
              ),
            ],
          ),
  );
}

import 'package:flutter/material.dart';

import 'login_screen.dart';

const Color settingsMaroon = Color(0xFF671B1D);
const Color settingsBrown = Color(0xFF9E7161);
const Color settingsCream = Color(0xFFFFF8E7);
const Color settingsPaleCream = Color(0xFFFFFDF5);
const Color settingsBorder = Color(0xFFE8D6C3);

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: settingsCream,
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Settings',
              style: TextStyle(
                color: settingsMaroon,
                fontSize: 25,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'Manage your account and preferences.',
              style: TextStyle(color: settingsBrown, fontSize: 14),
            ),
            const SizedBox(height: 24),

            // Admin profile
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: settingsMaroon,
                borderRadius: BorderRadius.circular(18),
              ),
              child: const Row(
                children: [
                  CircleAvatar(
                    radius: 30,
                    backgroundColor: settingsPaleCream,
                    child: Icon(Icons.person, size: 35, color: settingsMaroon),
                  ),
                  SizedBox(width: 15),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Logistics Admin',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        SizedBox(height: 5),
                        Text(
                          'Administrator',
                          style: TextStyle(
                            color: Color(0xFFFFE5D6),
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Icon(Icons.verified_user_outlined, color: Colors.white),
                ],
              ),
            ),

            const SizedBox(height: 25),

            _sectionTitle('Account Information'),

            _settingsTile(
              icon: Icons.person_outline,
              title: 'Full Name',
              subtitle: 'Logistics Admin',
              onTap: () => _showInfo(
                context,
                'Full Name',
                'Connect this field to the admin account to edit it.',
              ),
            ),

            _settingsTile(
              icon: Icons.email_outlined,
              title: 'Email Address',
              subtitle: 'admin@lumear.com',
              onTap: () => _showInfo(
                context,
                'Email Address',
                'Connect this field to the admin account to edit it.',
              ),
            ),

            _settingsTile(
              icon: Icons.admin_panel_settings_outlined,
              title: 'Account Role',
              subtitle: 'Administrator',
              onTap: () => _showInfo(
                context,
                'Account Role',
                'Your current account role is Administrator.',
              ),
            ),

            const SizedBox(height: 20),

            _sectionTitle('Preferences'),

            _settingsTile(
              icon: Icons.notifications_outlined,
              title: 'Notifications',
              subtitle: 'View logistics notifications',
              onTap: () {
                Navigator.pushNamed(context, '/notifications');
              },
            ),

            _settingsTile(
              icon: Icons.lock_outline,
              title: 'Security',
              subtitle: 'Manage account security',
              onTap: () => _showInfo(
                context,
                'Security',
                'Password management can be connected to your authentication system.',
              ),
            ),

            const SizedBox(height: 24),

            // Account actions
            _sectionTitle('Account Actions'),

            Container(
              width: double.infinity,
              decoration: BoxDecoration(
                color: settingsPaleCream,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: const Color(0xFFE5B8B8)),
              ),
              child: ListTile(
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 8,
                ),
                leading: Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: Colors.red.withValues(alpha: 0.10),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.logout_rounded, color: Colors.red),
                ),
                title: const Text(
                  'Log Out',
                  style: TextStyle(
                    color: Colors.red,
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
                subtitle: const Text(
                  'Sign out of your administrator account',
                  style: TextStyle(color: settingsBrown, fontSize: 12),
                ),
                trailing: const Icon(Icons.chevron_right, color: Colors.red),
                onTap: () => _confirmLogout(context),
              ),
            ),

            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  // Section heading
  Widget _sectionTitle(String title) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Text(
        title,
        style: const TextStyle(
          color: settingsMaroon,
          fontSize: 18,
          fontWeight: FontWeight.bold,
        ),
      ),
    );
  }

  // Settings item
  Widget _settingsTile({
    required IconData icon,
    required String title,
    required String subtitle,
    VoidCallback? onTap,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: settingsPaleCream,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: settingsBorder),
      ),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 5),
        leading: Icon(icon, color: settingsMaroon),
        title: Text(
          title,
          style: const TextStyle(
            color: settingsMaroon,
            fontWeight: FontWeight.bold,
            fontSize: 14,
          ),
        ),
        subtitle: Text(
          subtitle,
          style: const TextStyle(color: settingsBrown, fontSize: 12),
        ),
        trailing: const Icon(Icons.chevron_right, color: settingsBrown),
        onTap: onTap,
      ),
    );
  }

  // Logout confirmation dialog
  void _confirmLogout(BuildContext context) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: settingsPaleCream,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        title: const Row(
          children: [
            Icon(Icons.logout_rounded, color: Colors.red),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                'Confirm Logout',
                style: TextStyle(
                  color: settingsMaroon,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ],
        ),
        content: const Text(
          'Are you sure you want to log out of your administrator account?',
          style: TextStyle(color: settingsBrown, height: 1.5),
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(dialogContext);
            },
            child: const Text('Cancel', style: TextStyle(color: settingsBrown)),
          ),
          ElevatedButton.icon(
            style: ElevatedButton.styleFrom(
              backgroundColor: settingsMaroon,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            icon: const Icon(Icons.logout, size: 18),
            label: const Text('Log Out'),
            onPressed: () {
              // Close the confirmation dialog.
              Navigator.pop(dialogContext);

              // Return to LoginScreen and clear the navigation history.
              Navigator.of(context).pushAndRemoveUntil(
                MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
                (route) => false,
              );
            },
          ),
        ],
      ),
    );
  }

  // Information dialog
  void _showInfo(BuildContext context, String title, String message) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: settingsPaleCream,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        title: Text(
          title,
          style: const TextStyle(
            color: settingsMaroon,
            fontWeight: FontWeight.bold,
          ),
        ),
        content: Text(
          message,
          style: const TextStyle(color: settingsBrown, height: 1.5),
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(dialogContext);
            },
            child: const Text('Close', style: TextStyle(color: settingsMaroon)),
          ),
        ],
      ),
    );
  }
}

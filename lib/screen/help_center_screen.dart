import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../services/chat_service.dart';
import 'chat_screen.dart';

class HelpCenterScreen extends StatefulWidget {
  const HelpCenterScreen({super.key, required this.connected});
  final bool connected;
  @override
  State<HelpCenterScreen> createState() => _HelpCenterScreenState();
}

class _HelpCenterScreenState extends State<HelpCenterScreen> {
  late final Future<List<dynamic>> articles = rootBundle
      .loadString('assets/data/lumear_help.json')
      .then((text) => jsonDecode(text) as List);
  String query = '';
  String category = 'All';
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Help Center')),
    body: FutureBuilder<List<dynamic>>(
      future: articles,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return const Center(
            child: Text(
              'Help content could not be loaded. Please reopen Help Center.',
            ),
          );
        }
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final all = snapshot.data!;
        final rows = all
            .where(
              (r) =>
                  (category == 'All' || r['category'] == category) &&
                  ('${r['question']} ${r['answer']} ${r['keywords']}'
                      .toLowerCase()
                      .contains(query.toLowerCase())),
            )
            .toList();
        return ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Text(
              'How can we help?',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 16),
            TextField(
              onChanged: (v) => setState(() => query = v),
              decoration: const InputDecoration(
                labelText: 'Search help',
                prefixIcon: Icon(Icons.search),
              ),
            ),
            const SizedBox(height: 16),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  for (final topic in [
                    'All',
                    ...all.map((r) => r['category'] as String).toSet(),
                  ])
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        label: Text(topic),
                        selected: category == topic,
                        onSelected: (_) => setState(() => category = topic),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            if (rows.isEmpty)
              const Padding(
                padding: EdgeInsets.all(20),
                child: Text('No matching articles. Try another keyword.'),
              ),
            for (final row in rows)
              Card(
                child: ExpansionTile(
                  key: ValueKey(row['id']),
                  title: Text(row['question'] as String),
                  childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
                  children: [
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Text(row['answer'] as String),
                    ),
                  ],
                ),
              ),
            const SizedBox(height: 20),
            FilledButton.icon(
              icon: const Icon(Icons.chat_bubble_outline),
              label: const Text('Ask Lumear assistant'),
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => ChatScreen(
                    service: ChatService(connected: widget.connected),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
            const Text(
              'For a specific order or delivery issue, open Orders or contact the seller in Messages. Never share passwords or verification codes.',
            ),
          ],
        );
      },
    ),
  );
}

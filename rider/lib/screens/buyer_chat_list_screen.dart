import 'package:flutter/material.dart';
import 'package:flutter_application_1/models/parcel_store.dart';

import 'buyer_chat_screen.dart';

/// Rider's inbox: the list of buyers the rider can message (like Messenger).
///
/// Each row shows the buyer's name, the order tracking number, the parcel
/// status, the last message, the time, and an unread badge. Tapping a row
/// opens [BuyerChatScreen] for that buyer.
///
/// By default it lists the rider's current parcel (from [ParcelStore]) plus
/// a few sample buyers ([includeDemoBuyers]). When you have a real list of
/// assigned parcels, pass it through [contacts] and set
/// [includeDemoBuyers] to false.
class BuyerChatListScreen extends StatefulWidget {
  final List<BuyerChatContact>? contacts;
  final bool includeDemoBuyers;

  const BuyerChatListScreen({
    super.key,
    this.contacts,
    this.includeDemoBuyers = true,
  });

  @override
  State<BuyerChatListScreen> createState() => _BuyerChatListScreenState();
}

class _BuyerChatListScreenState extends State<BuyerChatListScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color textMuted = Color(0xFF8B6F63);

  static const Color success = Color(0xFF2E7D32);
  static const Color warning = Color(0xFFC77700);
  static const Color danger = Color(0xFFB3261E);

  final TextEditingController _searchController = TextEditingController();
  String _query = '';

  @override
  void initState() {
    super.initState();

    if (widget.includeDemoBuyers) {
      BuyerChatStore.seedDemo();
    }

    _searchController.addListener(() {
      setState(() => _query = _searchController.text.trim().toLowerCase());
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  // ============================================
  // DATA
  // ============================================

  List<BuyerChatContact> _buildContacts() {
    final List<BuyerChatContact> result = [];
    final Set<String> seen = {};

    void add(BuyerChatContact contact) {
      if (seen.add(contact.trackingNumber)) result.add(contact);
    }

    if (widget.contacts != null) {
      widget.contacts!.forEach(add);
    } else {
      final ParcelData? current = ParcelStore.currentParcel.value;

      if (current != null) {
        add(
          BuyerChatContact(
            buyerName: current.recipient,
            trackingNumber: current.parcelNumber,
            parcelStatus: current.status,
          ),
        );
      }
    }

    if (widget.includeDemoBuyers) {
      BuyerChatStore.demoContacts.forEach(add);
    }

    return result;
  }

  DateTime? _lastTime(BuyerChatContact contact) {
    final thread = BuyerChatStore.threadFor(contact.trackingNumber);

    return thread.isEmpty ? null : thread.last.sentAt;
  }

  List<BuyerChatContact> _visibleContacts() {
    final entries = _buildContacts().asMap().entries.toList();

    entries.sort((a, b) {
      final DateTime? timeA = _lastTime(a.value);
      final DateTime? timeB = _lastTime(b.value);

      if (timeA == null && timeB == null) return a.key.compareTo(b.key);
      if (timeA == null) return 1;
      if (timeB == null) return -1;

      return timeB.compareTo(timeA);
    });

    final sorted = entries.map((entry) => entry.value);

    if (_query.isEmpty) return sorted.toList();

    return sorted.where((contact) {
      return contact.buyerName.toLowerCase().contains(_query) ||
          contact.trackingNumber.toLowerCase().contains(_query);
    }).toList();
  }

  Future<void> _openChat(BuyerChatContact contact) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => BuyerChatScreen(
          buyerName: contact.buyerName,
          trackingNumber: contact.trackingNumber,
          parcelStatus: contact.parcelStatus,
        ),
      ),
    );

    // Refresh previews / unread badges after coming back.
    if (mounted) setState(() {});
  }

  // ============================================
  // HELPERS
  // ============================================

  String _formatListTime(DateTime date) {
    final DateTime local = date.toLocal();
    final DateTime now = DateTime.now();

    final bool sameDay =
        local.year == now.year &&
        local.month == now.month &&
        local.day == now.day;

    if (sameDay) {
      final int hour12 = local.hour % 12 == 0 ? 12 : local.hour % 12;
      final String minute = local.minute.toString().padLeft(2, '0');
      final String period = local.hour >= 12 ? 'PM' : 'AM';

      return '$hour12:$minute $period';
    }

    final DateTime yesterday = now.subtract(const Duration(days: 1));

    if (local.year == yesterday.year &&
        local.month == yesterday.month &&
        local.day == yesterday.day) {
      return 'Yesterday';
    }

    return '${local.month}/${local.day}/${local.year}';
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'Pending':
        return warning;
      case 'Delivered':
        return success;
      case 'Delivery Failed':
        return danger;
      default:
        return maroon;
    }
  }

  // ============================================
  // BUILD
  // ============================================

  @override
  Widget build(BuildContext context) {
    final List<BuyerChatContact> contacts = _visibleContacts();

    return Scaffold(
      backgroundColor: cream,
      appBar: AppBar(
        backgroundColor: darkMaroon,
        foregroundColor: Colors.white,
        elevation: 0,
        title: const Text(
          'Buyer Chat',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
        ),
      ),
      body: Column(
        children: [
          _buildSearchBar(),
          Expanded(
            child: contacts.isEmpty
                ? _buildEmptyState()
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                    itemCount: contacts.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      return _buildBuyerTile(contacts[index]);
                    },
                  ),
          ),
        ],
      ),
    );
  }

  Widget _buildSearchBar() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 10),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14),
        decoration: BoxDecoration(
          color: surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: darkMaroon.withValues(alpha: 0.06)),
        ),
        child: Row(
          children: [
            const Icon(Icons.search_rounded, color: brownBeige, size: 21),
            const SizedBox(width: 8),
            Expanded(
              child: TextField(
                controller: _searchController,
                style: const TextStyle(color: darkMaroon, fontSize: 14),
                decoration: const InputDecoration(
                  hintText: 'Search buyer or tracking number',
                  hintStyle: TextStyle(color: textMuted, fontSize: 13),
                  border: InputBorder.none,
                  contentPadding: EdgeInsets.symmetric(vertical: 14),
                ),
              ),
            ),
            if (_query.isNotEmpty)
              GestureDetector(
                onTap: _searchController.clear,
                child: const Icon(
                  Icons.close_rounded,
                  color: textMuted,
                  size: 19,
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildBuyerTile(BuyerChatContact contact) {
    final thread = BuyerChatStore.threadFor(contact.trackingNumber);
    final RiderChatMessage? last = thread.isEmpty ? null : thread.last;
    final int unread = BuyerChatStore.unreadFor(contact.trackingNumber);
    final bool hasUnread = unread > 0;

    final String preview = last == null
        ? 'No messages yet. Tap to start chatting.'
        : (last.isRider ? 'You: ${last.text}' : last.text);

    final Color statusColor = _statusColor(contact.parcelStatus);

    return Material(
      color: surface,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: () => _openChat(contact),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: hasUnread
                  ? maroon.withValues(alpha: 0.3)
                  : darkMaroon.withValues(alpha: 0.05),
            ),
            boxShadow: [
              BoxShadow(
                color: darkMaroon.withValues(alpha: 0.05),
                blurRadius: 14,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Row(
            children: [
              CircleAvatar(
                radius: 26,
                backgroundColor: lightBeige,
                child: Text(
                  contact.buyerName.trim().isEmpty
                      ? '?'
                      : contact.buyerName.trim()[0].toUpperCase(),
                  style: const TextStyle(
                    color: darkMaroon,
                    fontWeight: FontWeight.w800,
                    fontSize: 19,
                  ),
                ),
              ),
              const SizedBox(width: 13),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            contact.buyerName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: darkMaroon,
                              fontSize: 14.5,
                              fontWeight: hasUnread
                                  ? FontWeight.w800
                                  : FontWeight.w700,
                            ),
                          ),
                        ),
                        if (last != null)
                          Text(
                            _formatListTime(last.sentAt),
                            style: TextStyle(
                              color: hasUnread ? maroon : textMuted,
                              fontSize: 10.5,
                              fontWeight: hasUnread
                                  ? FontWeight.w700
                                  : FontWeight.w500,
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 5),
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: darkMaroon.withValues(alpha: 0.07),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(
                                Icons.inventory_2_outlined,
                                size: 11,
                                color: darkMaroon,
                              ),
                              const SizedBox(width: 4),
                              Text(
                                contact.trackingNumber,
                                style: const TextStyle(
                                  color: darkMaroon,
                                  fontSize: 10.5,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 6),
                        Flexible(
                          child: Text(
                            contact.parcelStatus,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: statusColor,
                              fontSize: 10.5,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            preview,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: hasUnread ? darkMaroon : textMuted,
                              fontSize: 12,
                              fontWeight: hasUnread
                                  ? FontWeight.w700
                                  : FontWeight.w500,
                            ),
                          ),
                        ),
                        if (hasUnread) ...[
                          const SizedBox(width: 8),
                          Container(
                            constraints: const BoxConstraints(
                              minWidth: 20,
                              minHeight: 20,
                            ),
                            padding: const EdgeInsets.symmetric(horizontal: 6),
                            decoration: BoxDecoration(
                              color: maroon,
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Center(
                              child: Text(
                                unread > 9 ? '9+' : '$unread',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 10.5,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildEmptyState() {
    final bool searching = _query.isNotEmpty;

    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 36),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: const BoxDecoration(
                color: surface,
                shape: BoxShape.circle,
              ),
              child: Icon(
                searching
                    ? Icons.search_off_rounded
                    : Icons.chat_bubble_outline_rounded,
                color: brownBeige,
                size: 32,
              ),
            ),
            const SizedBox(height: 14),
            Text(
              searching ? 'No matching buyers' : 'No buyers yet',
              style: const TextStyle(
                color: darkMaroon,
                fontSize: 15.5,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 5),
            Text(
              searching
                  ? 'Try a different name or tracking number.'
                  : 'Scan a parcel and its buyer will show up here.',
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: textMuted,
                fontSize: 12.5,
                height: 1.45,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

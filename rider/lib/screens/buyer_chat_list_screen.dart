import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_application_1/services/rider_api_service.dart';

import 'buyer_chat_screen.dart';

/// Rider's inbox: the list of buyers the rider can message (like Messenger).
///
/// Each row shows the buyer's name, the order tracking number, the parcel
/// status, the last message, the time, and an unread badge. Tapping a row
/// opens [BuyerChatScreen] for that buyer.
///
/// Lists conversations created when Logistics assigns parcels to this rider.
class BuyerChatListScreen extends StatefulWidget {
  final List<BuyerChatContact>? contacts;

  const BuyerChatListScreen({super.key, this.contacts});

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
  Timer? _refreshTimer;
  List<BuyerChatContact> _contacts = [];
  Map<String, dynamic> _hubChat = {};
  bool _loading = true;
  String? _error;
  String _query = '';

  @override
  void initState() {
    super.initState();
    if (widget.contacts != null) {
      _contacts = widget.contacts!;
      _loading = false;
    } else {
      _refreshContacts();
    }
    _refreshTimer = Timer.periodic(const Duration(seconds: 8), (_) {
      if (mounted && !_loading) _refreshContacts();
    });

    _searchController.addListener(() {
      setState(() => _query = _searchController.text.trim().toLowerCase());
    });
  }

  Future<void> _refreshContacts() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    Map<String, dynamic> hubChat = _hubChat;
    try {
      hubChat = await RiderApiService.logisticsChat();
    } catch (_) {
      // Keep buyer chats available if the hub chat endpoint is temporarily unavailable.
    }

    try {
      final rows = await RiderApiService.riderChats();
      if (!mounted) return;
      setState(() {
        _hubChat = hubChat;
        _contacts = rows.map(BuyerChatContact.fromJson).toList();
        _loading = false;
      });
    } catch (error) {
      if (mounted) {
        setState(() {
          _hubChat = hubChat;
          _loading = false;
          _error = error.toString();
        });
      }
    }
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  // ============================================
  // DATA
  // ============================================

  List<BuyerChatContact> _buildContacts() {
    return _contacts;
  }

  DateTime? _lastTime(BuyerChatContact contact) {
    return contact.lastMessageAt;
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
          conversationId: contact.conversationId,
          buyerName: contact.buyerName,
          trackingNumber: contact.trackingNumber,
          parcelStatus: contact.parcelStatus,
        ),
      ),
    );

    // Refresh previews / unread badges after coming back.
    if (mounted) setState(() {});
  }

  Future<void> _confirmDeleteChat(BuyerChatContact contact) async {
    final int? conversationId = contact.conversationId;
    if (conversationId == null) return;

    final bool confirmed =
        await showDialog<bool>(
          context: context,
          builder: (dialogContext) => AlertDialog(
            title: const Text('Delete conversation?'),
            content: Text(
              'Remove all chats with ${contact.buyerName} from your Rider inbox? '
              'The buyer’s chat history will not be deleted.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext, false),
                child: const Text('Cancel'),
              ),
              TextButton(
                onPressed: () => Navigator.pop(dialogContext, true),
                style: TextButton.styleFrom(foregroundColor: danger),
                child: const Text('Delete'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed) return;

    try {
      await RiderApiService.deleteRiderChat(conversationId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Conversation removed from your inbox.')),
      );
      await _refreshContacts();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Unable to delete conversation: $error')),
      );
    }
  }

  Future<void> _openLogisticsChat() async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => BuyerChatScreen(
          buyerName: _hubName,
          trackingNumber: '',
          parcelStatus: 'Logistics',
          isLogisticsChat: true,
        ),
      ),
    );

    if (mounted) _refreshContacts();
  }

  String get _hubName =>
      _hubChat['hub_name']?.toString().trim().isNotEmpty == true
      ? _hubChat['hub_name'].toString()
      : 'Victoria Hub';

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
    switch (status.toLowerCase().replaceAll('_', ' ').trim()) {
      case 'pending':
      case 'assigned':
        return warning;
      case 'delivered':
        return success;
      case 'delivery failed':
      case 'failed':
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
          'Chats',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
        ),
        actions: [
          IconButton(
            onPressed: _loading ? null : _refreshContacts,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: Column(
        children: [
          _buildSearchBar(),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
              children: [
                _buildSectionHeader('Logistics'),
                _buildLogisticsTile(),
                _buildSectionHeader('Buyer Chats'),
                if (_loading)
                  const Padding(
                    padding: EdgeInsets.all(28),
                    child: Center(child: CircularProgressIndicator()),
                  )
                else if (_error != null)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 24),
                    child: Column(
                      children: [
                        Text(_error!, textAlign: TextAlign.center),
                        TextButton(
                          onPressed: _refreshContacts,
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  )
                else if (contacts.isEmpty)
                  _buildEmptyState()
                else
                  ...contacts.map(
                    (contact) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _buildBuyerTile(contact),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(String title) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(2, 10, 2, 10),
      child: Text(
        title,
        style: const TextStyle(
          color: darkMaroon,
          fontSize: 15,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }

  Widget _buildLogisticsTile() {
    final unread =
        int.tryParse(_hubChat['unread_count']?.toString() ?? '') ?? 0;
    final lastMessage = _hubChat['last_message']?.toString() ?? '';
    final lastMessageAt = DateTime.tryParse(
      _hubChat['last_message_at']?.toString() ?? '',
    );
    final matchesSearch =
        _query.isEmpty ||
        'logistics $_hubName victoria hub'.toLowerCase().contains(_query);
    if (!matchesSearch) return const SizedBox.shrink();

    return Material(
      color: surface,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: _openLogisticsChat,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: darkMaroon.withValues(alpha: 0.06)),
          ),
          child: Row(
            children: [
              const CircleAvatar(
                radius: 26,
                backgroundColor: lightBeige,
                child: Icon(Icons.local_shipping_outlined, color: darkMaroon),
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
                            _hubName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: darkMaroon,
                              fontSize: 14.5,
                              fontWeight: unread > 0
                                  ? FontWeight.w800
                                  : FontWeight.w700,
                            ),
                          ),
                        ),
                        if (lastMessageAt != null)
                          Text(
                            _formatListTime(lastMessageAt),
                            style: const TextStyle(
                              color: textMuted,
                              fontSize: 10.5,
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 5),
                    Text(
                      lastMessage.isEmpty ? 'Logistics chat' : lastMessage,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: textMuted, fontSize: 12),
                    ),
                  ],
                ),
              ),
              if (unread > 0) ...[
                const SizedBox(width: 8),
                CircleAvatar(
                  radius: 11,
                  backgroundColor: maroon,
                  child: Text(
                    unread > 9 ? '9+' : '$unread',
                    style: const TextStyle(color: Colors.white, fontSize: 10),
                  ),
                ),
              ],
            ],
          ),
        ),
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
    final int unread = contact.unreadCount;
    final bool hasUnread = unread > 0;
    final String preview = contact.lastMessage.isEmpty
        ? 'Start your delivery conversation.'
        : contact.lastMessage;

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
                        if (contact.lastMessageAt != null)
                          Text(
                            _formatListTime(contact.lastMessageAt!),
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
              IconButton(
                tooltip: 'Delete conversation',
                onPressed: () => _confirmDeleteChat(contact),
                icon: const Icon(Icons.delete_outline_rounded),
                color: danger,
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
              searching ? 'Try a different name or tracking number.' : 'Assigned deliveries will appear here so you can message the buyer.',
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

import 'package:flutter/material.dart';
import 'package:flutter_application_1/services/rider_api_service.dart';

import 'dart:async';

/// A single chat message between the rider and the buyer.
class RiderChatMessage {
  final String text;

  /// true = sent by the rider (this app's user), false = sent by the buyer.
  final bool isRider;
  final DateTime sentAt;

  const RiderChatMessage({
    required this.text,
    required this.isRider,
    required this.sentAt,
  });
}

/// A buyer the rider can chat with (one per parcel / order).
class BuyerChatContact {
  final int? conversationId;
  final int? buyerId;
  final String buyerName;
  final String trackingNumber;
  final String parcelStatus;
  final String lastMessage;
  final DateTime? lastMessageAt;
  final int unreadCount;

  const BuyerChatContact({
    this.conversationId,
    this.buyerId,
    required this.buyerName,
    required this.trackingNumber,
    required this.parcelStatus,
    this.lastMessage = '',
    this.lastMessageAt,
    this.unreadCount = 0,
  });

  factory BuyerChatContact.fromJson(Map<String, dynamic> json) =>
      BuyerChatContact(
        conversationId: int.tryParse(json['id']?.toString() ?? ''),
        buyerId: int.tryParse(json['buyer_id']?.toString() ?? ''),
        buyerName: json['buyer_name']?.toString() ?? 'Buyer',
        trackingNumber: json['tracking_code']?.toString() ?? '',
        parcelStatus: json['status']?.toString() ?? 'assigned',
        lastMessage: json['last_message']?.toString() ?? '',
        lastMessageAt: DateTime.tryParse(
          json['last_message_at']?.toString() ?? '',
        ),
        unreadCount: int.tryParse(json['unread_count']?.toString() ?? '') ?? 0,
      );
}

/// Chat between the rider (the person using this app) and the buyer of the
/// parcels assigned to this rider. Messages persist in Laravel and are shared
/// with the buyer's Flutter app.
///
/// Rider messages appear on the right, buyer messages on the left.
///
/// Chat UI for buyers with active delivery assignments.
class BuyerChatScreen extends StatefulWidget {
  final int? conversationId;
  final String buyerName;
  final String trackingNumber;
  final String parcelStatus;
  final bool isLogisticsChat;

  const BuyerChatScreen({
    super.key,
    this.conversationId,
    required this.buyerName,
    required this.trackingNumber,
    this.parcelStatus = 'Out for Delivery',
    this.isLogisticsChat = false,
  });

  @override
  State<BuyerChatScreen> createState() => _BuyerChatScreenState();
}

class _BuyerChatScreenState extends State<BuyerChatScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color textMuted = Color(0xFF8B6F63);
  static const Color success = Color(0xFF2E7D32);

  final ScrollController _scrollController = ScrollController();

  final List<RiderChatMessage> _messages = [];
  final TextEditingController _messageController = TextEditingController();
  Timer? _refreshTimer;
  bool _loading = true;
  bool _sending = false;
  String? _error;

  String get _buyerInitial {
    final String name = widget.buyerName.trim();
    return name.isEmpty ? '?' : name[0].toUpperCase();
  }

  @override
  void initState() {
    super.initState();
    _refreshMessages();
    _refreshTimer = Timer.periodic(
      const Duration(seconds: 5),
      (_) => _refreshMessages(),
    );
    _scrollToBottom(animated: false);
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _messageController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _refreshMessages() async {
    final int? conversationId = widget.conversationId;
    if (!widget.isLogisticsChat && conversationId == null) return;
    try {
      final rows = widget.isLogisticsChat
          ? await RiderApiService.logisticsChatMessages()
          : await RiderApiService.riderChatMessages(conversationId!);
      if (!mounted) return;
      final messages = rows
          .map(
            (row) => RiderChatMessage(
              text: row['text']?.toString() ?? '',
              isRider: row['mine'] == true,
              sentAt:
                  DateTime.tryParse(row['date']?.toString() ?? '')?.toLocal() ??
                  DateTime.now(),
            ),
          )
          .toList();
      setState(() {
        _messages
          ..clear()
          ..addAll(messages);
        _loading = false;
        _error = null;
      });
      _scrollToBottom(animated: false);
    } catch (error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = error.toString();
        });
      }
    }
  }

  Future<void> _sendMessage() async {
    final text = _messageController.text.trim();
    final int? conversationId = widget.conversationId;
    if (text.isEmpty ||
        (!widget.isLogisticsChat && conversationId == null) ||
        _sending) {
      return;
    }
    setState(() => _sending = true);
    try {
      if (widget.isLogisticsChat) {
        await RiderApiService.sendLogisticsChatMessage(text);
      } else {
        await RiderApiService.sendRiderChatMessage(conversationId!, text);
      }
      _messageController.clear();
      await _refreshMessages();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  // ============================================
  // SEND / RECEIVE
  // ============================================

  void _scrollToBottom({bool animated = true}) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;

      final double target = _scrollController.position.maxScrollExtent;

      if (animated) {
        _scrollController.animateTo(
          target,
          duration: const Duration(milliseconds: 260),
          curve: Curves.easeOutCubic,
        );
      } else {
        _scrollController.jumpTo(target);
      }
    });
  }

  // ============================================
  // HELPERS
  // ============================================

  String _formatTime(DateTime date) {
    final DateTime local = date.toLocal();
    final int hour12 = local.hour % 12 == 0 ? 12 : local.hour % 12;
    final String minute = local.minute.toString().padLeft(2, '0');
    final String period = local.hour >= 12 ? 'PM' : 'AM';

    return '$hour12:$minute $period';
  }

  String _formatDay(DateTime date) {
    final DateTime now = DateTime.now();
    final DateTime local = date.toLocal();

    if (_isSameDay(local, now)) return 'Today';
    if (_isSameDay(local, now.subtract(const Duration(days: 1)))) {
      return 'Yesterday';
    }

    final String month = local.month.toString().padLeft(2, '0');
    final String day = local.day.toString().padLeft(2, '0');

    return '${local.year}-$month-$day';
  }

  bool _isSameDay(DateTime a, DateTime b) {
    return a.year == b.year && a.month == b.month && a.day == b.day;
  }

  // ============================================
  // BUILD
  // ============================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: cream,
      appBar: _buildAppBar(),
      body: Column(
        children: [
          if (!widget.isLogisticsChat) _buildParcelBanner(),
          Expanded(child: _buildMessageList()),
          _buildComposer(),
        ],
      ),
    );
  }

  PreferredSizeWidget _buildAppBar() {
    return AppBar(
      backgroundColor: darkMaroon,
      foregroundColor: Colors.white,
      elevation: 0,
      titleSpacing: 0,
      title: Row(
        children: [
          CircleAvatar(
            radius: 18,
            backgroundColor: lightBeige,
            child: Text(
              _buyerInitial,
              style: const TextStyle(
                color: darkMaroon,
                fontWeight: FontWeight.w800,
                fontSize: 15,
              ),
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.buyerName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 15.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 1),
                Text(
                  widget.isLogisticsChat ? 'Logistics' : 'Buyer',
                  style: TextStyle(
                    fontSize: 11,
                    color: Colors.white.withValues(alpha: 0.8),
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildParcelBanner() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
      decoration: BoxDecoration(
        color: surface,
        border: Border(
          bottom: BorderSide(color: darkMaroon.withValues(alpha: 0.06)),
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: darkMaroon.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(
              Icons.inventory_2_outlined,
              color: darkMaroon,
              size: 18,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Parcel',
                  style: TextStyle(color: textMuted, fontSize: 10),
                ),
                const SizedBox(height: 1),
                Text(
                  widget.trackingNumber,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: darkMaroon,
                    fontSize: 12.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration: BoxDecoration(
              color: success.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              widget.parcelStatus,
              style: const TextStyle(
                color: success,
                fontSize: 10.5,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMessageList() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null && _messages.isEmpty) {
      return Center(child: Text(_error!, textAlign: TextAlign.center));
    }
    if (_messages.isEmpty) {
      return _buildEmptyState();
    }

    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
      itemCount: _messages.length,
      itemBuilder: (context, index) {
        final RiderChatMessage message = _messages[index];
        final bool showDay =
            index == 0 ||
            !_isSameDay(
              _messages[index - 1].sentAt.toLocal(),
              message.sentAt.toLocal(),
            );

        return Column(
          children: [
            if (showDay) _buildDayChip(_formatDay(message.sentAt)),
            _buildBubble(message),
          ],
        );
      },
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 36),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 68,
              height: 68,
              decoration: const BoxDecoration(
                color: surface,
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.chat_bubble_outline_rounded,
                color: brownBeige,
                size: 30,
              ),
            ),
            const SizedBox(height: 14),
            Text(
              'Message ${widget.buyerName}',
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: darkMaroon,
                fontSize: 15,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 5),
            Text(
              widget.isLogisticsChat
                  ? 'Send a message to your Logistics hub. New messages refresh automatically.'
                  : 'Send a message about this delivery. New messages refresh automatically.',
              textAlign: TextAlign.center,
              style: TextStyle(color: textMuted, fontSize: 12.5, height: 1.45),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildComposer() {
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(12, 9, 12, 9),
        color: surface,
        child: Row(
          children: [
            Expanded(
              child: TextField(
                controller: _messageController,
                minLines: 1,
                maxLines: 4,
                maxLength: 1000,
                decoration: InputDecoration(
                  hintText: widget.isLogisticsChat
                      ? 'Message Logistics…'
                      : 'Message the buyer…',
                  counterText: '',
                  filled: true,
                  fillColor: cream,
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 11,
                  ),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(22),
                    borderSide: BorderSide.none,
                  ),
                ),
                onSubmitted: (_) => _sendMessage(),
              ),
            ),
            const SizedBox(width: 8),
            IconButton.filled(
              onPressed: _sending ? null : _sendMessage,
              style: IconButton.styleFrom(
                backgroundColor: darkMaroon,
                foregroundColor: Colors.white,
              ),
              icon: _sending
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.send_rounded),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDayChip(String label) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
        decoration: BoxDecoration(
          color: brownBeige.withValues(alpha: 0.15),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(
          label,
          style: const TextStyle(
            color: textMuted,
            fontSize: 10.5,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }

  Widget _buildBubble(RiderChatMessage message) {
    final bool isRider = message.isRider;

    return Align(
      alignment: isRider ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.74,
        ),
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 8),
        decoration: BoxDecoration(
          color: isRider ? darkMaroon : surface,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(18),
            topRight: const Radius.circular(18),
            bottomLeft: Radius.circular(isRider ? 18 : 4),
            bottomRight: Radius.circular(isRider ? 4 : 18),
          ),
          border: isRider
              ? null
              : Border.all(color: darkMaroon.withValues(alpha: 0.06)),
          boxShadow: [
            BoxShadow(
              color: darkMaroon.withValues(alpha: 0.05),
              blurRadius: 10,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          mainAxisSize: MainAxisSize.min,
          children: [
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                message.text,
                style: TextStyle(
                  color: isRider ? Colors.white : darkMaroon,
                  fontSize: 13.5,
                  height: 1.4,
                ),
              ),
            ),
            const SizedBox(height: 4),
            Text(
              _formatTime(message.sentAt),
              style: TextStyle(
                color: isRider
                    ? Colors.white.withValues(alpha: 0.65)
                    : textMuted,
                fontSize: 9.5,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ============================================================
// TYPING INDICATOR DOT
// ============================================================

class _TypingDot extends StatefulWidget {
  final int delay;

  const _TypingDot({required this.delay});

  @override
  State<_TypingDot> createState() => _TypingDotState();
}

class _TypingDotState extends State<_TypingDot>
    with SingleTickerProviderStateMixin {
  late final AnimationController _animation;

  @override
  void initState() {
    super.initState();

    _animation = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600),
    );

    Future.delayed(Duration(milliseconds: widget.delay), () {
      if (mounted) _animation.repeat(reverse: true);
    });
  }

  @override
  void dispose() {
    _animation.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: Tween<double>(begin: 0.3, end: 1).animate(_animation),
      child: Container(
        width: 7,
        height: 7,
        decoration: const BoxDecoration(
          color: Color(0xFF9E7161),
          shape: BoxShape.circle,
        ),
      ),
    );
  }
}

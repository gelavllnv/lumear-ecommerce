import 'dart:async';

import 'package:flutter/material.dart';

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
  final String buyerName;
  final String trackingNumber;
  final String parcelStatus;

  const BuyerChatContact({
    required this.buyerName,
    required this.trackingNumber,
    required this.parcelStatus,
  });
}

/// In-memory chat storage shared by the buyer list and the chat screen.
///
/// Local prototype: everything resets when the app restarts. Replace this
/// with your backend (Firebase, Supabase, API, etc.) later.
class BuyerChatStore {
  BuyerChatStore._();

  static final Map<String, List<RiderChatMessage>> _threads = {};
  static final Map<String, int> _unread = {};
  static bool _seeded = false;

  /// Sample buyers so the inbox is not empty while testing.
  static const List<BuyerChatContact> demoContacts = [
    BuyerChatContact(
      buyerName: 'Maria Santos',
      trackingNumber: 'LMR-DEMO-001',
      parcelStatus: 'Out for Delivery',
    ),
    BuyerChatContact(
      buyerName: 'Juan Dela Cruz',
      trackingNumber: 'LMR-DEMO-002',
      parcelStatus: 'Heading to Pickup',
    ),
    BuyerChatContact(
      buyerName: 'Ana Reyes',
      trackingNumber: 'LMR-DEMO-003',
      parcelStatus: 'Pending',
    ),
  ];

  static List<RiderChatMessage> threadFor(String trackingNumber) {
    return _threads.putIfAbsent(trackingNumber, () => <RiderChatMessage>[]);
  }

  static int unreadFor(String trackingNumber) => _unread[trackingNumber] ?? 0;

  static void markRead(String trackingNumber) {
    _unread[trackingNumber] = 0;
  }

  static void seedDemo() {
    if (_seeded) return;
    _seeded = true;

    final DateTime now = DateTime.now();

    _threads['LMR-DEMO-001'] = [
      RiderChatMessage(
        text: 'Kuya, asan na po kayo?',
        isRider: false,
        sentAt: now.subtract(const Duration(minutes: 14)),
      ),
      RiderChatMessage(
        text: 'Pwede po ba paki-text pag malapit na?',
        isRider: false,
        sentAt: now.subtract(const Duration(minutes: 12)),
      ),
    ];
    _unread['LMR-DEMO-001'] = 2;

    _threads['LMR-DEMO-002'] = [
      RiderChatMessage(
        text: "I'm on my way to your address.",
        isRider: true,
        sentAt: now.subtract(const Duration(hours: 1, minutes: 5)),
      ),
      RiderChatMessage(
        text: 'Okay po, salamat!',
        isRider: false,
        sentAt: now.subtract(const Duration(hours: 1)),
      ),
    ];
    _unread['LMR-DEMO-002'] = 0;

    _threads['LMR-DEMO-003'] = [
      RiderChatMessage(
        text: 'Pwede po bang bukas na lang i-deliver?',
        isRider: false,
        sentAt: now.subtract(const Duration(hours: 3)),
      ),
    ];
    _unread['LMR-DEMO-003'] = 1;
  }
}

/// Chat between the rider (the person using this app) and the buyer of the
/// parcel being delivered.
///
/// Rider messages appear on the right, buyer messages on the left.
///
/// Local prototype: messages are kept in memory (see [BuyerChatStore]) and
/// reset when the app restarts. The buyer's replies are simulated when
/// [enableDemoReplies] is true. To connect a real backend, replace
/// [_sendMessage] and the demo reply logic with your API / realtime calls
/// (Firebase, Supabase, websocket, etc.).
class BuyerChatScreen extends StatefulWidget {
  final String buyerName;
  final String trackingNumber;
  final String parcelStatus;
  final bool enableDemoReplies;

  const BuyerChatScreen({
    super.key,
    required this.buyerName,
    required this.trackingNumber,
    this.parcelStatus = 'Out for Delivery',
    this.enableDemoReplies = true,
  });

  @override
  State<BuyerChatScreen> createState() => _BuyerChatScreenState();
}

class _BuyerChatScreenState extends State<BuyerChatScreen> {
  static const Color darkMaroon = Color(0xFF62191C);
  static const Color maroon = Color(0xFF873632);
  static const Color brownBeige = Color(0xFF9E7161);
  static const Color lightBeige = Color(0xFFCAAE9F);
  static const Color cream = Color(0xFFF3E9E1);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color textMuted = Color(0xFF8B6F63);
  static const Color success = Color(0xFF2E7D32);

  // Messages a rider commonly needs to send.
  static const List<String> _quickReplies = [
    "I'm on my way to your address.",
    "I've arrived. I'm outside your place.",
    'Please prepare the payment (COD).',
    'Can you share your exact location?',
    'Please come out to receive the parcel.',
    'Thank you!',
  ];

  final TextEditingController _controller = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final FocusNode _focusNode = FocusNode();

  late final List<RiderChatMessage> _messages;

  bool _buyerTyping = false;
  Timer? _replyTimer;

  String get _threadKey => widget.trackingNumber;

  String get _buyerInitial {
    final String name = widget.buyerName.trim();
    return name.isEmpty ? '?' : name[0].toUpperCase();
  }

  @override
  void initState() {
    super.initState();

    _messages = BuyerChatStore.threadFor(_threadKey);
    BuyerChatStore.markRead(_threadKey);

    _controller.addListener(() => setState(() {}));

    _scrollToBottom(animated: false);
  }

  @override
  void dispose() {
    _replyTimer?.cancel();
    _controller.dispose();
    _scrollController.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  // ============================================
  // SEND / RECEIVE
  // ============================================

  void _sendMessage([String? preset]) {
    final String text = (preset ?? _controller.text).trim();

    if (text.isEmpty) return;

    setState(() {
      _messages.add(
        RiderChatMessage(text: text, isRider: true, sentAt: DateTime.now()),
      );
    });

    _controller.clear();
    _scrollToBottom();

    if (widget.enableDemoReplies) {
      _simulateBuyerReply(text);
    }
  }

  void _simulateBuyerReply(String riderText) {
    _replyTimer?.cancel();

    setState(() => _buyerTyping = true);
    _scrollToBottom();

    _replyTimer = Timer(const Duration(milliseconds: 1800), () {
      if (!mounted) return;

      setState(() {
        _buyerTyping = false;
        _messages.add(
          RiderChatMessage(
            text: _demoReplyFor(riderText),
            isRider: false,
            sentAt: DateTime.now(),
          ),
        );
      });

      _scrollToBottom();
    });
  }

  String _demoReplyFor(String text) {
    final String t = text.toLowerCase();

    if (t.contains('on my way') || t.contains('papunta')) {
      return 'Okay po, thank you! Hihintayin ko po kayo.';
    }
    if (t.contains('arrived') ||
        t.contains('outside') ||
        t.contains('nandito')) {
      return 'Sige po, lalabas na po ako.';
    }
    if (t.contains('payment') || t.contains('cod') || t.contains('bayad')) {
      return 'Opo, ready na po ang payment.';
    }
    if (t.contains('location') || t.contains('address')) {
      return 'Same po sa address sa order. May maroon na gate po.';
    }
    if (t.contains('come out') || t.contains('receive')) {
      return 'Okay po, papunta na po ako sa labas.';
    }
    if (t.contains('thank') || t.contains('salamat')) {
      return 'Salamat din po, kuya/ate!';
    }

    return 'Okay po, noted!';
  }

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
          _buildParcelBanner(),
          Expanded(child: _buildMessageList()),
          _buildQuickReplies(),
          _buildInputBar(),
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
                  _buyerTyping ? 'typing...' : 'Buyer',
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
    if (_messages.isEmpty && !_buyerTyping) {
      return _buildEmptyState();
    }

    final int extra = _buyerTyping ? 1 : 0;

    return ListView.builder(
      controller: _scrollController,
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
      itemCount: _messages.length + extra,
      itemBuilder: (context, index) {
        if (index >= _messages.length) {
          return _buildTypingBubble();
        }

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
            const Text(
              'Let the buyer know about your delivery. Tap a quick reply '
              'below or type your own message.',
              textAlign: TextAlign.center,
              style: TextStyle(color: textMuted, fontSize: 12.5, height: 1.45),
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

  Widget _buildTypingBubble() {
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
        decoration: BoxDecoration(
          color: surface,
          borderRadius: const BorderRadius.only(
            topLeft: Radius.circular(18),
            topRight: Radius.circular(18),
            bottomLeft: Radius.circular(4),
            bottomRight: Radius.circular(18),
          ),
          border: Border.all(color: darkMaroon.withValues(alpha: 0.06)),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            _TypingDot(delay: 0),
            SizedBox(width: 4),
            _TypingDot(delay: 160),
            SizedBox(width: 4),
            _TypingDot(delay: 320),
          ],
        ),
      ),
    );
  }

  Widget _buildQuickReplies() {
    return SizedBox(
      height: 44,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.fromLTRB(14, 4, 14, 6),
        itemCount: _quickReplies.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final String reply = _quickReplies[index];

          return Material(
            color: surface,
            borderRadius: BorderRadius.circular(20),
            child: InkWell(
              borderRadius: BorderRadius.circular(20),
              onTap: () => _sendMessage(reply),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 13),
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: maroon.withValues(alpha: 0.25)),
                ),
                child: Text(
                  reply,
                  style: const TextStyle(
                    color: maroon,
                    fontSize: 11.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildInputBar() {
    final bool canSend = _controller.text.trim().isNotEmpty;

    return Container(
      padding: const EdgeInsets.fromLTRB(14, 8, 14, 8),
      decoration: BoxDecoration(
        color: cream,
        border: Border(
          top: BorderSide(color: darkMaroon.withValues(alpha: 0.06)),
        ),
      ),
      child: SafeArea(
        top: false,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                decoration: BoxDecoration(
                  color: surface,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: darkMaroon.withValues(alpha: 0.08)),
                ),
                child: TextField(
                  controller: _controller,
                  focusNode: _focusNode,
                  minLines: 1,
                  maxLines: 4,
                  textCapitalization: TextCapitalization.sentences,
                  textInputAction: TextInputAction.send,
                  onSubmitted: (_) => _sendMessage(),
                  style: const TextStyle(color: darkMaroon, fontSize: 14),
                  decoration: InputDecoration(
                    hintText: 'Message ${widget.buyerName}...',
                    hintStyle: const TextStyle(
                      color: textMuted,
                      fontSize: 13.5,
                    ),
                    border: InputBorder.none,
                    contentPadding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 10),
            Material(
              color: canSend ? darkMaroon : darkMaroon.withValues(alpha: 0.35),
              shape: const CircleBorder(),
              child: InkWell(
                customBorder: const CircleBorder(),
                onTap: canSend ? _sendMessage : null,
                child: const SizedBox(
                  width: 48,
                  height: 48,
                  child: Icon(
                    Icons.send_rounded,
                    color: Colors.white,
                    size: 21,
                  ),
                ),
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

import '../design/lumear_theme.dart';

import 'package:flutter/material.dart';

import '../order_manager.dart';
import '../services/api_service.dart';
import '../services/buyer_data.dart';
import 'buyer_wallet_screen.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  static Color get cream => LumearColors.cream;
  static Color get maroon => LumearColors.maroon;
  static Color get darkMaroon => LumearColors.dark;
  static Color get lightMaroon => LumearColors.light;
  static Color get fieldColor => LumearColors.field;
  static Color get darkText => LumearColors.ink;

  int selectedTab = 0;
  bool isSearching = false;
  final TextEditingController searchController = TextEditingController();
  final Set<String> workingOrders = {};
  String? actionError;

  Future<void> actOnOrder(PlacedOrder order, {required bool receive}) async {
    if (workingOrders.contains(order.orderId)) return;
    final reason = receive
        ? null
        : await showDialog<String>(
            context: context,
            builder: (_) => const _CancellationDialog(),
          );
    if (!mounted || (!receive && reason == null)) return;
    if (receive) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialog) => AlertDialog(
          title: const Text('Confirm order received?'),
          content: const Text(
            'Confirm only after you have received and checked all items. If something is missing or damaged, request a return / refund first.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialog, false),
              child: const Text('Not yet'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialog, true),
              child: const Text('Confirm received'),
            ),
          ],
        ),
      );
      if (!mounted || confirmed != true) return;
    }
    setState(() {
      workingOrders.add(order.orderId);
      actionError = null;
    });
    try {
      if (OrderManager.instance.connected) {
        final result = await ApiService.post(
          '/orders/${order.orderId}/${receive ? 'received' : 'cancel'}',
          body: receive ? {} : {'reason': reason!},
        );
        if (!mounted) return;
        // The mutation is confirmed before refreshing the rest of the workspace.
        OrderManager.instance.updateOrderStatus(
          orderId: order.orderId,
          status: receive ? 'Completed' : 'Cancelled',
        );
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(result['message'] as String)));
        await BuyerData.instance.refresh();
      } else {
        OrderManager.instance.updateOrderStatus(
          orderId: order.orderId,
          status: receive ? 'Completed' : 'Cancelled',
        );
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Updated in local preview only.')),
          );
        }
      }
    } catch (e) {
      if (mounted) setState(() => actionError = e.toString());
    } finally {
      if (mounted) setState(() => workingOrders.remove(order.orderId));
    }
  }

  Future<void> requestRefund(PlacedOrder order) async {
    final item = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      builder: (sheet) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: [
            const ListTile(title: Text('Choose an item to return / refund')),
            for (final item in order.refundItems.where(
              (item) => item['return_status'] == null,
            ))
              ListTile(
                title: Text(item['name'] as String),
                subtitle: Text('${item['quantity']} item(s)'),
                onTap: () => Navigator.pop(sheet, item),
              ),
          ],
        ),
      ),
    );
    if (!mounted || item == null) return;
    final saved = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => ReturnRequestScreen(item: item)),
    );
    if (!mounted || saved != true) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text(
          'Return / refund requested. Track its status in My e-wallet.',
        ),
      ),
    );
    try {
      await BuyerData.instance.refresh();
    } catch (e) {
      if (mounted) setState(() => actionError = e.toString());
    }
  }

  final List<String> tabs = const [
    'All',
    'To Pay',
    'To Ship',
    'In Transit',
    'Delivered',
    'Completed',
    'Cancelled',
  ];

  late final List<DemoOrder> orders = [
    DemoOrder(
      id: 'LMR-2026-001',
      storeName: 'Lumear Fashion Store',
      productName: 'Classic Shirt',
      price: 499,
      quantity: 1,
      icon: Icons.checkroom_rounded,
      status: 'To Pay',
      statusText: 'Waiting for payment',
      statusIcon: Icons.payment_rounded,
      statusColor: Colors.orange.shade800,
      tracking: const [
        TrackingStep(
          title: 'Order Placed',
          description: 'Your order has been successfully placed.',
          completed: true,
        ),
        TrackingStep(
          title: 'Payment',
          description: 'Complete payment to continue processing your order.',
          completed: false,
        ),
        TrackingStep(
          title: 'Preparing Order',
          description: 'The seller will prepare your package after payment.',
          completed: false,
        ),
        TrackingStep(
          title: 'Delivered',
          description: 'Your package will be delivered to your address.',
          completed: false,
        ),
      ],
    ),
    DemoOrder(
      id: 'LMR-2026-002',
      storeName: 'GameHub Official Store',
      productName: 'Gaming Controller',
      price: 1499,
      quantity: 1,
      icon: Icons.sports_esports_rounded,
      status: 'To Ship',
      statusText: 'Seller is preparing your order',
      statusIcon: Icons.inventory_2_outlined,
      statusColor: maroon,
      tracking: const [
        TrackingStep(
          title: 'Order Placed',
          description: 'Your order has been successfully placed.',
          completed: true,
        ),
        TrackingStep(
          title: 'Payment Confirmed',
          description: 'Your payment has been confirmed.',
          completed: true,
        ),
        TrackingStep(
          title: 'Preparing Order',
          description: 'The seller is preparing your package.',
          completed: false,
        ),
        TrackingStep(
          title: 'In Transit',
          description: 'Your package will be handed to the courier.',
          completed: false,
        ),
        TrackingStep(
          title: 'Delivered',
          description: 'Your package will be delivered to your address.',
          completed: false,
        ),
      ],
    ),
    DemoOrder(
      id: 'LMR-2026-003',
      storeName: 'TechZone Philippines',
      productName: 'Wireless Headset',
      price: 899,
      quantity: 1,
      icon: Icons.headphones_rounded,
      status: 'In Transit',
      statusText: 'Arrived at Laguna Logistics Center',
      statusIcon: Icons.local_shipping_rounded,
      statusColor: maroon,
      tracking: const [
        TrackingStep(
          title: 'Order Placed',
          description: 'Your order has been successfully placed.',
          completed: true,
        ),
        TrackingStep(
          title: 'Preparing Order',
          description: 'The seller prepared your package.',
          completed: true,
        ),
        TrackingStep(
          title: 'Picked Up by Courier',
          description: 'The courier has picked up your package.',
          completed: true,
        ),
        TrackingStep(
          title: 'In Transit',
          description: 'Your package arrived at Laguna Logistics Center.',
          completed: true,
        ),
        TrackingStep(
          title: 'Out for Delivery',
          description: 'Your package is waiting for the next delivery route.',
          completed: false,
        ),
        TrackingStep(
          title: 'Delivered',
          description: 'Your package will be delivered to your address.',
          completed: false,
        ),
      ],
    ),
    DemoOrder(
      id: 'LMR-2026-004',
      storeName: 'BabyCare Shop',
      productName: 'Baby Essentials',
      price: 750,
      quantity: 2,
      icon: Icons.child_friendly_rounded,
      status: 'In Transit',
      statusText: 'Out for delivery',
      statusIcon: Icons.delivery_dining_rounded,
      statusColor: maroon,
      tracking: const [
        TrackingStep(
          title: 'Order Placed',
          description: 'Your order has been successfully placed.',
          completed: true,
        ),
        TrackingStep(
          title: 'Preparing Order',
          description: 'The seller prepared your package.',
          completed: true,
        ),
        TrackingStep(
          title: 'Picked Up by Courier',
          description: 'The courier has picked up your package.',
          completed: true,
        ),
        TrackingStep(
          title: 'Out for Delivery',
          description: 'Your package is with the delivery rider.',
          completed: true,
        ),
        TrackingStep(
          title: 'Delivered',
          description: 'Your package will be marked delivered after arrival.',
          completed: false,
        ),
      ],
    ),
    DemoOrder(
      id: 'LMR-2026-005',
      storeName: 'Golden Gems',
      productName: 'Gold Jewelry',
      price: 1299,
      quantity: 1,
      icon: Icons.diamond_outlined,
      status: 'Delivered',
      statusText: 'Delivered successfully',
      statusIcon: Icons.check_circle_rounded,
      statusColor: Colors.green.shade700,
      tracking: const [
        TrackingStep(
          title: 'Order Placed',
          description: 'Your order has been successfully placed.',
          completed: true,
        ),
        TrackingStep(
          title: 'Preparing Order',
          description: 'The seller prepared your package.',
          completed: true,
        ),
        TrackingStep(
          title: 'In Transit',
          description: 'Your package was transported to your area.',
          completed: true,
        ),
        TrackingStep(
          title: 'Out for Delivery',
          description: 'Your package was assigned to a delivery rider.',
          completed: true,
        ),
        TrackingStep(
          title: 'Delivered',
          description: 'Your package has been successfully delivered.',
          completed: true,
        ),
      ],
    ),
  ];

  @override
  void initState() {
    super.initState();
    OrderManager.instance.addListener(_refresh);
  }

  void _refresh() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    OrderManager.instance.removeListener(_refresh);
    searchController.dispose();
    super.dispose();
  }

  List<DemoOrder> get filteredOrders {
    final query = searchController.text.trim().toLowerCase();
    final placed = OrderManager.instance.orders.map(
      (order) => DemoOrder(
        id: order.orderId,
        source: order,
        storeName: order.storeName.isEmpty
            ? 'Lumear · This session'
            : order.storeName,
        productName: order.items
            .map((item) => '${item.name} × ${item.quantity}')
            .join(' · '),
        price: order.total,
        quantity: 1,
        icon: Icons.shopping_bag_outlined,
        status: order.status == 'To Receive' ? 'In Transit' : order.status,
        statusText: '${order.status} · ${order.paymentMethod}',
        statusIcon: Icons.inventory_2_outlined,
        statusColor: maroon,
        tracking: OrderManager.instance.connected
            ? [
                for (final e in order.timeline)
                  TrackingStep(
                    title: e['title'],
                    description: DateTime.fromMillisecondsSinceEpoch(
                      (e['date'] as num).toInt() * 1000,
                    ).toLocal().toString().split('.').first,
                    completed: true,
                  ),
              ]
            : [
                TrackingStep(
                  title: 'Order placed',
                  description: 'Saved in this app session. Cash on delivery. Total includes shipping.',
                  completed: true,
                ),
              ],
      ),
    );
    return [...placed, if (!OrderManager.instance.connected) ...orders].where((
      order,
    ) {
      final matchesTab = selectedTab == 0 || order.status == tabs[selectedTab];
      final matchesSearch =
          query.isEmpty ||
          order.productName.toLowerCase().contains(query) ||
          order.storeName.toLowerCase().contains(query) ||
          order.id.toLowerCase().contains(query);
      return matchesTab && matchesSearch;
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    LumearAppearance.watch(context);
    final visibleOrders = filteredOrders;

    return Scaffold(
      backgroundColor: cream,
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(
                OrderManager.instance.connected
                    ? 'Your saved orders'
                    : 'Session orders & sample tracking',
                style: TextStyle(fontSize: 10, color: maroon),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 18, 16, 10),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'Your order journal',
                      style: TextStyle(
                        color: darkMaroon,
                        fontFamily: 'CormorantGaramond',
                        fontSize: 25,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: isSearching ? 'Close search' : 'Search orders',
                    onPressed: () {
                      setState(() {
                        isSearching = !isSearching;
                        if (!isSearching) searchController.clear();
                      });
                    },
                    icon: Icon(
                      isSearching ? Icons.close_rounded : Icons.search_rounded,
                      color: darkMaroon,
                    ),
                  ),
                ],
              ),
            ),
            if (isSearching)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                child: TextField(
                  controller: searchController,
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    hintText: 'Search product, store, or order ID',
                    prefixIcon: Icon(Icons.search_rounded, color: lightMaroon),
                    filled: true,
                    fillColor: LumearColors.surface,
                    contentPadding: const EdgeInsets.symmetric(vertical: 13),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: const BorderSide(color: Color(0xFFEAD7CE)),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: const BorderSide(color: Color(0xFFEAD7CE)),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: BorderSide(color: maroon, width: 1.4),
                    ),
                  ),
                ),
              ),
            SizedBox(
              height: 48,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: tabs.length,
                itemBuilder: (context, index) {
                  final isSelected = selectedTab == index;
                  return GestureDetector(
                    onTap: () => setState(() => selectedTab = index),
                    child: Container(
                      margin: const EdgeInsets.only(
                        right: 8,
                        top: 5,
                        bottom: 5,
                      ),
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      decoration: BoxDecoration(
                        color: isSelected
                            ? LumearColors.action
                            : LumearColors.surface,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: isSelected ? maroon : const Color(0xFFEAD7CE),
                        ),
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        tabs[index],
                        style: TextStyle(
                          color: isSelected ? Colors.white : lightMaroon,
                          fontSize: 12,
                          fontWeight: isSelected
                              ? FontWeight.bold
                              : FontWeight.normal,
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
            const SizedBox(height: 8),
            Expanded(
              child: visibleOrders.isEmpty
                  ? _buildEmptyState()
                  : ListView.builder(
                      padding: const EdgeInsets.fromLTRB(16, 10, 16, 24),
                      itemCount: visibleOrders.length,
                      itemBuilder: (context, index) =>
                          _buildOrderCard(visibleOrders[index]),
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 88,
              height: 88,
              decoration: BoxDecoration(
                color: fieldColor,
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.receipt_long_outlined,
                color: lightMaroon,
                size: 42,
              ),
            ),
            const SizedBox(height: 18),
            Text(
              'No orders found',
              style: TextStyle(
                color: darkMaroon,
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Your orders will appear here. Try another tab or search term.',
              textAlign: TextAlign.center,
              style: TextStyle(color: lightMaroon, fontSize: 13, height: 1.4),
            ),
            if (selectedTab != 0 || searchController.text.isNotEmpty) ...[
              const SizedBox(height: 16),
              TextButton(
                onPressed: () => setState(() {
                  selectedTab = 0;
                  searchController.clear();
                }),
                child: Text('Show all orders', style: TextStyle(color: maroon)),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildOrderCard(DemoOrder order) {
    final total = order.price * order.quantity;
    final source = order.source;
    final busy = workingOrders.contains(order.id);
    final active = !['Completed', 'Cancelled'].contains(order.status);
    final canCancel =
        source != null &&
        active &&
        (OrderManager.instance.connected
            ? source.canCancel
            : ['To Pay', 'To Ship'].contains(source.status));
    final canReceive =
        source != null &&
        active &&
        (OrderManager.instance.connected
            ? source.canReceive
            : source.status == 'Delivered');
    return Container(
      margin: const EdgeInsets.only(bottom: 20),
      decoration: BoxDecoration(
        border: Border(bottom: BorderSide(color: LumearColors.border)),
      ),
      padding: const EdgeInsets.only(bottom: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  order.storeName.toUpperCase(),
                  style: TextStyle(
                    color: maroon,
                    fontSize: 10,
                    letterSpacing: 1,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
                decoration: BoxDecoration(
                  color: fieldColor,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  order.status,
                  style: TextStyle(
                    color: darkMaroon,
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 13),
          Row(
            children: [
              Container(
                width: 64,
                height: 74,
                decoration: BoxDecoration(
                  color: fieldColor,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(order.icon, color: maroon, size: 30),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      order.productName,
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '${order.quantity} item(s) · ${order.id}',
                      style: TextStyle(color: darkText, fontSize: 10),
                    ),
                    const SizedBox(height: 7),
                    Text(
                      '₱${total.toStringAsFixed(2)}',
                      style: TextStyle(
                        color: darkMaroon,
                        fontWeight: FontWeight.w700,
                        fontSize: 17,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          if (source != null) ...[
            for (final item in source.refundItems.where(
              (item) => item['return_status'] != null,
            ))
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text(
                  'Return / refund · ${item['name']}: ${item['return_status']}',
                ),
              ),
            if (actionError != null)
              Text(
                actionError!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            if (busy) const LinearProgressIndicator(),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                if (canCancel)
                  OutlinedButton(
                    onPressed: busy
                        ? null
                        : () => actOnOrder(source, receive: false),
                    child: const Text('Cancel order'),
                  ),
                if (OrderManager.instance.connected && source.canRefund)
                  OutlinedButton(
                    onPressed: busy ? null : () => requestRefund(source),
                    child: const Text('Return / Refund'),
                  ),
                if (canReceive)
                  FilledButton(
                    onPressed: busy
                        ? null
                        : () => actOnOrder(source, receive: true),
                    child: const Text('Order received'),
                  ),
              ],
            ),
            const SizedBox(height: 8),
          ],
          Row(
            children: [
              Icon(order.statusIcon, size: 16, color: maroon),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  order.statusText,
                  style: const TextStyle(fontSize: 11),
                ),
              ),
              IconButton(
                tooltip: 'Track order',
                onPressed: () => _showTrackingBottomSheet(order),
                icon: const Icon(Icons.arrow_forward, size: 20),
              ),
            ],
          ),
        ],
      ),
    );
  }

  void _showTrackingBottomSheet(DemoOrder order) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: cream,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(25)),
      ),
      builder: (sheetContext) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(22, 18, 22, 30),
            child: SingleChildScrollView(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Center(
                    child: Container(
                      width: 45,
                      height: 5,
                      decoration: BoxDecoration(
                        color: lightMaroon,
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                  ),
                  const SizedBox(height: 22),
                  Text(
                    'Track Order',
                    style: TextStyle(
                      color: darkMaroon,
                      fontFamily: 'CormorantGaramond',
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    order.productName,
                    style: TextStyle(color: lightMaroon, fontSize: 13),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Order ID: ${order.id}',
                    style: TextStyle(color: lightMaroon, fontSize: 11),
                  ),
                  const SizedBox(height: 25),
                  ...List.generate(
                    order.tracking.length,
                    (index) => _buildTrackingStep(
                      order.tracking[index],
                      index == order.tracking.length - 1,
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildTrackingStep(TrackingStep step, bool isLast) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Column(
          children: [
            Container(
              width: 30,
              height: 30,
              decoration: BoxDecoration(
                color: step.completed ? LumearColors.action : fieldColor,
                shape: BoxShape.circle,
              ),
              child: Icon(
                step.completed ? Icons.check_rounded : Icons.circle_outlined,
                color: step.completed ? Colors.white : lightMaroon,
                size: 17,
              ),
            ),
            if (!isLast)
              Container(
                width: 2,
                height: 55,
                color: step.completed ? maroon : const Color(0xFFEAD7CE),
              ),
          ],
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(top: 3, bottom: 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  step.title,
                  style: TextStyle(
                    color: step.completed ? darkText : lightMaroon,
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  step.description,
                  style: TextStyle(
                    color: lightMaroon,
                    fontSize: 12,
                    height: 1.3,
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class DemoOrder {
  final PlacedOrder? source;
  final String id;
  final String storeName;
  final String productName;
  final double price;
  final int quantity;
  final IconData icon;
  final String status;
  final String statusText;
  final IconData statusIcon;
  final Color statusColor;
  final List<TrackingStep> tracking;

  const DemoOrder({
    this.source,
    required this.id,
    required this.storeName,
    required this.productName,
    required this.price,
    required this.quantity,
    required this.icon,
    required this.status,
    required this.statusText,
    required this.statusIcon,
    required this.statusColor,
    required this.tracking,
  });
}

class _CancellationDialog extends StatefulWidget {
  const _CancellationDialog();
  @override
  State<_CancellationDialog> createState() => _CancellationDialogState();
}

class _CancellationDialogState extends State<_CancellationDialog> {
  final reason = TextEditingController();
  final form = GlobalKey<FormState>();
  @override
  void dispose() {
    reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Cancel this order?'),
    content: Form(
      key: form,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text(
            'Cancellation is available before packing for unpaid orders. Please tell us why you are cancelling.',
          ),
          TextFormField(
            controller: reason,
            maxLength: 300,
            decoration: const InputDecoration(labelText: 'Cancellation reason'),
            validator: (v) => (v?.trim().length ?? 0) < 3
                ? 'Enter at least 3 characters.'
                : null,
          ),
        ],
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Keep order'),
      ),
      FilledButton(
        onPressed: () {
          if (form.currentState!.validate()) {
            Navigator.pop(context, reason.text.trim());
          }
        },
        child: const Text('Confirm cancellation'),
      ),
    ],
  );
}

class TrackingStep {
  final String title;
  final String description;
  final bool completed;

  const TrackingStep({
    required this.title,
    required this.description,
    required this.completed,
  });
}

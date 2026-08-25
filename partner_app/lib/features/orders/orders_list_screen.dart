import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/notification_bell.dart';
import '../../shared/widgets/p_card.dart';
import '../../shared/widgets/status_chip.dart';
import 'orders_provider.dart';

const _statusOptions = [
  'placed', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery',
  'delivered', 'completed', 'cancelled', 'returned', 'refunded',
];

class OrdersListScreen extends ConsumerWidget {
  const OrdersListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersAsync = ref.watch(ordersProvider);
    final filter = ref.watch(ordersFilterProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Orders'),
        actions: const [NotificationBell(), SizedBox(width: 8)],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    style: const TextStyle(color: AppTheme.textPrimary),
                    decoration: const InputDecoration(
                      hintText: 'Search sub-order #',
                      prefixIcon: Icon(Icons.search),
                      isDense: true,
                    ),
                    onSubmitted: (v) => ref.read(ordersFilterProvider.notifier).state =
                        OrdersFilter(status: filter.status, search: v, issuesOnly: filter.issuesOnly),
                  ),
                ),
                const SizedBox(width: 8),
                PopupMenuButton<String?>(
                  icon: const Icon(Icons.filter_list),
                  onSelected: (status) => ref.read(ordersFilterProvider.notifier).state =
                      OrdersFilter(status: status, search: filter.search, issuesOnly: filter.issuesOnly),
                  itemBuilder: (context) => [
                    const PopupMenuItem(value: null, child: Text('All statuses')),
                    ..._statusOptions.map((s) => PopupMenuItem(value: s, child: Text(s))),
                  ],
                ),
                IconButton(
                  icon: Icon(Icons.report_problem_outlined,
                      color: filter.issuesOnly ? AppTheme.error : AppTheme.textSecondary),
                  tooltip: 'Issues only',
                  onPressed: () => ref.read(ordersFilterProvider.notifier).state =
                      OrdersFilter(status: filter.status, search: filter.search, issuesOnly: !filter.issuesOnly),
                ),
              ],
            ),
          ),
          Expanded(
            child: ordersAsync.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(
                message: e is ApiException ? e.message : 'Failed to load orders.',
                onRetry: () => ref.read(ordersProvider.notifier).refresh(),
              ),
              data: (paginated) => RefreshIndicator(
                onRefresh: () => ref.read(ordersProvider.notifier).refresh(),
                child: paginated.items.isEmpty
                    ? ListView(children: const [
                        SizedBox(height: 120),
                        EmptyState(message: 'No orders found.', icon: Icons.receipt_long_outlined),
                      ])
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                        itemCount: paginated.items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (context, index) {
                          final o = paginated.items[index];
                          return PCard(
                            onTap: () => context.push('/orders/${o['sub_order_number']}'),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: Text('${o['sub_order_number'] ?? '-'}',
                                          style: const TextStyle(fontWeight: FontWeight.w600)),
                                    ),
                                    if (o['status'] != null) StatusChip(status: '${o['status']}'),
                                  ],
                                ),
                                const SizedBox(height: 6),
                                Text('${o['item_count'] ?? 0} item(s) · ${o['customer_city'] ?? '-'}',
                                    style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                                const SizedBox(height: 6),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  children: [
                                    Text(
                                      MoneyFormatter.format(o['vendor_payout'] as num?, o['currency'] as String?),
                                      style: const TextStyle(fontWeight: FontWeight.w600, color: AppTheme.success),
                                    ),
                                    if (o['sla_breached'] == true)
                                      const Text('SLA breached', style: TextStyle(color: AppTheme.error, fontSize: 12)),
                                  ],
                                ),
                              ],
                            ),
                          );
                        },
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

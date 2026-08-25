import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/notification_bell.dart';
import '../../shared/widgets/p_card.dart';
import 'inventory_provider.dart';

class InventoryListScreen extends ConsumerWidget {
  const InventoryListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final inventoryAsync = ref.watch(inventoryProvider);
    final lowStockOnly = ref.watch(lowStockOnlyProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Inventory'),
        actions: const [NotificationBell(), SizedBox(width: 8)],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                FilterChip(
                  label: const Text('Low stock only'),
                  selected: lowStockOnly,
                  onSelected: (v) => ref.read(lowStockOnlyProvider.notifier).state = v,
                ),
                const Spacer(),
                OutlinedButton.icon(
                  onPressed: () => context.push('/inventory/transfers'),
                  icon: const Icon(Icons.swap_horiz, size: 18),
                  label: const Text('Transfers'),
                ),
              ],
            ),
          ),
          Expanded(
            child: inventoryAsync.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(
                message: e is ApiException ? e.message : 'Failed to load inventory.',
                onRetry: () => ref.read(inventoryProvider.notifier).refresh(),
              ),
              data: (paginated) => RefreshIndicator(
                onRefresh: () => ref.read(inventoryProvider.notifier).refresh(),
                child: paginated.items.isEmpty
                    ? ListView(children: const [
                        SizedBox(height: 120),
                        EmptyState(message: 'No inventory records.', icon: Icons.inventory_2_outlined),
                      ])
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                        itemCount: paginated.items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (context, index) {
                          final inv = paginated.items[index];
                          final warehouse = (inv['warehouse'] as Map?)?.cast<String, dynamic>() ?? {};
                          final listing = (inv['listing'] as Map?)?.cast<String, dynamic>() ?? {};
                          final variant = (listing['product_variant'] as Map?)?.cast<String, dynamic>() ?? {};
                          final available = (inv['quantity_available'] as num?)?.toInt() ?? 0;
                          final reorder = (inv['reorder_point'] as num?)?.toInt();
                          final isLow = reorder != null && available <= reorder;

                          return PCard(
                            onTap: () => context.push('/inventory/${inv['id']}/movements'),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text('${variant['variant_name'] ?? variant['sku'] ?? 'Item #${inv['id']}'}',
                                          style: const TextStyle(fontWeight: FontWeight.w600)),
                                      const SizedBox(height: 4),
                                      Text('${warehouse['name'] ?? '-'} · Bin ${inv['bin_location'] ?? '-'}',
                                          style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                                    ],
                                  ),
                                ),
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.end,
                                  children: [
                                    Text('$available available',
                                        style: TextStyle(
                                          fontWeight: FontWeight.w600,
                                          color: isLow ? AppTheme.error : AppTheme.textPrimary,
                                        )),
                                    Text('${inv['quantity_reserved'] ?? 0} reserved',
                                        style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
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

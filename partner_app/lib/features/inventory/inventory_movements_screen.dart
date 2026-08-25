import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/date_formatter.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import 'inventory_provider.dart';

class InventoryMovementsScreen extends ConsumerWidget {
  final String inventoryId;

  const InventoryMovementsScreen({super.key, required this.inventoryId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final movementsAsync = ref.watch(inventoryMovementsProvider(inventoryId));

    return Scaffold(
      appBar: AppBar(title: const Text('Stock Movements')),
      body: movementsAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load movements.',
          onRetry: () => ref.invalidate(inventoryMovementsProvider(inventoryId)),
        ),
        data: (paginated) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(inventoryMovementsProvider(inventoryId)),
          child: paginated.items.isEmpty
              ? ListView(children: const [
                  SizedBox(height: 120),
                  EmptyState(message: 'No stock movements recorded.', icon: Icons.swap_vert),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: paginated.items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final m = paginated.items[index];
                    final delta = (m['quantity_delta'] as num?)?.toInt() ?? 0;
                    return PCard(
                      child: Row(
                        children: [
                          Icon(
                            delta >= 0 ? Icons.arrow_upward : Icons.arrow_downward,
                            color: delta >= 0 ? AppTheme.success : AppTheme.error,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('${m['movement_type'] ?? '-'}', style: const TextStyle(fontWeight: FontWeight.w600)),
                                if (m['reason'] != null)
                                  Text('${m['reason']}', style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                                Text(DateFormatter.dateTime(DateFormatter.parse(m['created_at'] as String?)),
                                    style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                              ],
                            ),
                          ),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text('${delta >= 0 ? '+' : ''}$delta',
                                  style: TextStyle(
                                      fontWeight: FontWeight.bold, color: delta >= 0 ? AppTheme.success : AppTheme.error)),
                              Text('→ ${m['quantity_after'] ?? '-'}',
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
    );
  }
}

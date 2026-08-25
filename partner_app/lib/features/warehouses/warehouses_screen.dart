import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import 'warehouses_provider.dart';

class WarehousesScreen extends ConsumerWidget {
  const WarehousesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final warehousesAsync = ref.watch(warehousesProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Warehouses')),
      body: warehousesAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load warehouses.',
          onRetry: () => ref.invalidate(warehousesProvider),
        ),
        data: (warehouses) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(warehousesProvider),
          child: warehouses.isEmpty
              ? ListView(children: const [
                  SizedBox(height: 120),
                  EmptyState(message: 'No warehouses assigned to your store.', icon: Icons.warehouse_outlined),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: warehouses.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final w = warehouses[index];
                    final country = (w['country'] as Map?)?.cast<String, dynamic>() ?? {};
                    final address = (w['address'] as Map?)?.cast<String, dynamic>() ?? {};
                    final used = (w['used_capacity_m3'] as num?)?.toDouble();
                    final total = (w['total_capacity_m3'] as num?)?.toDouble();

                    return PCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(child: Text('${w['name'] ?? '-'}', style: const TextStyle(fontWeight: FontWeight.w600))),
                              Icon(
                                w['is_active'] == true ? Icons.check_circle : Icons.pause_circle_outline,
                                color: w['is_active'] == true ? AppTheme.success : AppTheme.textSecondary,
                                size: 18,
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text('${w['code'] ?? '-'} · ${w['type'] ?? '-'}', style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                          if (address['area'] != null || country['name'] != null) ...[
                            const SizedBox(height: 4),
                            Text([address['area'], country['name']].where((e) => e != null).join(', '),
                                style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                          ],
                          if (used != null && total != null) ...[
                            const SizedBox(height: 8),
                            LinearProgressIndicator(
                              value: total > 0 ? (used / total).clamp(0, 1) : 0,
                              backgroundColor: AppTheme.background,
                              color: AppTheme.primary,
                            ),
                            const SizedBox(height: 4),
                            Text('${used.toStringAsFixed(1)} / ${total.toStringAsFixed(1)} m³',
                                style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                          ],
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

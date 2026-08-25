import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/date_formatter.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import '../../shared/widgets/status_chip.dart';
import 'inventory_provider.dart';

class TransfersListScreen extends ConsumerWidget {
  const TransfersListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final transfersAsync = ref.watch(transfersProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Inventory Transfers')),
      body: transfersAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load transfers.',
          onRetry: () => ref.invalidate(transfersProvider),
        ),
        data: (paginated) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(transfersProvider),
          child: paginated.items.isEmpty
              ? ListView(children: const [
                  SizedBox(height: 120),
                  EmptyState(message: 'No inventory transfers.', icon: Icons.swap_horiz),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: paginated.items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final t = paginated.items[index];
                    final source = (t['source_warehouse'] as Map?)?.cast<String, dynamic>() ?? {};
                    final dest = (t['destination_warehouse'] as Map?)?.cast<String, dynamic>() ?? {};
                    return PCard(
                      onTap: () => context.push('/inventory/transfers/${t['transfer_number']}'),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                  child: Text('${t['transfer_number'] ?? '-'}',
                                      style: const TextStyle(fontWeight: FontWeight.w600))),
                              if (t['status'] != null) StatusChip(status: '${t['status']}'),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text('${source['name'] ?? '-'} → ${dest['name'] ?? '-'}',
                              style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                          const SizedBox(height: 4),
                          Text(DateFormatter.relative(DateFormatter.parse(t['created_at'] as String?)),
                              style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
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

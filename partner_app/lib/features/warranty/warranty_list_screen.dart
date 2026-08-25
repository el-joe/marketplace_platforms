import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/date_formatter.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/notification_bell.dart';
import '../../shared/widgets/p_card.dart';
import '../../shared/widgets/status_chip.dart';
import 'warranty_provider.dart';

class WarrantyListScreen extends ConsumerWidget {
  const WarrantyListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final warrantyAsync = ref.watch(warrantyProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Warranty Claims'),
        actions: const [NotificationBell(), SizedBox(width: 8)],
      ),
      body: warrantyAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load warranty claims.',
          onRetry: () => ref.read(warrantyProvider.notifier).refresh(),
        ),
        data: (paginated) => RefreshIndicator(
          onRefresh: () => ref.read(warrantyProvider.notifier).refresh(),
          child: paginated.items.isEmpty
              ? ListView(children: const [
                  SizedBox(height: 120),
                  EmptyState(message: 'No warranty claims.', icon: Icons.verified_outlined),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: paginated.items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final c = paginated.items[index];
                    final customer = (c['customer'] as Map?)?.cast<String, dynamic>() ?? {};
                    return PCard(
                      onTap: () => context.push('/warranty/${c['id']}'),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                  child: Text('${c['claim_number'] ?? '-'}',
                                      style: const TextStyle(fontWeight: FontWeight.w600))),
                              if (c['status'] != null) StatusChip(status: '${c['status']}'),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text('${customer['name'] ?? 'Customer'} · ${c['issue_type'] ?? '-'}',
                              style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                          const SizedBox(height: 4),
                          Text(DateFormatter.relative(DateFormatter.parse(c['created_at'] as String?)),
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

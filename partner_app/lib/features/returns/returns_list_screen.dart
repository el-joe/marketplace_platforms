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
import 'returns_provider.dart';

class ReturnsListScreen extends ConsumerWidget {
  const ReturnsListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final returnsAsync = ref.watch(returnsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Returns'),
        actions: const [NotificationBell(), SizedBox(width: 8)],
      ),
      body: returnsAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load returns.',
          onRetry: () => ref.read(returnsProvider.notifier).refresh(),
        ),
        data: (paginated) => RefreshIndicator(
          onRefresh: () => ref.read(returnsProvider.notifier).refresh(),
          child: paginated.items.isEmpty
              ? ListView(children: const [
                  SizedBox(height: 120),
                  EmptyState(message: 'No return requests.', icon: Icons.assignment_return_outlined),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: paginated.items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final r = paginated.items[index];
                    return PCard(
                      onTap: () => context.push('/returns/${r['return_number']}'),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                  child: Text('${r['return_number'] ?? '-'}',
                                      style: const TextStyle(fontWeight: FontWeight.w600))),
                              if (r['status'] != null) StatusChip(status: '${r['status']}'),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text('${r['customer_name'] ?? 'Customer'} · ${r['reason'] ?? '-'}',
                              style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                          const SizedBox(height: 4),
                          Text(DateFormatter.relative(DateFormatter.parse(r['created_at'] as String?)),
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

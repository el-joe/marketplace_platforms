import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/empty_view.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/status_chip.dart';
import 'reports_provider.dart';

class ClaimsTab extends ConsumerWidget {
  const ClaimsTab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final claimsAsync = ref.watch(claimsProvider);

    return claimsAsync.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(
        message: e is ApiException ? e.message : 'Failed to load claims.',
        onRetry: () => ref.read(claimsProvider.notifier).refresh(),
      ),
      data: (paginated) => paginated.items.isEmpty
          ? const EmptyView(message: 'No claims found.', icon: Icons.report_gmailerrorred_outlined)
          : RefreshIndicator(
              onRefresh: () => ref.read(claimsProvider.notifier).refresh(),
              child: ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: paginated.items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 12),
                itemBuilder: (context, index) {
                  final claim = paginated.items[index];
                  return DCard(
                    onTap: () => context.push('/reports/claims/${claim.id}'),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(claim.claimNumber ?? '-', style: const TextStyle(fontWeight: FontWeight.w600)),
                              const SizedBox(height: 4),
                              Text(claim.deliveryAgent?.name ?? '-',
                                  style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                              if (claim.claimedAmount != null) ...[
                                const SizedBox(height: 4),
                                Text('Claimed: ${MoneyFormatter.format(claim.claimedAmount!, '')}',
                                    style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                              ],
                            ],
                          ),
                        ),
                        StatusChip(status: claim.status),
                      ],
                    ),
                  );
                },
              ),
            ),
    );
  }
}

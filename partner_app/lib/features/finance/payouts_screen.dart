import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import '../../shared/widgets/status_chip.dart';
import 'finance_provider.dart';

class PayoutsScreen extends ConsumerWidget {
  const PayoutsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final payoutsAsync = ref.watch(payoutsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Payouts')),
      body: payoutsAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load payouts.',
          onRetry: () => ref.read(payoutsProvider.notifier).refresh(),
        ),
        data: (paginated) => RefreshIndicator(
          onRefresh: () => ref.read(payoutsProvider.notifier).refresh(),
          child: paginated.items.isEmpty
              ? ListView(children: const [
                  SizedBox(height: 120),
                  EmptyState(message: 'No payouts yet.', icon: Icons.payments_outlined),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: paginated.items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final p = paginated.items[index];
                    return PCard(
                      onTap: () => context.push('/finance/payouts/${p['id']}'),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(child: Text('${p['payout_number'] ?? '-'}', style: const TextStyle(fontWeight: FontWeight.w600))),
                              if (p['status'] != null) StatusChip(status: '${p['status']}'),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text('${p['period_start'] ?? '-'} → ${p['period_end'] ?? '-'}',
                              style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                          const SizedBox(height: 6),
                          Text(MoneyFormatter.format(p['net_amount'] as num?, p['currency'] as String?),
                              style: const TextStyle(fontWeight: FontWeight.w600, color: AppTheme.success)),
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

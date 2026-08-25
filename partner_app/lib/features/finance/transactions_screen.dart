import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import 'finance_provider.dart';

class TransactionsScreen extends ConsumerWidget {
  const TransactionsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final transactionsAsync = ref.watch(transactionsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Transactions')),
      body: transactionsAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load transactions.',
          onRetry: () => ref.invalidate(transactionsProvider),
        ),
        data: (body) {
          final items = (body['items'] as List? ?? []).cast<Map<String, dynamic>>();
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(transactionsProvider),
            child: items.isEmpty
                ? ListView(children: const [
                    SizedBox(height: 120),
                    EmptyState(message: 'No transactions yet.', icon: Icons.receipt_outlined),
                  ])
                : ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: items.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      final t = items[index];
                      final type = t['type'] as String?;
                      final amount = (t['net'] ?? t['amount']) as num?;
                      final isNegative = type == 'refund';
                      return PCard(
                        child: Row(
                          children: [
                            Icon(
                              switch (type) {
                                'sale' => Icons.arrow_downward,
                                'refund' => Icons.arrow_upward,
                                'payout' => Icons.account_balance_wallet_outlined,
                                _ => Icons.swap_horiz,
                              },
                              color: isNegative ? AppTheme.error : AppTheme.success,
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('${t['description'] ?? type ?? '-'}', style: const TextStyle(fontWeight: FontWeight.w600)),
                                  Text('${t['reference'] ?? ''} · ${t['date'] ?? ''}',
                                      style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                                ],
                              ),
                            ),
                            Text(
                              '${isNegative ? '-' : ''}${MoneyFormatter.format(amount, t['currency'] as String?)}',
                              style: TextStyle(fontWeight: FontWeight.w600, color: isNegative ? AppTheme.error : AppTheme.success),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
          );
        },
      ),
    );
  }
}

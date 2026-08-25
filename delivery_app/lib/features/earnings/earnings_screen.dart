import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/utils/date_formatter.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/empty_view.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import 'earnings_provider.dart';

class EarningsScreen extends ConsumerWidget {
  const EarningsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final earningsAsync = ref.watch(earningsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Earnings'),
        actions: [
          IconButton(
            icon: const Icon(Icons.account_balance_wallet_outlined),
            onPressed: () => context.push('/wallet'),
            tooltip: 'Wallet',
          ),
          IconButton(
            icon: const Icon(Icons.receipt_long_outlined),
            onPressed: () => context.push('/cod-settlements'),
            tooltip: 'COD Settlements',
          ),
        ],
      ),
      body: earningsAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load earnings.',
          onRetry: () => ref.read(earningsProvider.notifier).refresh(),
        ),
        data: (summary) => RefreshIndicator(
          onRefresh: () => ref.read(earningsProvider.notifier).refresh(),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              DCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Today', style: TextStyle(color: Colors.grey.shade600)),
                    const SizedBox(height: 4),
                    Text(
                      MoneyFormatter.format(summary.todayTotal, summary.currency),
                      style: const TextStyle(fontSize: 28, fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
              if (summary.days.isEmpty)
                const EmptyView(message: 'No earnings recorded yet.', icon: Icons.payments_outlined)
              else
                ...summary.days.map((day) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: DCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Text(day.date, style: const TextStyle(fontWeight: FontWeight.w600)),
                                const Spacer(),
                                Text(MoneyFormatter.format(day.total, summary.currency)),
                              ],
                            ),
                            const Divider(height: 20),
                            ...day.earnings.map((e) => Padding(
                                  padding: const EdgeInsets.symmetric(vertical: 4),
                                  child: Row(
                                    children: [
                                      Expanded(
                                        child: Text(e.earningType ?? '-',
                                            style: TextStyle(color: Colors.grey.shade700)),
                                      ),
                                      Text(DateFormatter.time(e.earnedAt),
                                          style: TextStyle(color: Colors.grey.shade500, fontSize: 12)),
                                      const SizedBox(width: 12),
                                      Text(MoneyFormatter.format(e.amount, e.currency ?? summary.currency)),
                                    ],
                                  ),
                                )),
                          ],
                        ),
                      ),
                    )),
            ],
          ),
        ),
      ),
    );
  }
}

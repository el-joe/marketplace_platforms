import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/date_formatter.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/empty_view.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import 'wallet_provider.dart';

class WalletScreen extends ConsumerWidget {
  const WalletScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final walletAsync = ref.watch(walletProvider);
    final transactionsAsync = ref.watch(walletTransactionsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Wallet')),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(walletProvider);
          ref.invalidate(walletTransactionsProvider);
        },
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            walletAsync.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(message: e is ApiException ? e.message : 'Failed to load wallet.'),
              data: (wallet) => DCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Balance', style: TextStyle(color: Colors.grey.shade600)),
                    const SizedBox(height: 4),
                    Text(
                      MoneyFormatter.format(wallet.balance, wallet.currency),
                      style: const TextStyle(fontSize: 28, fontWeight: FontWeight.bold),
                    ),
                    if (wallet.pendingBalance != 0) ...[
                      const SizedBox(height: 4),
                      Text('Pending: ${MoneyFormatter.format(wallet.pendingBalance, wallet.currency)}',
                          style: TextStyle(color: Colors.grey.shade600)),
                    ],
                    if (wallet.isFrozen) ...[
                      const SizedBox(height: 8),
                      const Text('Wallet is frozen — withdrawals unavailable.',
                          style: TextStyle(color: AppTheme.danger)),
                    ],
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: wallet.isFrozen ? null : () => context.push('/wallet/withdraw'),
                        child: const Text('Request Withdrawal'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),
            Text('Transactions', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            transactionsAsync.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(message: e is ApiException ? e.message : 'Failed to load transactions.'),
              data: (paginated) => paginated.items.isEmpty
                  ? const EmptyView(message: 'No transactions yet.', icon: Icons.receipt_long_outlined)
                  : Column(
                      children: paginated.items
                          .map((t) => DCard(
                                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(t.description ?? t.type ?? '-'),
                                          Text(DateFormatter.dateTime(t.createdAt),
                                              style: TextStyle(color: Colors.grey.shade500, fontSize: 12)),
                                        ],
                                      ),
                                    ),
                                    Text(
                                      '${t.amount >= 0 ? '+' : ''}${t.amount}',
                                      style: TextStyle(
                                        fontWeight: FontWeight.w600,
                                        color: t.amount >= 0 ? AppTheme.success : AppTheme.danger,
                                      ),
                                    ),
                                  ],
                                ),
                              ))
                          .map((w) => Padding(padding: const EdgeInsets.only(bottom: 8), child: w))
                          .toList(),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

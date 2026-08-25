import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/notification_bell.dart';
import '../../shared/widgets/p_card.dart';
import '../auth/auth_provider.dart';
import 'finance_provider.dart';

/// Finance tab landing screen — summary at a glance, with quick links to
/// payouts, ledger, commission rates, sales report and bank accounts (each
/// their own read-only screen given how much data every one of them holds).
class FinanceScreen extends ConsumerWidget {
  const FinanceScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final summaryAsync = ref.watch(financeSummaryProvider);
    final currency = ref.watch(authProvider).admin?.vendor.currencyCode;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Finance'),
        actions: const [NotificationBell(), SizedBox(width: 8)],
      ),
      body: summaryAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load finance summary.',
          onRetry: () => ref.invalidate(financeSummaryProvider),
        ),
        data: (s) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(financeSummaryProvider),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              PCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Pending payout', style: TextStyle(color: AppTheme.textSecondary)),
                    const SizedBox(height: 6),
                    Text(MoneyFormatter.format(s['pending_payout'] as num?, currency),
                        style: const TextStyle(fontSize: 24, fontWeight: FontWeight.bold, color: AppTheme.success)),
                    if (s['payout_on_hold'] == true) ...[
                      const SizedBox(height: 6),
                      const Text('Payout on hold', style: TextStyle(color: AppTheme.error)),
                    ],
                    const SizedBox(height: 10),
                    Text('Next payout: ${s['next_payout_date'] ?? '-'} (${s['payout_schedule'] ?? '-'})',
                        style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                    Text('Last payout: ${s['last_payout_date'] ?? '-'}',
                        style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _StatTile(
                      label: 'Total GMV',
                      value: MoneyFormatter.format(s['total_gmv'] as num?, currency),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _StatTile(
                      label: 'Commission paid',
                      value: MoneyFormatter.format(s['total_commission_paid'] as num?, currency),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              Text('Details', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              _MenuTile(icon: Icons.payments_outlined, label: 'Payouts', onTap: () => context.push('/finance/payouts')),
              _MenuTile(icon: Icons.receipt_outlined, label: 'Transactions', onTap: () => context.push('/finance/transactions')),
              _MenuTile(icon: Icons.list_alt_outlined, label: 'Ledger', onTap: () => context.push('/finance/ledger')),
              _MenuTile(icon: Icons.percent_outlined, label: 'Commission rates', onTap: () => context.push('/finance/commission-rates')),
              _MenuTile(icon: Icons.summarize_outlined, label: 'Sales report', onTap: () => context.push('/finance/sales-report')),
              _MenuTile(icon: Icons.account_balance_outlined, label: 'Bank accounts', onTap: () => context.push('/finance/bank-accounts')),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatTile extends StatelessWidget {
  final String label;
  final String value;

  const _StatTile({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return PCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
          const SizedBox(height: 6),
          Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15), overflow: TextOverflow.ellipsis),
        ],
      ),
    );
  }
}

class _MenuTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _MenuTile({required this.icon, required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: PCard(
        onTap: onTap,
        child: Row(
          children: [
            Icon(icon, color: AppTheme.textSecondary),
            const SizedBox(width: 12),
            Expanded(child: Text(label)),
            const Icon(Icons.chevron_right, color: AppTheme.textSecondary),
          ],
        ),
      ),
    );
  }
}

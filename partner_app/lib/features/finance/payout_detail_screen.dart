import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import '../../shared/widgets/status_chip.dart';
import 'finance_provider.dart';

class PayoutDetailScreen extends ConsumerWidget {
  final int id;

  const PayoutDetailScreen({super.key, required this.id});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final payoutAsync = ref.watch(payoutDetailProvider(id));

    return Scaffold(
      appBar: AppBar(title: const Text('Payout')),
      body: payoutAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load payout.',
          onRetry: () => ref.invalidate(payoutDetailProvider(id)),
        ),
        data: (p) {
          final currency = p['currency'] as String?;
          final deductions = (p['deductions'] as Map?)?.cast<String, dynamic>() ?? {};
          final bankAccount = (p['bank_account'] as Map?)?.cast<String, dynamic>();
          final items = (p['items'] as List? ?? []).cast<Map<String, dynamic>>();

          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(payoutDetailProvider(id)),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                PCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('${p['payout_number'] ?? '-'}', style: const TextStyle(fontWeight: FontWeight.w600)),
                          if (p['status'] != null) StatusChip(status: '${p['status']}'),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text('${p['period_start'] ?? '-'} → ${p['period_end'] ?? '-'}',
                          style: const TextStyle(color: AppTheme.textSecondary)),
                      if (p['gateway_reference'] != null)
                        Text('Ref: ${p['gateway_reference']}', style: const TextStyle(color: AppTheme.textSecondary)),
                      if (p['failed_reason'] != null)
                        Text('Failed: ${p['failed_reason']}', style: const TextStyle(color: AppTheme.error)),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                Text('Deductions', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                PCard(
                  child: Column(
                    children: [
                      _row('Commission', MoneyFormatter.format(deductions['commission'] as num?, currency)),
                      _row('Refunds', MoneyFormatter.format(deductions['refunds_deducted'] as num?, currency)),
                      _row('Chargebacks', MoneyFormatter.format(deductions['chargebacks_deducted'] as num?, currency)),
                      _row('Storage fees', MoneyFormatter.format(deductions['storage_fees'] as num?, currency)),
                      _row('Ad fees', MoneyFormatter.format(deductions['ad_fees'] as num?, currency)),
                      _row('Other adjustments', MoneyFormatter.format(deductions['other_adjustments'] as num?, currency)),
                      const Divider(),
                      _row('Gross sales', MoneyFormatter.format(p['gross_sales'] as num?, currency)),
                      _row('Net amount', MoneyFormatter.format(p['net_amount'] as num?, currency), bold: true),
                    ],
                  ),
                ),
                if (bankAccount != null) ...[
                  const SizedBox(height: 16),
                  Text('Bank account', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  PCard(
                    child: Text('${bankAccount['bank_name'] ?? '-'} · ${bankAccount['iban'] ?? '-'}'),
                  ),
                ],
                if (items.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Text('Sub-orders', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  ...items.map((item) => Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: PCard(
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text('Sub-order #${item['sub_order_id'] ?? '-'}'),
                              Text(MoneyFormatter.format(item['net'] as num?, currency)),
                            ],
                          ),
                        ),
                      )),
                ],
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _row(String label, String value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppTheme.textSecondary)),
          Text(value, style: TextStyle(fontWeight: bold ? FontWeight.bold : FontWeight.normal)),
        ],
      ),
    );
  }
}

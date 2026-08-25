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

class SalesReportScreen extends ConsumerWidget {
  const SalesReportScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final reportAsync = ref.watch(salesReportProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Sales Report')),
      body: reportAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load sales report.',
          onRetry: () => ref.invalidate(salesReportProvider),
        ),
        data: (report) {
          final currency = report['currency'] as String?;
          final totals = (report['totals'] as Map?)?.cast<String, dynamic>() ?? {};
          final shipments = (report['shipments'] as Map?)?.cast<String, dynamic>();
          final shipmentItems = (shipments?['data'] as List? ?? []).cast<Map<String, dynamic>>();

          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(salesReportProvider),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text('${report['date_from'] ?? ''} → ${report['date_to'] ?? ''}',
                    style: const TextStyle(color: AppTheme.textSecondary)),
                const SizedBox(height: 12),
                PCard(
                  child: Column(
                    children: [
                      _row('Shipping charged to customers',
                          MoneyFormatter.format(totals['total_shipping_charged_to_customers'] as num?, currency)),
                      _row('Platform subsidy', MoneyFormatter.format(totals['total_platform_subsidy'] as num?, currency)),
                      _row('Your shipping contribution',
                          MoneyFormatter.format(totals['total_vendor_shipping_contribution'] as num?, currency)),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                Text('Shipments', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                if (shipmentItems.isEmpty)
                  const EmptyState(message: 'No shipments in this period.', icon: Icons.local_shipping_outlined)
                else
                  ...shipmentItems.map((s) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: PCard(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('${s['sub_order_number'] ?? '-'}', style: const TextStyle(fontWeight: FontWeight.w600)),
                              const SizedBox(height: 4),
                              Text('${s['date'] ?? ''}', style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                              const SizedBox(height: 6),
                              Text('Your contribution: ${MoneyFormatter.format(s['your_delivery_contribution'] as num?, currency)}'),
                            ],
                          ),
                        ),
                      )),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _row(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(child: Text(label, style: const TextStyle(color: AppTheme.textSecondary))),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}

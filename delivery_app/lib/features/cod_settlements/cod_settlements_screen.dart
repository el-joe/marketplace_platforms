import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/utils/date_formatter.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/empty_view.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/status_chip.dart';
import 'cod_settlement_provider.dart';

class CodSettlementsScreen extends ConsumerWidget {
  const CodSettlementsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final currentAsync = ref.watch(currentCodProvider);
    final historyAsync = ref.watch(codSettlementsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('COD Settlements')),
      body: RefreshIndicator(
        onRefresh: () async {
          ref.invalidate(currentCodProvider);
          ref.invalidate(codSettlementsProvider);
        },
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text('Current (unsettled)', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            currentAsync.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(message: e is ApiException ? e.message : 'Failed to load.'),
              data: (current) => DCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _row('COD collected', MoneyFormatter.format(current.codTotal, current.currency)),
                    _row('Earnings owed', MoneyFormatter.format(current.earningsTotal, current.currency)),
                    const Divider(height: 20),
                    _row('Net to remit', MoneyFormatter.format(current.netToRemit, current.currency), bold: true),
                    if (current.deliveries.isNotEmpty) ...[
                      const SizedBox(height: 12),
                      ...current.deliveries.map((d) => Padding(
                            padding: const EdgeInsets.symmetric(vertical: 2),
                            child: Text('${d.subOrderNumber ?? '#${d.id}'} — ${MoneyFormatter.format(d.codCollected, current.currency)}'),
                          )),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),
            Text('Settlement history', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            historyAsync.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(message: e is ApiException ? e.message : 'Failed to load.'),
              data: (paginated) => paginated.items.isEmpty
                  ? const EmptyView(message: 'No settlements yet.', icon: Icons.receipt_long_outlined)
                  : Column(
                      children: paginated.items
                          .map((s) => Padding(
                                padding: const EdgeInsets.only(bottom: 12),
                                child: DCard(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        children: [
                                          Text('${s.periodStart ?? ''} - ${s.periodEnd ?? ''}'),
                                          const Spacer(),
                                          StatusChip(status: s.status ?? ''),
                                        ],
                                      ),
                                      const SizedBox(height: 8),
                                      _row('Net remitted', MoneyFormatter.format(s.netToRemit, 'AED')),
                                      if (s.settledAt != null) _row('Settled', DateFormatter.date(s.settledAt)),
                                    ],
                                  ),
                                ),
                              ))
                          .toList(),
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _row(String label, String value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          Text(label, style: TextStyle(color: Colors.grey.shade600)),
          const Spacer(),
          Text(value, style: TextStyle(fontWeight: bold ? FontWeight.bold : FontWeight.normal)),
        ],
      ),
    );
  }
}

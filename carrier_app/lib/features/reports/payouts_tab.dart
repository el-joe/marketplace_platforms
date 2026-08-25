import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/utils/date_formatter.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/empty_view.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/status_chip.dart';
import 'reports_provider.dart';
import 'widgets/horizontal_data_table.dart';
import 'widgets/report_stat_row.dart';

class PayoutsTab extends ConsumerWidget {
  const PayoutsTab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final payoutsAsync = ref.watch(payoutsReportProvider);

    return payoutsAsync.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(
        message: e is ApiException ? e.message : 'Failed to load payouts report.',
        onRetry: () => ref.invalidate(payoutsReportProvider),
      ),
      data: (data) {
        final stats = (data['stats'] as Map?)?.cast<String, dynamic>() ?? {};
        final currency = data['currency'] as String? ?? '';
        final items = (data['items'] as List? ?? []).cast<Map<String, dynamic>>();

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(payoutsReportProvider),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              ReportStatRow(stats: [
                ReportStat(label: 'Total Net Paid', value: MoneyFormatter.formatCompact((stats['total_net_paid'] as num?) ?? 0, currency)),
                ReportStat(label: 'Pending Count', value: '${stats['pending_count'] ?? 0}'),
              ]),
              const SizedBox(height: 20),
              if (items.isEmpty)
                const EmptyView(message: 'No payouts found.')
              else
                HorizontalDataTable(
                  columns: const [
                    DataColumn(label: Text('Payout #')),
                    DataColumn(label: Text('Agent')),
                    DataColumn(label: Text('Period')),
                    DataColumn(label: Text('Deliveries')),
                    DataColumn(label: Text('Net Amount')),
                    DataColumn(label: Text('Status')),
                  ],
                  rows: items
                      .map((row) => DataRow(cells: [
                            DataCell(Text(row['payout_number']?.toString() ?? '-')),
                            DataCell(Text(row['agent_name']?.toString() ?? '-')),
                            DataCell(Text(
                                '${DateFormatter.date(DateTime.tryParse(row['period_start']?.toString() ?? ''))} - ${DateFormatter.date(DateTime.tryParse(row['period_end']?.toString() ?? ''))}')),
                            DataCell(Text('${row['total_deliveries'] ?? 0}')),
                            DataCell(Text(MoneyFormatter.format((row['net_amount'] as num?) ?? 0, row['currency']?.toString() ?? currency))),
                            DataCell(StatusChip(status: row['status']?.toString() ?? '')),
                          ]))
                      .toList(),
                ),
            ],
          ),
        );
      },
    );
  }
}

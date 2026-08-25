import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/empty_view.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import 'reports_provider.dart';
import 'widgets/horizontal_data_table.dart';
import 'widgets/report_stat_row.dart';

class EarningsTab extends ConsumerWidget {
  const EarningsTab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final earningsAsync = ref.watch(earningsReportProvider);

    return earningsAsync.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(
        message: e is ApiException ? e.message : 'Failed to load earnings report.',
        onRetry: () => ref.invalidate(earningsReportProvider),
      ),
      data: (data) {
        final stats = (data['stats'] as Map?)?.cast<String, dynamic>() ?? {};
        final currency = data['currency'] as String? ?? '';
        final agentSummary = (data['agent_summary'] as List? ?? []).cast<Map<String, dynamic>>();

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(earningsReportProvider),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              ReportStatRow(stats: [
                ReportStat(label: 'Total Gross', value: MoneyFormatter.formatCompact((stats['total_gross'] as num?) ?? 0, currency)),
                ReportStat(label: 'Pending', value: MoneyFormatter.formatCompact((stats['pending'] as num?) ?? 0, currency)),
                ReportStat(label: 'Paid', value: MoneyFormatter.formatCompact((stats['paid'] as num?) ?? 0, currency)),
              ]),
              const SizedBox(height: 20),
              Text('By Agent', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 12),
              if (agentSummary.isEmpty)
                const EmptyView(message: 'No earnings recorded.')
              else
                HorizontalDataTable(
                  columns: const [
                    DataColumn(label: Text('Agent')),
                    DataColumn(label: Text('Base Fee')),
                    DataColumn(label: Text('COD Handling')),
                    DataColumn(label: Text('Bonus')),
                    DataColumn(label: Text('Tip')),
                    DataColumn(label: Text('Deductions')),
                    DataColumn(label: Text('Total')),
                  ],
                  rows: agentSummary
                      .map((row) => DataRow(cells: [
                            DataCell(Text(row['agent_name']?.toString() ?? '-')),
                            DataCell(Text(MoneyFormatter.format((row['base_fee'] as num?) ?? 0, currency))),
                            DataCell(Text(MoneyFormatter.format((row['cod_handling'] as num?) ?? 0, currency))),
                            DataCell(Text(MoneyFormatter.format((row['bonus'] as num?) ?? 0, currency))),
                            DataCell(Text(MoneyFormatter.format((row['tip'] as num?) ?? 0, currency))),
                            DataCell(Text(MoneyFormatter.format((row['deductions'] as num?) ?? 0, currency))),
                            DataCell(Text(MoneyFormatter.format((row['total'] as num?) ?? 0, currency))),
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

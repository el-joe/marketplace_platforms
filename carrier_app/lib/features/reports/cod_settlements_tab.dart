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

class CodSettlementsTab extends ConsumerWidget {
  const CodSettlementsTab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final codAsync = ref.watch(codSettlementsReportProvider);

    return codAsync.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(
        message: e is ApiException ? e.message : 'Failed to load COD settlements report.',
        onRetry: () => ref.invalidate(codSettlementsReportProvider),
      ),
      data: (data) {
        final stats = (data['stats'] as Map?)?.cast<String, dynamic>() ?? {};
        final currency = data['currency'] as String? ?? '';
        final items = (data['items'] as List? ?? []).cast<Map<String, dynamic>>();

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(codSettlementsReportProvider),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              ReportStatRow(stats: [
                ReportStat(label: 'Pending Cash', value: MoneyFormatter.formatCompact((stats['pending_cash'] as num?) ?? 0, currency)),
                ReportStat(label: 'Settled (Month)', value: MoneyFormatter.formatCompact((stats['settled_month'] as num?) ?? 0, currency)),
                ReportStat(label: 'Disputed', value: '${stats['disputed'] ?? 0}'),
                ReportStat(label: 'Discrepancies', value: '${stats['discrepancies'] ?? 0}'),
              ]),
              const SizedBox(height: 20),
              if (items.isEmpty)
                const EmptyView(message: 'No COD settlements found.')
              else
                HorizontalDataTable(
                  columns: const [
                    DataColumn(label: Text('Agent')),
                    DataColumn(label: Text('Period')),
                    DataColumn(label: Text('Collected')),
                    DataColumn(label: Text('Net to Remit')),
                    DataColumn(label: Text('Status')),
                  ],
                  rows: items
                      .map((row) => DataRow(cells: [
                            DataCell(Text(row['agent_name']?.toString() ?? '-')),
                            DataCell(Text(
                                '${DateFormatter.date(DateTime.tryParse(row['period_start']?.toString() ?? ''))} - ${DateFormatter.date(DateTime.tryParse(row['period_end']?.toString() ?? ''))}')),
                            DataCell(Text(MoneyFormatter.format((row['total_cod_collected'] as num?) ?? 0, currency))),
                            DataCell(Text(MoneyFormatter.format((row['net_to_remit'] as num?) ?? 0, currency))),
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

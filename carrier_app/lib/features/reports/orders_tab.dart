import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/empty_view.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/status_chip.dart';
import 'reports_provider.dart';
import 'widgets/horizontal_data_table.dart';
import 'widgets/report_stat_row.dart';

class OrdersTab extends ConsumerWidget {
  const OrdersTab({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersAsync = ref.watch(ordersReportProvider);

    return ordersAsync.when(
      loading: () => const LoadingView(),
      error: (e, _) => ErrorView(
        message: e is ApiException ? e.message : 'Failed to load orders report.',
        onRetry: () => ref.invalidate(ordersReportProvider),
      ),
      data: (data) {
        final stats = (data['stats'] as Map?)?.cast<String, dynamic>() ?? {};
        final currency = data['currency'] as String? ?? '';
        final items = (data['items'] as List? ?? []).cast<Map<String, dynamic>>();

        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(ordersReportProvider),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              ReportStatRow(stats: [
                ReportStat(label: 'Total', value: '${stats['total'] ?? 0}'),
                ReportStat(label: 'Delivered', value: '${stats['delivered'] ?? 0}'),
                ReportStat(label: 'Failed', value: '${stats['failed'] ?? 0}'),
                ReportStat(
                  label: 'COD Unremitted',
                  value: MoneyFormatter.formatCompact((stats['cod_unremitted'] as num?) ?? 0, currency),
                ),
                ReportStat(
                  label: 'Shipping Revenue',
                  value: MoneyFormatter.formatCompact((stats['shipping_revenue'] as num?) ?? 0, currency),
                ),
              ]),
              const SizedBox(height: 20),
              if (items.isEmpty)
                const EmptyView(message: 'No orders found.')
              else
                HorizontalDataTable(
                  columns: const [
                    DataColumn(label: Text('Order')),
                    DataColumn(label: Text('Agent')),
                    DataColumn(label: Text('Status')),
                    DataColumn(label: Text('Payment')),
                    DataColumn(label: Text('Shipping Cost')),
                  ],
                  rows: items
                      .map((row) => DataRow(cells: [
                            DataCell(Text(row['order_number']?.toString() ?? row['sub_order_number']?.toString() ?? '-')),
                            DataCell(Text(row['agent_name']?.toString() ?? '-')),
                            DataCell(StatusChip(status: row['status']?.toString() ?? '')),
                            DataCell(Text(row['payment_method']?.toString() ?? '-')),
                            DataCell(Text(MoneyFormatter.format((row['carrier_shipping_cost'] as num?) ?? 0, currency))),
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

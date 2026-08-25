import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/date_formatter.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import '../../shared/widgets/status_chip.dart';
import 'orders_provider.dart';

class OrderDetailScreen extends ConsumerWidget {
  final String subOrderNumber;

  const OrderDetailScreen({super.key, required this.subOrderNumber});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orderAsync = ref.watch(orderDetailProvider(subOrderNumber));

    return Scaffold(
      appBar: AppBar(title: Text(subOrderNumber)),
      body: orderAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load order.',
          onRetry: () => ref.invalidate(orderDetailProvider(subOrderNumber)),
        ),
        data: (o) {
          final currency = o['currency'] as String?;
          final financials = (o['financials'] as Map?)?.cast<String, dynamic>() ?? {};
          final customer = (o['customer'] as Map?)?.cast<String, dynamic>() ?? {};
          final address = (customer['address'] as Map?)?.cast<String, dynamic>() ?? {};
          final items = (o['items'] as List? ?? []).cast<Map<String, dynamic>>();
          final trackingEvents = (o['tracking_events'] as List? ?? []).cast<Map<String, dynamic>>();

          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(orderDetailProvider(subOrderNumber)),
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
                          Text('Order #${o['order_number'] ?? '-'}', style: const TextStyle(fontWeight: FontWeight.w600)),
                          if (o['status'] != null) StatusChip(status: '${o['status']}'),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text('Placed: ${DateFormatter.dateTime(DateFormatter.parse(o['placed_at'] as String?))}',
                          style: const TextStyle(color: AppTheme.textSecondary)),
                      if (o['tracking_number'] != null)
                        Text('Tracking: ${o['tracking_number']} (${o['carrier_name'] ?? '-'})',
                            style: const TextStyle(color: AppTheme.textSecondary)),
                      if (o['sla_breached'] == true)
                        const Padding(
                          padding: EdgeInsets.only(top: 6),
                          child: Text('SLA breached', style: TextStyle(color: AppTheme.error)),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                Text('Customer', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                PCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${customer['name'] ?? '-'}'),
                      if (customer['phone'] != null) Text('${customer['phone']}'),
                      Text([address['city'], address['country']].where((e) => e != null).join(', ')),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                Text('Items (${items.length})', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                ...items.map((item) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: PCard(
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('${item['name_en'] ?? item['sku'] ?? 'Item'}',
                                      style: const TextStyle(fontWeight: FontWeight.w600)),
                                  const SizedBox(height: 4),
                                  Text('Qty ${item['quantity'] ?? 1} · SKU ${item['sku'] ?? '-'}',
                                      style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                                ],
                              ),
                            ),
                            Text(MoneyFormatter.format(item['line_total'] as num?, currency)),
                          ],
                        ),
                      ),
                    )),
                const SizedBox(height: 16),
                Text('Financials', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                PCard(
                  child: Column(
                    children: [
                      _row('Subtotal', MoneyFormatter.format(financials['subtotal'] as num?, currency)),
                      _row('Shipping', MoneyFormatter.format(financials['shipping'] as num?, currency)),
                      _row('Tax', MoneyFormatter.format(financials['tax'] as num?, currency)),
                      _row('Platform commission', MoneyFormatter.format(financials['platform_commission'] as num?, currency)),
                      _row('Gateway fee', MoneyFormatter.format(financials['gateway_fee'] as num?, currency)),
                      const Divider(),
                      _row('Your payout', MoneyFormatter.format(financials['vendor_payout'] as num?, currency), bold: true),
                    ],
                  ),
                ),
                if (trackingEvents.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Text('Tracking', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  PCard(
                    child: Column(
                      children: trackingEvents
                          .map((e) => Padding(
                                padding: const EdgeInsets.symmetric(vertical: 6),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Icon(Icons.circle, size: 8, color: AppTheme.primary),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text('${e['status'] ?? ''} — ${e['description'] ?? ''}'),
                                          Text(
                                            DateFormatter.dateTime(DateFormatter.parse(e['occurred_at'] as String?)),
                                            style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ))
                          .toList(),
                    ),
                  ),
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

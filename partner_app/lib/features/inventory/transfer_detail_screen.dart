import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/date_formatter.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import '../../shared/widgets/status_chip.dart';
import 'inventory_provider.dart';

class TransferDetailScreen extends ConsumerWidget {
  final String transferNumber;

  const TransferDetailScreen({super.key, required this.transferNumber});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final transferAsync = ref.watch(transferDetailProvider(transferNumber));

    return Scaffold(
      appBar: AppBar(title: Text(transferNumber)),
      body: transferAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load transfer.',
          onRetry: () => ref.invalidate(transferDetailProvider(transferNumber)),
        ),
        data: (t) {
          final source = (t['source_warehouse'] as Map?)?.cast<String, dynamic>() ?? {};
          final dest = (t['destination_warehouse'] as Map?)?.cast<String, dynamic>() ?? {};
          final items = (t['items'] as List? ?? []).cast<Map<String, dynamic>>();

          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(transferDetailProvider(transferNumber)),
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
                          Text('${t['transfer_number'] ?? transferNumber}', style: const TextStyle(fontWeight: FontWeight.w600)),
                          if (t['status'] != null) StatusChip(status: '${t['status']}'),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text('${source['name'] ?? '-'} → ${dest['name'] ?? '-'}',
                          style: const TextStyle(color: AppTheme.textSecondary)),
                      if (t['carrier'] != null) Text('Carrier: ${t['carrier']}', style: const TextStyle(color: AppTheme.textSecondary)),
                      if (t['tracking_number'] != null)
                        Text('Tracking: ${t['tracking_number']}', style: const TextStyle(color: AppTheme.textSecondary)),
                      if (t['expected_arrival_date'] != null)
                        Text('Expected: ${t['expected_arrival_date']}', style: const TextStyle(color: AppTheme.textSecondary)),
                      if (t['notes'] != null) ...[
                        const SizedBox(height: 8),
                        Text('${t['notes']}'),
                      ],
                    ],
                  ),
                ),
                if (items.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Text('Items', style: Theme.of(context).textTheme.titleMedium),
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
                                    Text('Listing #${item['vendor_listing_id'] ?? '-'}'),
                                    if (item['condition_notes'] != null)
                                      Text('${item['condition_notes']}',
                                          style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                                  ],
                                ),
                              ),
                              Text('${item['quantity_received'] ?? 0}/${item['quantity_requested'] ?? 0}'),
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
}

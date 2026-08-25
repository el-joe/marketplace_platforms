import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/date_formatter.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import '../../shared/widgets/status_chip.dart';
import 'returns_provider.dart';

class ReturnDetailScreen extends ConsumerWidget {
  final String returnNumber;

  const ReturnDetailScreen({super.key, required this.returnNumber});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final returnAsync = ref.watch(returnDetailProvider(returnNumber));

    return Scaffold(
      appBar: AppBar(title: Text(returnNumber)),
      body: returnAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load return.',
          onRetry: () => ref.invalidate(returnDetailProvider(returnNumber)),
        ),
        data: (r) {
          final items = (r['items'] as List? ?? []).cast<Map<String, dynamic>>();
          final messages = (r['messages'] as List? ?? []).cast<Map<String, dynamic>>();

          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(returnDetailProvider(returnNumber)),
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
                          Text('${r['return_number'] ?? returnNumber}', style: const TextStyle(fontWeight: FontWeight.w600)),
                          if (r['status'] != null) StatusChip(status: '${r['status']}'),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text('Order ${r['order_number_masked'] ?? r['order_number'] ?? '-'}',
                          style: const TextStyle(color: AppTheme.textSecondary)),
                      Text('Reason: ${r['reason'] ?? '-'}', style: const TextStyle(color: AppTheme.textSecondary)),
                      Text('Type: ${r['return_type'] ?? '-'}', style: const TextStyle(color: AppTheme.textSecondary)),
                      const SizedBox(height: 4),
                      Text(DateFormatter.dateTime(DateFormatter.parse(r['created_at'] as String?)),
                          style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                    ],
                  ),
                ),
                if (items.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Text('Items', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  ...items.map((item) {
                    final product = (item['product'] as Map?)?.cast<String, dynamic>() ?? {};
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: PCard(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('${product['name_en'] ?? 'Item'} × ${item['quantity'] ?? 1}'),
                            const SizedBox(height: 4),
                            Text('Condition: ${item['condition_received'] ?? '-'} · Restock: ${item['restock_decision'] ?? '-'}',
                                style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                          ],
                        ),
                      ),
                    );
                  }),
                ],
                if (messages.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Text('Conversation', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  ...messages.map((m) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: PCard(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('${m['sender_label'] ?? m['sender_role'] ?? 'Message'}',
                                  style: const TextStyle(fontWeight: FontWeight.w600)),
                              const SizedBox(height: 4),
                              Text('${m['message'] ?? ''}'),
                              const SizedBox(height: 4),
                              Text(DateFormatter.relative(DateFormatter.parse(m['created_at'] as String?)),
                                  style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
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

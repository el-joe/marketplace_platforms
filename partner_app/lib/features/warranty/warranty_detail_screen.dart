import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/date_formatter.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import '../../shared/widgets/status_chip.dart';
import 'warranty_provider.dart';

class WarrantyDetailScreen extends ConsumerWidget {
  final String id;

  const WarrantyDetailScreen({super.key, required this.id});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final claimAsync = ref.watch(warrantyDetailProvider(id));

    return Scaffold(
      appBar: AppBar(title: const Text('Warranty Claim')),
      body: claimAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load claim.',
          onRetry: () => ref.invalidate(warrantyDetailProvider(id)),
        ),
        data: (c) {
          final customer = (c['customer'] as Map?)?.cast<String, dynamic>() ?? {};
          final messages = (c['messages'] as List? ?? []).cast<Map<String, dynamic>>();

          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(warrantyDetailProvider(id)),
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
                          Text('${c['claim_number'] ?? '-'}', style: const TextStyle(fontWeight: FontWeight.w600)),
                          if (c['status'] != null) StatusChip(status: '${c['status']}'),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text('Customer: ${customer['name'] ?? '-'}', style: const TextStyle(color: AppTheme.textSecondary)),
                      Text('Issue: ${c['issue_type'] ?? '-'}', style: const TextStyle(color: AppTheme.textSecondary)),
                      if (c['description'] != null) ...[
                        const SizedBox(height: 8),
                        Text('${c['description']}'),
                      ],
                      if (c['resolution_notes'] != null) ...[
                        const SizedBox(height: 8),
                        Text('Resolution: ${c['resolution_notes']}', style: const TextStyle(color: AppTheme.success)),
                      ],
                      const SizedBox(height: 4),
                      Text(DateFormatter.dateTime(DateFormatter.parse(c['created_at'] as String?)),
                          style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                    ],
                  ),
                ),
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
                              Text('${m['sender'] ?? 'Message'}', style: const TextStyle(fontWeight: FontWeight.w600)),
                              const SizedBox(height: 4),
                              Text('${m['body'] ?? ''}'),
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

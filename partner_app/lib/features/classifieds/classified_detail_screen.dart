import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import '../../shared/widgets/status_chip.dart';
import 'classifieds_provider.dart';

class ClassifiedDetailScreen extends ConsumerWidget {
  final String id;

  const ClassifiedDetailScreen({super.key, required this.id});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final classifiedAsync = ref.watch(classifiedDetailProvider(id));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Classified'),
        actions: [
          IconButton(
            icon: const Icon(Icons.forum_outlined),
            tooltip: 'Inquiries',
            onPressed: () => context.push('/classifieds/$id/inquiries'),
          ),
        ],
      ),
      body: classifiedAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load classified.',
          onRetry: () => ref.invalidate(classifiedDetailProvider(id)),
        ),
        data: (c) {
          final category = (c['category'] as Map?)?.cast<String, dynamic>() ?? {};
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(classifiedDetailProvider(id)),
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
                          Expanded(child: Text('${c['title'] ?? '-'}', style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16))),
                          if (c['status'] != null) StatusChip(status: '${c['status']}'),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(MoneyFormatter.format(c['price'] as num?, c['currency'] as String?),
                          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.success)),
                      const SizedBox(height: 8),
                      Text('Category: ${category['name'] ?? '-'}', style: const TextStyle(color: AppTheme.textSecondary)),
                      Text('Purpose: ${c['listing_purpose'] ?? '-'} · Seller: ${c['seller_type'] ?? '-'}',
                          style: const TextStyle(color: AppTheme.textSecondary)),
                      if (c['description'] != null) ...[
                        const SizedBox(height: 12),
                        Text('${c['description']}'),
                      ],
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          const Icon(Icons.visibility_outlined, size: 16, color: AppTheme.textSecondary),
                          const SizedBox(width: 4),
                          Text('${c['views_count'] ?? 0} views'),
                          const SizedBox(width: 16),
                          const Icon(Icons.forum_outlined, size: 16, color: AppTheme.textSecondary),
                          const SizedBox(width: 4),
                          Text('${c['inquiries_count'] ?? 0} inquiries'),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

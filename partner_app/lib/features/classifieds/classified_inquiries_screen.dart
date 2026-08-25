import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/date_formatter.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import 'classifieds_provider.dart';

class ClassifiedInquiriesScreen extends ConsumerWidget {
  final String id;

  const ClassifiedInquiriesScreen({super.key, required this.id});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final inquiriesAsync = ref.watch(classifiedInquiriesProvider(id));

    return Scaffold(
      appBar: AppBar(title: const Text('Inquiries')),
      body: inquiriesAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load inquiries.',
          onRetry: () => ref.invalidate(classifiedInquiriesProvider(id)),
        ),
        data: (paginated) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(classifiedInquiriesProvider(id)),
          child: paginated.items.isEmpty
              ? ListView(children: const [
                  SizedBox(height: 120),
                  EmptyState(message: 'No inquiries yet.', icon: Icons.forum_outlined),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: paginated.items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final i = paginated.items[index];
                    return PCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(child: Text('${i['customer'] ?? 'Customer'}', style: const TextStyle(fontWeight: FontWeight.w600))),
                              Text('${i['status'] ?? ''}', style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text('${i['message'] ?? ''}'),
                          const SizedBox(height: 4),
                          Text(DateFormatter.relative(DateFormatter.parse(i['created_at'] as String?)),
                              style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                        ],
                      ),
                    );
                  },
                ),
        ),
      ),
    );
  }
}

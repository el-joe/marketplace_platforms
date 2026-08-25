import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/date_formatter.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import 'performance_provider.dart';

class ReviewsScreen extends ConsumerWidget {
  const ReviewsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final reviewsAsync = ref.watch(reviewsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Reviews')),
      body: reviewsAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load reviews.',
          onRetry: () => ref.invalidate(reviewsProvider),
        ),
        data: (paginated) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(reviewsProvider),
          child: paginated.items.isEmpty
              ? ListView(children: const [
                  SizedBox(height: 120),
                  EmptyState(message: 'No reviews yet.', icon: Icons.reviews_outlined),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: paginated.items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final r = paginated.items[index];
                    final rating = (r['rating'] as num?)?.toInt() ?? 0;
                    return PCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              ...List.generate(
                                5,
                                (i) => Icon(i < rating ? Icons.star : Icons.star_border, size: 16, color: AppTheme.primary),
                              ),
                              const Spacer(),
                              Text(DateFormatter.relative(DateFormatter.parse(r['created_at'] as String?)),
                                  style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                            ],
                          ),
                          if (r['title'] != null) ...[
                            const SizedBox(height: 6),
                            Text('${r['title']}', style: const TextStyle(fontWeight: FontWeight.w600)),
                          ],
                          if (r['body'] != null) ...[
                            const SizedBox(height: 4),
                            Text('${r['body']}'),
                          ],
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

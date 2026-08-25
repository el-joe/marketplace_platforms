import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/notification_bell.dart';
import '../../shared/widgets/p_card.dart';
import 'performance_provider.dart';

class PerformanceScreen extends ConsumerWidget {
  const PerformanceScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final performanceAsync = ref.watch(performanceProvider);
    final days = ref.watch(performanceDaysProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Performance'),
        actions: const [NotificationBell(), SizedBox(width: 8)],
      ),
      body: performanceAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load performance.',
          onRetry: () => ref.invalidate(performanceProvider),
        ),
        data: (p) {
          final comparisons = (p['period_comparison'] as List? ?? []).cast<Map<String, dynamic>>();
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(performanceProvider),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Row(
                  children: [30, 60, 90]
                      .map((d) => Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: ChoiceChip(
                              label: Text('$d days'),
                              selected: days == d,
                              onSelected: (_) => ref.read(performanceDaysProvider.notifier).state = d,
                            ),
                          ))
                      .toList(),
                ),
                const SizedBox(height: 16),
                GridView.count(
                  crossAxisCount: 2,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  childAspectRatio: 1.6,
                  children: [
                    _metric('Store rating', '${p['store_rating_avg'] ?? '-'}', Icons.star, AppTheme.primary),
                    _metric('Total reviews', '${p['total_reviews'] ?? 0}', Icons.reviews_outlined, AppTheme.textPrimary),
                    _metric('Cancellation rate', '${p['cancellation_rate_pct'] ?? '-'}%', Icons.cancel_outlined, AppTheme.error),
                    _metric('SLA compliance', '${p['sla_compliance_pct'] ?? '-'}%', Icons.timer_outlined, AppTheme.success),
                    _metric('Return rate', '${p['return_rate_pct'] ?? '-'}%', Icons.assignment_return_outlined, AppTheme.warning),
                  ],
                ),
                const SizedBox(height: 20),
                Text('Period comparison', style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 8),
                ...comparisons.map((c) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: PCard(
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text('${c['days']} days', style: const TextStyle(fontWeight: FontWeight.w600)),
                            Text('${c['total_orders'] ?? 0} orders', style: const TextStyle(color: AppTheme.textSecondary)),
                            Text('Cancel ${c['cancellation_rate_pct'] ?? '-'}%'),
                            Text('★ ${c['avg_rating'] ?? '-'}'),
                          ],
                        ),
                      ),
                    )),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: () => context.push('/performance/reviews'),
                  icon: const Icon(Icons.rate_review_outlined),
                  label: const Text('View reviews'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _metric(String label, String value, IconData icon, Color color) {
    return PCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, color: color),
          const SizedBox(height: 8),
          Text(value, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
          Text(label, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
        ],
      ),
    );
  }
}

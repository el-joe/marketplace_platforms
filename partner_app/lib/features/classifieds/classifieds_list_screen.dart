import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/notification_bell.dart';
import '../../shared/widgets/p_card.dart';
import '../../shared/widgets/status_chip.dart';
import 'classifieds_provider.dart';

class ClassifiedsListScreen extends ConsumerWidget {
  const ClassifiedsListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final classifiedsAsync = ref.watch(classifiedsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('My Classifieds'),
        actions: const [NotificationBell(), SizedBox(width: 8)],
      ),
      body: classifiedsAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load classifieds.',
          onRetry: () => ref.read(classifiedsProvider.notifier).refresh(),
        ),
        data: (paginated) => RefreshIndicator(
          onRefresh: () => ref.read(classifiedsProvider.notifier).refresh(),
          child: paginated.items.isEmpty
              ? ListView(children: const [
                  SizedBox(height: 120),
                  EmptyState(message: 'No classified listings.', icon: Icons.storefront_outlined),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: paginated.items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final c = paginated.items[index];
                    return PCard(
                      onTap: () => context.push('/classifieds/${c['id']}'),
                      child: Row(
                        children: [
                          ClipRRect(
                            borderRadius: BorderRadius.circular(8),
                            child: c['thumbnail'] != null
                                ? CachedNetworkImage(
                                    imageUrl: '${c['thumbnail']}',
                                    width: 56,
                                    height: 56,
                                    fit: BoxFit.cover,
                                    errorWidget: (_, __, ___) => const Icon(Icons.image_not_supported_outlined),
                                  )
                                : Container(
                                    width: 56,
                                    height: 56,
                                    color: AppTheme.background,
                                    child: const Icon(Icons.image_outlined, color: AppTheme.textSecondary),
                                  ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('${c['title'] ?? '-'}',
                                    maxLines: 1, overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(fontWeight: FontWeight.w600)),
                                const SizedBox(height: 4),
                                Text(MoneyFormatter.format(c['price'] as num?, c['currency'] as String?),
                                    style: const TextStyle(color: AppTheme.success)),
                                const SizedBox(height: 2),
                                Text('${c['views_count'] ?? 0} views · ${c['inquiries_count'] ?? 0} inquiries',
                                    style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                              ],
                            ),
                          ),
                          if (c['status'] != null) StatusChip(status: '${c['status']}'),
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

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
import 'listings_provider.dart';

class ListingsListScreen extends ConsumerWidget {
  const ListingsListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final listingsAsync = ref.watch(listingsProvider);
    final filter = ref.watch(listingsFilterProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Listings'),
        actions: const [NotificationBell(), SizedBox(width: 8)],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              style: const TextStyle(color: AppTheme.textPrimary),
              decoration: const InputDecoration(hintText: 'Search listings', prefixIcon: Icon(Icons.search), isDense: true),
              onSubmitted: (v) =>
                  ref.read(listingsFilterProvider.notifier).state = ListingsFilter(status: filter.status, search: v),
            ),
          ),
          Expanded(
            child: listingsAsync.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(
                message: e is ApiException ? e.message : 'Failed to load listings.',
                onRetry: () => ref.read(listingsProvider.notifier).refresh(),
              ),
              data: (paginated) => RefreshIndicator(
                onRefresh: () => ref.read(listingsProvider.notifier).refresh(),
                child: paginated.items.isEmpty
                    ? ListView(children: const [
                        SizedBox(height: 120),
                        EmptyState(message: 'No listings found.', icon: Icons.inventory_2_outlined),
                      ])
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                        itemCount: paginated.items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (context, index) {
                          final l = paginated.items[index];
                          return PCard(
                            onTap: () => context.push('/listings/${l['id']}'),
                            child: Row(
                              children: [
                                ClipRRect(
                                  borderRadius: BorderRadius.circular(8),
                                  child: l['thumbnail'] != null
                                      ? CachedNetworkImage(
                                          imageUrl: '${l['thumbnail']}',
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
                                      Text('${l['product_name_en'] ?? l['vendor_sku'] ?? 'Listing'}',
                                          maxLines: 1, overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(fontWeight: FontWeight.w600)),
                                      const SizedBox(height: 4),
                                      Text(MoneyFormatter.format(l['price'] as num?, l['currency'] as String?),
                                          style: const TextStyle(color: AppTheme.success)),
                                      const SizedBox(height: 2),
                                      Text('${l['total_sold'] ?? 0} sold', style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                                    ],
                                  ),
                                ),
                                if (l['status'] != null) StatusChip(status: '${l['status']}'),
                              ],
                            ),
                          );
                        },
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

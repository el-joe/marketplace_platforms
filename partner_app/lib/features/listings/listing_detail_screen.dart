import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import '../../shared/widgets/status_chip.dart';
import 'listings_provider.dart';

class ListingDetailScreen extends ConsumerWidget {
  final String id;

  const ListingDetailScreen({super.key, required this.id});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final listingAsync = ref.watch(listingDetailProvider(id));

    return Scaffold(
      appBar: AppBar(title: const Text('Listing')),
      body: listingAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load listing.',
          onRetry: () => ref.invalidate(listingDetailProvider(id)),
        ),
        data: (l) {
          final currency = l['currency'] as String?;
          return RefreshIndicator(
            onRefresh: () async => ref.invalidate(listingDetailProvider(id)),
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (l['thumbnail'] != null)
                  ClipRRect(
                    borderRadius: BorderRadius.circular(16),
                    child: CachedNetworkImage(
                      imageUrl: '${l['thumbnail']}',
                      height: 200,
                      width: double.infinity,
                      fit: BoxFit.cover,
                      errorWidget: (_, __, ___) => const SizedBox.shrink(),
                    ),
                  ),
                const SizedBox(height: 16),
                PCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Expanded(
                              child: Text('${l['product_name_en'] ?? l['vendor_sku'] ?? 'Listing'}',
                                  style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16))),
                          if (l['status'] != null) StatusChip(status: '${l['status']}'),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text('SKU: ${l['sku'] ?? l['vendor_sku'] ?? '-'}', style: const TextStyle(color: AppTheme.textSecondary)),
                      Text('Condition: ${l['condition'] ?? '-'}', style: const TextStyle(color: AppTheme.textSecondary)),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Text(MoneyFormatter.format(l['price'] as num?, currency),
                              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.success)),
                          if (l['compare_at_price'] != null) ...[
                            const SizedBox(width: 8),
                            Text(MoneyFormatter.format(l['compare_at_price'] as num?, currency),
                                style: const TextStyle(decoration: TextDecoration.lineThrough, color: AppTheme.textSecondary)),
                          ],
                        ],
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          const Icon(Icons.star, color: AppTheme.primary, size: 16),
                          const SizedBox(width: 4),
                          Text('${l['rating_avg'] ?? '-'}'),
                          const SizedBox(width: 16),
                          const Icon(Icons.sell_outlined, color: AppTheme.textSecondary, size: 16),
                          const SizedBox(width: 4),
                          Text('${l['total_sold'] ?? 0} sold'),
                        ],
                      ),
                      if (l['rejection_reason'] != null) ...[
                        const SizedBox(height: 12),
                        Text('Rejection reason: ${l['rejection_reason']}', style: const TextStyle(color: AppTheme.error)),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                PCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Fulfillment', style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 8),
                      Text('Model: ${l['fulfillment_model'] ?? '-'}'),
                      Text('Vendor covers delivery: ${l['vendor_covers_delivery'] == true ? 'Yes' : 'No'}'),
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

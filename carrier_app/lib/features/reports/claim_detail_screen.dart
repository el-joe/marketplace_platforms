import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/date_formatter.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/models/claim.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/status_chip.dart';
import 'reports_provider.dart';

class ClaimDetailScreen extends ConsumerWidget {
  final String claimId;

  const ClaimDetailScreen({super.key, required this.claimId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final claimAsync = ref.watch(claimDetailProvider(claimId));

    return Scaffold(
      appBar: AppBar(title: const Text('Claim Details')),
      body: claimAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load claim.',
          onRetry: () => ref.invalidate(claimDetailProvider(claimId)),
        ),
        data: (payload) {
          final claim = CarrierClaim.fromJson(payload['claim'] as Map<String, dynamic>);
          final currency = payload['currency'] as String? ?? '';

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              DCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(claim.claimNumber ?? '-',
                              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                        ),
                        StatusChip(status: claim.status),
                      ],
                    ),
                    const SizedBox(height: 8),
                    if (claim.claimType != null) Text('Type: ${claim.claimType}'),
                    if (claim.deliveryAgent != null) Text('Agent: ${claim.deliveryAgent!.name}'),
                    if (claim.shipment?.trackingNumber != null) Text('Tracking: ${claim.shipment!.trackingNumber}'),
                    Text('Created: ${DateFormatter.dateTime(claim.createdAt)}'),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              DCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Amounts', style: TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 8),
                    Text('Claimed: ${MoneyFormatter.format(claim.claimedAmount ?? 0, currency)}'),
                    Text('Compensated: ${MoneyFormatter.format(claim.compensatedAmount ?? 0, currency)}'),
                  ],
                ),
              ),
              if (claim.description != null) ...[
                const SizedBox(height: 16),
                DCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Description', style: TextStyle(fontWeight: FontWeight.w600)),
                      const SizedBox(height: 8),
                      Text(claim.description!, style: const TextStyle(color: AppTheme.textSecondary)),
                    ],
                  ),
                ),
              ],
              if (claim.resolutionNotes != null) ...[
                const SizedBox(height: 16),
                DCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Resolution', style: TextStyle(fontWeight: FontWeight.w600)),
                      const SizedBox(height: 8),
                      Text(claim.resolutionNotes!, style: const TextStyle(color: AppTheme.textSecondary)),
                      if (claim.resolvedAt != null) ...[
                        const SizedBox(height: 4),
                        Text('Resolved: ${DateFormatter.dateTime(claim.resolvedAt)}',
                            style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                      ],
                    ],
                  ),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

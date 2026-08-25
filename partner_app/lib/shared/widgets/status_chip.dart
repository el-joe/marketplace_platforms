import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

/// Color-coded status pill. Covers order, return/warranty-claim, listing,
/// inventory-transfer, payout and classified statuses in one map — statuses
/// are unique enough across these domains that a single switch is safe and
/// keeps the color rules in one auditable place.
class StatusChip extends StatelessWidget {
  final String status;

  const StatusChip({super.key, required this.status});

  ({Color color, String label}) _config() {
    switch (status) {
      // Orders
      case 'placed':
      case 'confirmed':
        return (color: AppTheme.warning, label: _label(status));
      case 'processing':
      case 'shipped':
        return (color: AppTheme.primary, label: _label(status));
      case 'delivered':
      case 'completed':
      case 'approved':
      case 'received':
      case 'resolved':
      case 'active':
      case 'in_stock':
      case 'paid':
        return (color: AppTheme.success, label: _label(status));
      case 'cancelled':
      case 'rejected':
      case 'failed':
      case 'declined':
      case 'expired':
        return (color: AppTheme.error, label: _label(status));
      case 'returned':
      case 'refunded':
      case 'return_requested':
        return (color: AppTheme.error, label: _label(status));

      // Returns / warranty claims
      case 'pending':
      case 'requested':
      case 'awaiting_pickup':
      case 'in_transit':
      case 'submitted':
      case 'under_review':
      case 'processing_payout':
        return (color: AppTheme.warning, label: _label(status));

      // Listings
      case 'draft':
      case 'inactive':
      case 'paused':
        return (color: AppTheme.textSecondary, label: _label(status));
      case 'out_of_stock':
        return (color: AppTheme.error, label: _label(status));

      // Payouts
      case 'scheduled':
        return (color: AppTheme.primary, label: _label(status));

      default:
        return (color: AppTheme.textSecondary, label: _label(status));
    }
  }

  String _label(String value) =>
      value.split('_').map((w) => w.isEmpty ? w : '${w[0].toUpperCase()}${w.substring(1)}').join(' ');

  @override
  Widget build(BuildContext context) {
    final config = _config();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: config.color.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        config.label,
        style: TextStyle(color: config.color, fontSize: 12, fontWeight: FontWeight.w600),
      ),
    );
  }
}

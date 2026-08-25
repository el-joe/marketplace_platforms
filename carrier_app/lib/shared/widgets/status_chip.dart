import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

/// Renders a colored pill for assignment / agent / claim status values.
class StatusChip extends StatelessWidget {
  final String status;

  const StatusChip({super.key, required this.status});

  ({Color color, String label}) _config() {
    switch (status) {
      // Assignment statuses
      case 'assigned':
        return (color: AppTheme.warning, label: 'Assigned');
      case 'accepted':
        return (color: AppTheme.primary, label: 'Accepted');
      case 'picked_up':
        return (color: const Color(0xFF7C3AED), label: 'Picked Up');
      case 'delivered':
        return (color: AppTheme.success, label: 'Delivered');
      case 'failed':
        return (color: AppTheme.danger, label: 'Failed');

      // Agent statuses
      case 'active':
        return (color: AppTheme.success, label: 'Active');
      case 'on_shift':
        return (color: AppTheme.success, label: 'On Shift');
      case 'inactive':
        return (color: AppTheme.textSecondary, label: 'Inactive');
      case 'suspended':
        return (color: AppTheme.danger, label: 'Suspended');

      // Claim statuses
      case 'submitted':
        return (color: AppTheme.warning, label: 'Submitted');
      case 'under_review':
        return (color: AppTheme.primary, label: 'Under Review');
      case 'approved':
        return (color: const Color(0xFF7C3AED), label: 'Approved');
      case 'compensated':
        return (color: AppTheme.success, label: 'Compensated');
      case 'rejected':
        return (color: AppTheme.danger, label: 'Rejected');

      // Generic / payout / settlement statuses
      case 'pending':
        return (color: AppTheme.warning, label: 'Pending');
      case 'paid':
      case 'settled':
        return (color: AppTheme.success, label: status == 'paid' ? 'Paid' : 'Settled');
      case 'disputed':
        return (color: AppTheme.danger, label: 'Disputed');
      default:
        return (color: AppTheme.textSecondary, label: status.replaceAll('_', ' '));
    }
  }

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
        style: TextStyle(
          color: config.color,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

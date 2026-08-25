import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

class StatusChip extends StatelessWidget {
  final String status;

  const StatusChip({super.key, required this.status});

  ({Color color, String label}) _config() {
    switch (status) {
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
      case 'on_shift':
        return (color: AppTheme.success, label: 'On Shift');
      case 'active':
        return (color: AppTheme.success, label: 'Active');
      case 'pending':
        return (color: AppTheme.warning, label: 'Pending');
      case 'settled':
        return (color: AppTheme.success, label: 'Settled');
      case 'open':
        return (color: AppTheme.warning, label: 'Open');
      case 'resolved':
        return (color: AppTheme.success, label: 'Resolved');
      case 'closed':
        return (color: Colors.grey, label: 'Closed');
      default:
        return (color: Colors.grey, label: status.replaceAll('_', ' '));
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = _config();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: config.color.withValues(alpha: 0.12),
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

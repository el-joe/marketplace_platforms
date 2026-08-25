import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

enum StatusChipType { booking, package, campaign, inquiry, bankAccount }

class StatusChip extends StatelessWidget {
  const StatusChip({super.key, required this.status, this.type = StatusChipType.booking});

  final String status;
  final StatusChipType type;

  static const Map<String, Color> _booking = {
    'pending_documents': AppColors.warning,
    'confirmed': AppColors.primary,
    'completed': AppColors.success,
    'cancelled': AppColors.error,
    'no_show': AppColors.textSecondary,
  };

  static const Map<String, Color> _package = {
    'draft': AppColors.textSecondary,
    'pending_review': AppColors.warning,
    'active': AppColors.success,
    'inactive': AppColors.error,
    'sold_out': Colors.orange,
    'withdrawn': AppColors.textSecondary,
  };

  static const Map<String, Color> _campaign = {
    'draft': AppColors.textSecondary,
    'pending_admin': AppColors.warning,
    'active': AppColors.success,
    'paused': Colors.orange,
    'completed': AppColors.accent,
    'rejected': AppColors.error,
  };

  static const Map<String, Color> _inquiry = {
    'new': AppColors.primary,
    'contacted': AppColors.warning,
    'converted': AppColors.success,
    'closed': AppColors.textSecondary,
  };

  static const Map<String, Color> _bankAccount = {
    'pending': AppColors.warning,
    'verified': AppColors.success,
    'rejected': AppColors.error,
  };

  Color get _color {
    final map = switch (type) {
      StatusChipType.booking => _booking,
      StatusChipType.package => _package,
      StatusChipType.campaign => _campaign,
      StatusChipType.inquiry => _inquiry,
      StatusChipType.bankAccount => _bankAccount,
    };
    return map[status] ?? AppColors.textSecondary;
  }

  @override
  Widget build(BuildContext context) {
    final color = _color;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.4)),
      ),
      child: Text(
        status.replaceAll('_', ' '),
        style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600),
      ),
    );
  }
}

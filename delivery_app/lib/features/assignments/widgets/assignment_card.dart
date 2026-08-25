import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/date_formatter.dart';
import '../../../core/utils/money_formatter.dart';
import '../../../shared/models/assignment.dart';
import '../../../shared/widgets/d_card.dart';
import '../../../shared/widgets/status_chip.dart';

class AssignmentCard extends StatelessWidget {
  final Assignment assignment;
  final VoidCallback onTap;

  const AssignmentCard({super.key, required this.assignment, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return DCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(
                assignment.subOrderNumber ?? '#${assignment.id}',
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
              const Spacer(),
              StatusChip(status: assignment.status),
            ],
          ),
          const SizedBox(height: 8),
          if (assignment.recipientName != null)
            Row(
              children: [
                const Icon(Icons.person_outline, size: 16, color: Colors.grey),
                const SizedBox(width: 4),
                Text(assignment.recipientName!),
              ],
            ),
          if (assignment.deliveryAddressLine != null) ...[
            const SizedBox(height: 4),
            Row(
              children: [
                const Icon(Icons.location_on_outlined, size: 16, color: Colors.grey),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    assignment.deliveryAddressLine!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Colors.grey),
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 8),
          Row(
            children: [
              if (assignment.isCod)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: AppTheme.warning.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    'COD ${assignment.effectiveCodAmount != null && assignment.currency != null ? MoneyFormatter.format(assignment.effectiveCodAmount!, assignment.currency!) : ''}',
                    style: const TextStyle(fontSize: 11, color: AppTheme.warning, fontWeight: FontWeight.w600),
                  ),
                ),
              const Spacer(),
              Text(
                DateFormatter.relative(assignment.assignedAt),
                style: const TextStyle(fontSize: 12, color: Colors.grey),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

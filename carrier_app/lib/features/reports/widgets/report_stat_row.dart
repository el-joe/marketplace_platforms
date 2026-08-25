import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/d_card.dart';

class ReportStat {
  final String label;
  final String value;
  final Color? color;

  const ReportStat({required this.label, required this.value, this.color});
}

/// A horizontally-scrollable row of KPI stat cards used across report tabs.
class ReportStatRow extends StatelessWidget {
  final List<ReportStat> stats;

  const ReportStatRow({super.key, required this.stats});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 84,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: stats.length,
        separatorBuilder: (_, __) => const SizedBox(width: 12),
        itemBuilder: (context, index) {
          final stat = stats[index];
          return SizedBox(
            width: 140,
            child: DCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(stat.value,
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: stat.color ?? AppTheme.textPrimary,
                      )),
                  const SizedBox(height: 4),
                  Text(stat.label, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

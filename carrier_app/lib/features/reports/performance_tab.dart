import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/empty_view.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import 'reports_provider.dart';
import 'widgets/horizontal_data_table.dart';

class PerformanceTab extends ConsumerWidget {
  const PerformanceTab({super.key});

  static const _periods = ['week', 'month', 'quarter', 'year'];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final period = ref.watch(performancePeriodProvider);
    final performanceAsync = ref.watch(performanceReportProvider);
    final trendAsync = ref.watch(performanceTrendProvider);

    return RefreshIndicator(
      onRefresh: () async {
        ref.invalidate(performanceReportProvider);
        ref.invalidate(performanceTrendProvider);
      },
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SizedBox(
            height: 36,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: _periods.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (context, index) {
                final p = _periods[index];
                return ChoiceChip(
                  label: Text(p[0].toUpperCase() + p.substring(1)),
                  selected: period == p,
                  onSelected: (_) => ref.read(performancePeriodProvider.notifier).state = p,
                );
              },
            ),
          ),
          const SizedBox(height: 20),
          performanceAsync.when(
            loading: () => const LoadingView(),
            error: (e, _) =>
                ErrorView(message: e is ApiException ? e.message : 'Failed to load performance report.'),
            data: (data) {
              final scorecard = (data['scorecard'] as Map?)?.cast<String, dynamic>() ?? {};
              final agentRatings = (data['agent_ratings'] as List? ?? []).cast<Map<String, dynamic>>();

              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _Scorecard(scorecard: scorecard),
                  const SizedBox(height: 20),
                  Text('Rating Trend', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 12),
                  trendAsync.when(
                    loading: () => const SizedBox(height: 180, child: LoadingView()),
                    error: (e, _) => ErrorView(message: e is ApiException ? e.message : 'Failed to load trend.'),
                    data: (trend) => trend.isEmpty
                        ? const EmptyView(message: 'No rating trend data yet.')
                        : _TrendChart(trend: trend),
                  ),
                  const SizedBox(height: 20),
                  Text('Agent Ratings', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 12),
                  if (agentRatings.isEmpty)
                    const EmptyView(message: 'No agent ratings yet.')
                  else
                    HorizontalDataTable(
                      columns: const [
                        DataColumn(label: Text('Agent')),
                        DataColumn(label: Text('Avg Rating')),
                        DataColumn(label: Text('Total Ratings')),
                        DataColumn(label: Text('On-time')),
                      ],
                      rows: agentRatings.map((row) {
                        final onTimeCount = (row['on_time_count'] as num?)?.toInt() ?? 0;
                        final onTimeEligible = (row['on_time_eligible'] as num?)?.toInt() ?? 0;
                        final onTimePct = onTimeEligible > 0 ? (onTimeCount / onTimeEligible * 100).toStringAsFixed(0) : '-';
                        return DataRow(cells: [
                          DataCell(Text(row['agent_name']?.toString() ?? '-')),
                          DataCell(Text('${row['avg_rating'] ?? '-'}')),
                          DataCell(Text('${row['total_ratings'] ?? 0}')),
                          DataCell(Text(onTimeEligible > 0 ? '$onTimePct%' : '-')),
                        ]);
                      }).toList(),
                    ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }
}

class _Scorecard extends StatelessWidget {
  final Map<String, dynamic> scorecard;

  const _Scorecard({required this.scorecard});

  @override
  Widget build(BuildContext context) {
    return DCard(
      child: Wrap(
        spacing: 20,
        runSpacing: 12,
        children: [
          _ScoreItem(label: 'Avg Rating', value: '${scorecard['avg_rating'] ?? '-'}'),
          _ScoreItem(label: 'Total Ratings', value: '${scorecard['total_ratings'] ?? 0}'),
          _ScoreItem(label: 'On-time %', value: scorecard['on_time_pct'] != null ? '${scorecard['on_time_pct']}%' : '-'),
          _ScoreItem(label: 'Total Claims', value: '${scorecard['total_claims'] ?? 0}'),
          _ScoreItem(
              label: 'Claims Approved %',
              value: scorecard['claims_approved_pct'] != null ? '${scorecard['claims_approved_pct']}%' : '-'),
        ],
      ),
    );
  }
}

class _ScoreItem extends StatelessWidget {
  final String label;
  final String value;

  const _ScoreItem({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(value, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
        const SizedBox(height: 2),
        Text(label, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
      ],
    );
  }
}

class _TrendChart extends StatelessWidget {
  final List<Map<String, dynamic>> trend;

  const _TrendChart({required this.trend});

  @override
  Widget build(BuildContext context) {
    final spots = <FlSpot>[
      for (var i = 0; i < trend.length; i++) FlSpot(i.toDouble(), (trend[i]['avg_rating'] as num?)?.toDouble() ?? 0),
    ];

    return DCard(
      padding: const EdgeInsets.fromLTRB(8, 20, 20, 12),
      child: SizedBox(
        height: 200,
        child: LineChart(
          LineChartData(
            minY: 0,
            maxY: 5,
            gridData: const FlGridData(show: true, drawVerticalLine: false),
            borderData: FlBorderData(show: false),
            titlesData: FlTitlesData(
              topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
              rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
              leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: true, reservedSize: 28, interval: 1)),
              bottomTitles: AxisTitles(
                sideTitles: SideTitles(
                  showTitles: true,
                  reservedSize: 28,
                  getTitlesWidget: (value, meta) {
                    final index = value.toInt();
                    if (index < 0 || index >= trend.length) return const SizedBox.shrink();
                    final month = trend[index]['month']?.toString() ?? '';
                    final label = month.length >= 7 ? month.substring(5) : month;
                    return Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(label, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 10)),
                    );
                  },
                ),
              ),
            ),
            lineBarsData: [
              LineChartBarData(
                spots: spots,
                isCurved: true,
                color: AppTheme.primary,
                barWidth: 3,
                dotData: const FlDotData(show: true),
                belowBarData: BarAreaData(show: true, color: AppTheme.primary.withValues(alpha: 0.12)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

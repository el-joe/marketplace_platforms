import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/notification_bell.dart';
import '../../shared/widgets/p_card.dart';
import '../../shared/widgets/status_chip.dart';
import 'dashboard_provider.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashboardAsync = ref.watch(dashboardProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        actions: const [NotificationBell(), SizedBox(width: 8)],
      ),
      body: dashboardAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load dashboard.',
          onRetry: () => ref.read(dashboardProvider.notifier).refresh(),
        ),
        data: (dashboard) => RefreshIndicator(
          onRefresh: () => ref.read(dashboardProvider.notifier).refresh(),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _StatsGrid(dashboard: dashboard),
              const SizedBox(height: 20),
              const _QuickLinks(),
              const SizedBox(height: 20),
              Text('Revenue — last 7 days', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 12),
              _RevenueChart(dashboard: dashboard),
              const SizedBox(height: 20),
              _PerformanceCard(dashboard: dashboard),
              const SizedBox(height: 20),
              Row(
                children: [
                  Text('Recent orders', style: Theme.of(context).textTheme.titleMedium),
                  const Spacer(),
                  TextButton(onPressed: () => context.go('/orders'), child: const Text('See all')),
                ],
              ),
              const SizedBox(height: 4),
              ..._recentOrders(dashboard).map((o) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: PCard(
                      onTap: () {
                        final number = o['sub_order_number'];
                        if (number != null) context.push('/orders/$number');
                      },
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('${o['sub_order_number'] ?? '-'}', style: const TextStyle(fontWeight: FontWeight.w600)),
                                const SizedBox(height: 4),
                                Text(
                                  MoneyFormatter.format((o['vendor_payout'] as num?), dashboard['currency'] as String?),
                                  style: const TextStyle(color: AppTheme.textSecondary),
                                ),
                              ],
                            ),
                          ),
                          if (o['status'] != null) StatusChip(status: '${o['status']}'),
                        ],
                      ),
                    ),
                  )),
              if (_recentOrders(dashboard).isEmpty)
                const PCard(child: Text('No recent orders.', style: TextStyle(color: AppTheme.textSecondary))),
            ],
          ),
        ),
      ),
    );
  }

  List<Map<String, dynamic>> _recentOrders(Map<String, dynamic> dashboard) {
    final raw = dashboard['recent_orders'];
    if (raw is! List) return [];
    return raw.cast<Map<String, dynamic>>();
  }
}

class _StatsGrid extends StatelessWidget {
  final Map<String, dynamic> dashboard;

  const _StatsGrid({required this.dashboard});

  @override
  Widget build(BuildContext context) {
    final currency = dashboard['currency'] as String?;
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      childAspectRatio: 1.6,
      children: [
        _StatCard(
          label: "Today's revenue",
          value: MoneyFormatter.format(dashboard['today_revenue'] as num?, currency),
          icon: Icons.payments_outlined,
          color: AppTheme.success,
        ),
        _StatCard(
          label: 'Pending orders',
          value: '${dashboard['pending_orders_count'] ?? 0}',
          icon: Icons.pending_actions_outlined,
          color: AppTheme.warning,
        ),
        _StatCard(
          label: 'Active listings',
          value: '${dashboard['active_listings_count'] ?? 0}',
          icon: Icons.inventory_2_outlined,
          color: AppTheme.primary,
        ),
        _StatCard(
          label: 'Low stock',
          value: '${dashboard['low_stock_count'] ?? 0}',
          icon: Icons.warning_amber_outlined,
          color: AppTheme.error,
        ),
        _StatCard(
          label: 'Open returns',
          value: '${dashboard['open_returns_count'] ?? 0}',
          icon: Icons.assignment_return_outlined,
          color: AppTheme.warning,
          onTap: () => context.go('/returns'),
        ),
        _StatCard(
          label: 'Pending payout',
          value: MoneyFormatter.format(dashboard['pending_payout'] as num?, currency),
          icon: Icons.account_balance_wallet_outlined,
          color: AppTheme.success,
          onTap: () => context.go('/finance'),
        ),
      ],
    );
  }
}

/// Shortcuts to the screens that don't have their own bottom-nav tab.
class _QuickLinks extends StatelessWidget {
  const _QuickLinks();

  static const _links = [
    (path: '/listings', icon: Icons.storefront_outlined, label: 'Listings'),
    (path: '/returns', icon: Icons.assignment_return_outlined, label: 'Returns'),
    (path: '/warranty', icon: Icons.verified_outlined, label: 'Warranty'),
    (path: '/classifieds', icon: Icons.campaign_outlined, label: 'Classifieds'),
    (path: '/performance', icon: Icons.insights_outlined, label: 'Performance'),
    (path: '/warehouses', icon: Icons.warehouse_outlined, label: 'Warehouses'),
  ];

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 92,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: _links.length,
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemBuilder: (context, index) {
          final link = _links[index];
          return SizedBox(
            width: 84,
            child: PCard(
              onTap: () => context.push(link.path),
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(link.icon, color: AppTheme.primary),
                  const SizedBox(height: 8),
                  Text(link.label, textAlign: TextAlign.center, style: const TextStyle(fontSize: 11)),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final VoidCallback? onTap;

  const _StatCard({required this.label, required this.value, required this.icon, required this.color, this.onTap});

  @override
  Widget build(BuildContext context) {
    return PCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, color: color),
          const SizedBox(height: 8),
          Text(value, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold), overflow: TextOverflow.ellipsis),
          Text(label, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
        ],
      ),
    );
  }
}

class _RevenueChart extends StatelessWidget {
  final Map<String, dynamic> dashboard;

  const _RevenueChart({required this.dashboard});

  @override
  Widget build(BuildContext context) {
    final raw = dashboard['revenue_last_7_days'];
    final days = raw is List ? raw.cast<Map<String, dynamic>>() : <Map<String, dynamic>>[];

    if (days.isEmpty) {
      return const PCard(child: Text('No revenue data yet.', style: TextStyle(color: AppTheme.textSecondary)));
    }

    final maxAmount = days.map((d) => (d['amount'] as num?)?.toDouble() ?? 0).fold<double>(0, (a, b) => a > b ? a : b);

    return PCard(
      child: SizedBox(
        height: 160,
        child: BarChart(
          BarChartData(
            maxY: maxAmount <= 0 ? 10 : maxAmount * 1.2,
            gridData: const FlGridData(show: false),
            borderData: FlBorderData(show: false),
            titlesData: FlTitlesData(
              leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
              rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
              topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
              bottomTitles: AxisTitles(
                sideTitles: SideTitles(
                  showTitles: true,
                  getTitlesWidget: (value, meta) {
                    final index = value.toInt();
                    if (index < 0 || index >= days.length) return const SizedBox.shrink();
                    final date = days[index]['date'] as String? ?? '';
                    final short = date.length >= 10 ? date.substring(8, 10) : date;
                    return Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Text(short, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 10)),
                    );
                  },
                ),
              ),
            ),
            barGroups: List.generate(days.length, (i) {
              final amount = (days[i]['amount'] as num?)?.toDouble() ?? 0;
              return BarChartGroupData(x: i, barRods: [
                BarChartRodData(toY: amount, color: AppTheme.primary, width: 16, borderRadius: BorderRadius.circular(4)),
              ]);
            }),
          ),
        ),
      ),
    );
  }
}

class _PerformanceCard extends StatelessWidget {
  final Map<String, dynamic> dashboard;

  const _PerformanceCard({required this.dashboard});

  @override
  Widget build(BuildContext context) {
    final perf = (dashboard['performance'] as Map?)?.cast<String, dynamic>() ?? {};
    final slaBreaches = (dashboard['sla_breaches'] as Map?)?.cast<String, dynamic>() ?? {};

    return PCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Performance (30 days)', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 12),
          _perfRow('On-time ship rate', perf['on_time_ship_rate'] != null ? '${perf['on_time_ship_rate']}%' : '-'),
          _perfRow('Cancellation rate', perf['cancellation_rate'] != null ? '${perf['cancellation_rate']}%' : '-'),
          _perfRow('Average rating', perf['avg_rating'] != null ? '${perf['avg_rating']}' : '-'),
          _perfRow('SLA breaches', '${slaBreaches['count'] ?? 0}'),
        ],
      ),
    );
  }

  Widget _perfRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppTheme.textSecondary)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}

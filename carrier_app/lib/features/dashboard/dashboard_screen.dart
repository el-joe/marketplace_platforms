import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/models/dashboard.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/permission_gate.dart';
import '../../shared/widgets/status_chip.dart';
import '../auth/auth_provider.dart';
import 'dashboard_provider.dart';

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashboardAsync = ref.watch(dashboardProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () => ref.read(authProvider.notifier).logout(),
          ),
        ],
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
              _CompanyHeader(company: dashboard.company),
              const SizedBox(height: 20),
              Text('Agents', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _StatCard(
                      label: 'Total',
                      value: '${dashboard.agentsCount.total}',
                      icon: Icons.groups_outlined,
                      color: AppTheme.primary,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _StatCard(
                      label: 'On Shift',
                      value: '${dashboard.agentsCount.onShift}',
                      icon: Icons.badge_outlined,
                      color: AppTheme.success,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _StatCard(
                      label: 'Available',
                      value: '${dashboard.agentsCount.available}',
                      icon: Icons.check_circle_outline,
                      color: AppTheme.warning,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              Text("Today's Assignments", style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _StatCard(
                      label: 'Completed',
                      value: '${dashboard.todayAssignments.completed}',
                      icon: Icons.check_circle_outline,
                      color: AppTheme.success,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _StatCard(
                      label: 'In Transit',
                      value: '${dashboard.todayAssignments.inTransit}',
                      icon: Icons.local_shipping_outlined,
                      color: AppTheme.primary,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _StatCard(
                      label: 'Failed',
                      value: '${dashboard.todayAssignments.failed}',
                      icon: Icons.cancel_outlined,
                      color: AppTheme.danger,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),
              Text('Quick Actions', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 12),
              _QuickActionsGrid(),
              const SizedBox(height: 24),
              Text('Recent Assignments', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 12),
              if (dashboard.recentAssignments.isEmpty)
                const DCard(child: Text('No recent assignments.', style: TextStyle(color: AppTheme.textSecondary)))
              else
                ...dashboard.recentAssignments.map(
                  (a) => Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: DCard(
                      onTap: ref.watch(permissionsProvider).contains('view_orders')
                          ? () => context.push('/assignments/${a.id}')
                          : null,
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(a.subOrderNumber ?? '-', style: const TextStyle(fontWeight: FontWeight.w600)),
                                const SizedBox(height: 4),
                                Text(a.agentName ?? 'Unassigned', style: const TextStyle(color: AppTheme.textSecondary)),
                              ],
                            ),
                          ),
                          StatusChip(status: a.status),
                        ],
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CompanyHeader extends StatelessWidget {
  final DashboardCompany company;

  const _CompanyHeader({required this.company});

  @override
  Widget build(BuildContext context) {
    return DCard(
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: AppTheme.primary.withValues(alpha: 0.16),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(Icons.local_shipping, color: AppTheme.primary),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(company.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                const SizedBox(height: 4),
                if (company.status != null) StatusChip(status: company.status!),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;

  const _StatCard({required this.label, required this.value, required this.icon, required this.color});

  @override
  Widget build(BuildContext context) {
    return DCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color),
          const SizedBox(height: 8),
          Text(value, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
          Text(label, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
        ],
      ),
    );
  }
}

class _QuickActionsGrid extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: PermissionGate(
                permission: 'view_orders',
                child: _QuickActionCard(
                  label: 'Unassigned Shipments',
                  icon: Icons.assignment_late_outlined,
                  onTap: () => context.push('/assignments/unassigned'),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: PermissionGate(
                permission: 'manage_agents',
                child: _QuickActionCard(
                  label: 'Manage Agents',
                  icon: Icons.groups_outlined,
                  onTap: () => context.push('/agents'),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: PermissionGate(
                permission: 'view_reports',
                child: _QuickActionCard(
                  label: 'Reports',
                  icon: Icons.bar_chart_outlined,
                  onTap: () => context.push('/reports'),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: PermissionGate(
                permission: 'manage_agents',
                child: _QuickActionCard(
                  label: 'Supervisors',
                  icon: Icons.admin_panel_settings_outlined,
                  onTap: () => context.push('/supervisors'),
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _QuickActionCard extends StatelessWidget {
  final String label;
  final IconData icon;
  final VoidCallback onTap;

  const _QuickActionCard({required this.label, required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return DCard(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AppTheme.primary),
          const SizedBox(height: 8),
          Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}

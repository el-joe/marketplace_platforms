import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/status_chip.dart';
import '../../shared/models/dashboard.dart';
import '../assignments/assignments_provider.dart';
import '../assignments/widgets/assignment_card.dart';
import '../auth/auth_provider.dart';
import 'dashboard_provider.dart';
import 'location_tracking_provider.dart';

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashboardAsync = ref.watch(dashboardProvider);
    final locationError = ref.watch(locationTrackingProvider);

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
              if (locationError != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 16),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppTheme.danger.withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.location_off, color: AppTheme.danger),
                      const SizedBox(width: 8),
                      Expanded(child: Text(locationError, style: const TextStyle(color: AppTheme.danger))),
                    ],
                  ),
                ),
              _ShiftCard(dashboard: dashboard),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: _StatCard(
                      label: 'Earnings Today',
                      value: MoneyFormatter.format(dashboard.earningsToday, dashboard.currency),
                      icon: Icons.payments_outlined,
                      color: AppTheme.success,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _StatCard(
                      label: 'Completed',
                      value: '${dashboard.stats.completed}/${dashboard.stats.total}',
                      icon: Icons.check_circle_outline,
                      color: AppTheme.primary,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _StatCard(
                      label: 'Active',
                      value: '${dashboard.stats.active}',
                      icon: Icons.local_shipping_outlined,
                      color: AppTheme.warning,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _StatCard(
                      label: 'Failed',
                      value: '${dashboard.stats.failed}',
                      icon: Icons.cancel_outlined,
                      color: AppTheme.danger,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),
              Text('Pending assignments', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 12),
              if (dashboard.pendingAssignments.isEmpty)
                const DCard(child: Text('No pending assignments right now.'))
              else
                ...dashboard.pendingAssignments.map(
                  (a) => Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: AssignmentCard(
                      assignment: a,
                      onTap: () {
                        context.push('/assignments/${a.id}');
                        ref.invalidate(assignmentsProvider);
                      },
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

class _ShiftCard extends ConsumerWidget {
  final Dashboard dashboard;

  const _ShiftCard({required this.dashboard});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isOnShift = dashboard.status == 'on_shift';
    final busy = ref.watch(_shiftBusyProvider);

    return DCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              StatusChip(status: dashboard.status as String),
              const Spacer(),
              if (isOnShift)
                Row(
                  children: [
                    Text(dashboard.isAvailable ? 'Available' : 'On break'),
                    Switch(
                      value: dashboard.isAvailable as bool,
                      onChanged: busy
                          ? null
                          : (value) async {
                              ref.read(_shiftBusyProvider.notifier).state = true;
                              try {
                                await ref.read(dashboardProvider.notifier).setAvailability(value);
                              } catch (_) {
                                if (context.mounted) {
                                  ScaffoldMessenger.of(context)
                                      .showSnackBar(const SnackBar(content: Text('Failed to update availability.')));
                                }
                              } finally {
                                ref.read(_shiftBusyProvider.notifier).state = false;
                              }
                            },
                    ),
                  ],
                ),
            ],
          ),
          const SizedBox(height: 8),
          if (dashboard.zone != null) Text('Zone: ${dashboard.zone.name ?? '-'}'),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: isOnShift
                ? OutlinedButton.icon(
                    onPressed: busy
                        ? null
                        : () async {
                            ref.read(_shiftBusyProvider.notifier).state = true;
                            try {
                              await ref.read(dashboardProvider.notifier).endShift();
                            } catch (e) {
                              if (context.mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(content: Text(e is ApiException ? e.message : 'Failed to end shift.')),
                                );
                              }
                            } finally {
                              ref.read(_shiftBusyProvider.notifier).state = false;
                            }
                          },
                    icon: const Icon(Icons.stop_circle_outlined),
                    label: const Text('End Shift'),
                  )
                : ElevatedButton.icon(
                    onPressed: busy
                        ? null
                        : () async {
                            ref.read(_shiftBusyProvider.notifier).state = true;
                            try {
                              await ref.read(dashboardProvider.notifier).startShift();
                            } catch (e) {
                              if (context.mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(content: Text(e is ApiException ? e.message : 'Failed to start shift.')),
                                );
                              }
                            } finally {
                              ref.read(_shiftBusyProvider.notifier).state = false;
                            }
                          },
                    icon: const Icon(Icons.play_circle_outline),
                    label: const Text('Start Shift'),
                  ),
          ),
        ],
      ),
    );
  }
}

final _shiftBusyProvider = StateProvider<bool>((ref) => false);

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
          Text(value, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          Text(label, style: TextStyle(color: Colors.grey.shade600, fontSize: 12)),
        ],
      ),
    );
  }
}

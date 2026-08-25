import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/empty_view.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/permission_gate.dart';
import '../../shared/widgets/status_chip.dart';
import 'assignments_provider.dart';

class AssignmentsListScreen extends ConsumerStatefulWidget {
  const AssignmentsListScreen({super.key});

  @override
  ConsumerState<AssignmentsListScreen> createState() => _AssignmentsListScreenState();
}

class _AssignmentsListScreenState extends ConsumerState<AssignmentsListScreen> {
  static const _statuses = ['', 'assigned', 'accepted', 'picked_up', 'delivered', 'failed'];

  @override
  Widget build(BuildContext context) {
    final assignmentsAsync = ref.watch(assignmentsProvider);
    final filter = ref.watch(assignmentsFilterProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Orders'),
        actions: [
          PermissionGate(
            permission: 'assign_orders',
            child: IconButton(
              icon: const Icon(Icons.assignment_late_outlined),
              tooltip: 'Unassigned shipments',
              onPressed: () => context.push('/assignments/unassigned'),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: SizedBox(
              height: 36,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: _statuses.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  final status = _statuses[index];
                  final label = status.isEmpty ? 'All' : status.replaceAll('_', ' ');
                  final selected = (filter.status ?? '') == status;
                  return ChoiceChip(
                    label: Text(label),
                    selected: selected,
                    onSelected: (_) {
                      ref.read(assignmentsFilterProvider.notifier).state = filter.copyWith(status: status);
                      ref.invalidate(assignmentsProvider);
                    },
                  );
                },
              ),
            ),
          ),
          Expanded(
            child: assignmentsAsync.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(
                message: e is ApiException ? e.message : 'Failed to load orders.',
                onRetry: () => ref.read(assignmentsProvider.notifier).refresh(),
              ),
              data: (paginated) => paginated.items.isEmpty
                  ? const EmptyView(message: 'No assignments found.', icon: Icons.local_shipping_outlined)
                  : RefreshIndicator(
                      onRefresh: () => ref.read(assignmentsProvider.notifier).refresh(),
                      child: ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                        itemCount: paginated.items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 12),
                        itemBuilder: (context, index) {
                          final a = paginated.items[index];
                          return DCard(
                            onTap: () => context.push('/assignments/${a.id}'),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(a.subOrderNumber ?? '-', style: const TextStyle(fontWeight: FontWeight.w600)),
                                      const SizedBox(height: 4),
                                      Text(a.agent?.name ?? 'Unassigned',
                                          style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                                    ],
                                  ),
                                ),
                                StatusChip(status: a.status),
                              ],
                            ),
                          );
                        },
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

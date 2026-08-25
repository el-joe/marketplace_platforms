import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../shared/widgets/empty_view.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import 'assignments_provider.dart';
import 'widgets/assignment_card.dart';

class AssignmentsListScreen extends ConsumerWidget {
  const AssignmentsListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final assignmentsAsync = ref.watch(assignmentsProvider);

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Deliveries'),
          bottom: const TabBar(tabs: [Tab(text: 'Active'), Tab(text: 'Completed Today')]),
        ),
        body: assignmentsAsync.when(
          loading: () => const LoadingView(),
          error: (e, _) => ErrorView(
            message: e is ApiException ? e.message : 'Failed to load assignments.',
            onRetry: () => ref.read(assignmentsProvider.notifier).refresh(),
          ),
          data: (dashboard) => TabBarView(
            children: [
              RefreshIndicator(
                onRefresh: () => ref.read(assignmentsProvider.notifier).refresh(),
                child: dashboard.active.isEmpty
                    ? ListView(children: const [
                        SizedBox(height: 120),
                        EmptyView(message: 'No active deliveries.', icon: Icons.local_shipping_outlined),
                      ])
                    : ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: dashboard.active.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 12),
                        itemBuilder: (context, index) {
                          final a = dashboard.active[index];
                          return AssignmentCard(assignment: a, onTap: () => context.push('/assignments/${a.id}'));
                        },
                      ),
              ),
              RefreshIndicator(
                onRefresh: () => ref.read(assignmentsProvider.notifier).refresh(),
                child: dashboard.completedToday.isEmpty
                    ? ListView(children: const [
                        SizedBox(height: 120),
                        EmptyView(message: 'No completed deliveries today.', icon: Icons.check_circle_outline),
                      ])
                    : ListView.separated(
                        padding: const EdgeInsets.all(16),
                        itemCount: dashboard.completedToday.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 12),
                        itemBuilder: (context, index) {
                          final a = dashboard.completedToday[index];
                          return AssignmentCard(assignment: a, onTap: () => context.push('/assignments/${a.id}'));
                        },
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

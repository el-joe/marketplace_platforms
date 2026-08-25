import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/empty_view.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/status_chip.dart';
import 'agents_provider.dart';

class AgentsListScreen extends ConsumerStatefulWidget {
  const AgentsListScreen({super.key});

  @override
  ConsumerState<AgentsListScreen> createState() => _AgentsListScreenState();
}

class _AgentsListScreenState extends ConsumerState<AgentsListScreen> {
  final _searchController = TextEditingController();

  static const _statuses = ['', 'active', 'on_shift', 'inactive', 'suspended'];

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final agentsAsync = ref.watch(agentsProvider);
    final filter = ref.watch(agentsFilterProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Agents')),
      floatingActionButton: FloatingActionButton(
        onPressed: () => context.push('/agents/new'),
        child: const Icon(Icons.add),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                TextField(
                  controller: _searchController,
                  decoration: const InputDecoration(
                    hintText: 'Search by name or email',
                    prefixIcon: Icon(Icons.search),
                  ),
                  onSubmitted: (v) {
                    ref.read(agentsFilterProvider.notifier).state = filter.copyWith(search: v);
                    ref.invalidate(agentsProvider);
                  },
                ),
                const SizedBox(height: 12),
                SizedBox(
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
                          ref.read(agentsFilterProvider.notifier).state = filter.copyWith(status: status);
                          ref.invalidate(agentsProvider);
                        },
                      );
                    },
                  ),
                ),
              ],
            ),
          ),
          Expanded(
            child: agentsAsync.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(
                message: e is ApiException ? e.message : 'Failed to load agents.',
                onRetry: () => ref.read(agentsProvider.notifier).refresh(),
              ),
              data: (paginated) => paginated.items.isEmpty
                  ? const EmptyView(message: 'No agents found.', icon: Icons.groups_outlined)
                  : RefreshIndicator(
                      onRefresh: () => ref.read(agentsProvider.notifier).refresh(),
                      child: ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                        itemCount: paginated.items.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 12),
                        itemBuilder: (context, index) {
                          final agent = paginated.items[index];
                          return DCard(
                            onTap: () => context.push('/agents/${agent.id}'),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(agent.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                                      const SizedBox(height: 4),
                                      Text(agent.email ?? agent.phone ?? '-',
                                          style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                                      if (agent.zone?.name != null) ...[
                                        const SizedBox(height: 4),
                                        Text('Zone: ${agent.zone!.name}',
                                            style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                                      ],
                                    ],
                                  ),
                                ),
                                StatusChip(status: agent.status),
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

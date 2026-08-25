import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/models/supervisor.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/empty_view.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../auth/auth_provider.dart';
import 'supervisor_form_sheet.dart';
import 'supervisors_provider.dart';

class SupervisorsListScreen extends ConsumerWidget {
  const SupervisorsListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final supervisorsAsync = ref.watch(supervisorsProvider);
    final currentSupervisorId = ref.watch(authProvider).supervisor?.id;

    return Scaffold(
      appBar: AppBar(title: const Text('Supervisors')),
      floatingActionButton: FloatingActionButton(
        onPressed: () => showModalBottomSheet(
          context: context,
          isScrollControlled: true,
          builder: (_) => const SupervisorFormSheet(),
        ),
        child: const Icon(Icons.add),
      ),
      body: supervisorsAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load supervisors.',
          onRetry: () => ref.read(supervisorsProvider.notifier).refresh(),
        ),
        data: (paginated) => paginated.items.isEmpty
            ? const EmptyView(message: 'No supervisors found.', icon: Icons.admin_panel_settings_outlined)
            : RefreshIndicator(
                onRefresh: () => ref.read(supervisorsProvider.notifier).refresh(),
                child: ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: paginated.items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemBuilder: (context, index) {
                    final supervisor = paginated.items[index];
                    final isSelf = supervisor.id == currentSupervisorId;
                    return DCard(
                      onTap: () => showModalBottomSheet(
                        context: context,
                        isScrollControlled: true,
                        builder: (_) => SupervisorFormSheet(supervisor: supervisor),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Text(supervisor.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                                    if (isSelf) ...[
                                      const SizedBox(width: 6),
                                      const Text('(you)', style: TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                                    ],
                                    if (!supervisor.isActive) ...[
                                      const SizedBox(width: 6),
                                      const Text('(inactive)', style: TextStyle(color: AppTheme.danger, fontSize: 12)),
                                    ],
                                  ],
                                ),
                                const SizedBox(height: 4),
                                Text(supervisor.email, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                                const SizedBox(height: 6),
                                Wrap(
                                  spacing: 6,
                                  runSpacing: 6,
                                  children: supervisor.permissions
                                      .map((p) => Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                            decoration: BoxDecoration(
                                              color: AppTheme.primary.withValues(alpha: 0.14),
                                              borderRadius: BorderRadius.circular(12),
                                            ),
                                            child: Text(p.replaceAll('_', ' '),
                                                style: const TextStyle(color: AppTheme.primary, fontSize: 11)),
                                          ))
                                      .toList(),
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.delete_outline, color: AppTheme.danger),
                            onPressed: isSelf
                                ? () => ScaffoldMessenger.of(context).showSnackBar(
                                      const SnackBar(content: Text('You cannot delete your own account.')),
                                    )
                                : () => _confirmDelete(context, ref, supervisor),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),
      ),
    );
  }

  void _confirmDelete(BuildContext context, WidgetRef ref, Supervisor supervisor) {
    showDialog(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Delete Supervisor'),
        content: Text('Are you sure you want to delete ${supervisor.name}?'),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('Cancel')),
          TextButton(
            onPressed: () async {
              Navigator.of(dialogContext).pop();
              try {
                await ref.read(supervisorsProvider.notifier).delete(supervisor.id);
              } catch (e) {
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(e is ApiException ? e.message : 'Failed to delete supervisor.')),
                  );
                }
              }
            },
            child: const Text('Delete', style: TextStyle(color: AppTheme.danger)),
          ),
        ],
      ),
    );
  }
}

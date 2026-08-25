import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/models/agent.dart';
import '../../shared/models/assignment.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/empty_view.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import 'assignments_provider.dart';

class UnassignedScreen extends ConsumerWidget {
  const UnassignedScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final unassignedAsync = ref.watch(unassignedProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Unassigned Shipments')),
      body: unassignedAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load unassigned shipments.',
          onRetry: () => ref.read(unassignedProvider.notifier).refresh(),
        ),
        data: (paginated) => paginated.items.isEmpty
            ? const EmptyView(message: 'No unassigned shipments.', icon: Icons.assignment_turned_in_outlined)
            : RefreshIndicator(
                onRefresh: () => ref.read(unassignedProvider.notifier).refresh(),
                child: ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: paginated.items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemBuilder: (context, index) {
                    final shipment = paginated.items[index];
                    return DCard(
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(shipment.orderNumber ?? shipment.subOrderNumber ?? shipment.trackingNumber ?? '-',
                                    style: const TextStyle(fontWeight: FontWeight.w600)),
                                const SizedBox(height: 4),
                                if (shipment.trackingNumber != null)
                                  Text('Tracking: ${shipment.trackingNumber}',
                                      style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                              ],
                            ),
                          ),
                          ElevatedButton(
                            onPressed: () => _showAssignSheet(context, ref, shipment),
                            child: const Text('Assign'),
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

  void _showAssignSheet(BuildContext context, WidgetRef ref, UnassignedShipment shipment) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => _AssignSheet(shipment: shipment),
    );
  }
}

class _AssignSheet extends ConsumerStatefulWidget {
  final UnassignedShipment shipment;

  const _AssignSheet({required this.shipment});

  @override
  ConsumerState<_AssignSheet> createState() => _AssignSheetState();
}

class _AssignSheetState extends ConsumerState<_AssignSheet> {
  String? _selectedAgentId;
  bool _busy = false;
  String? _error;

  Future<void> _assign() async {
    if (_selectedAgentId == null) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref.read(unassignedProvider.notifier).assign(widget.shipment.id, _selectedAgentId!);
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Failed to assign shipment.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final agentsAsync = ref.watch(availableAgentsProvider);

    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Assign ${widget.shipment.orderNumber ?? widget.shipment.subOrderNumber ?? ''}',
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
            const SizedBox(height: 16),
            agentsAsync.when(
              loading: () => const LoadingView(),
              error: (e, _) =>
                  Text(e is ApiException ? e.message : 'Failed to load agents.', style: const TextStyle(color: AppTheme.danger)),
              data: (agents) {
                if (agents.isEmpty) {
                  return const Text('No active agents available.', style: TextStyle(color: AppTheme.textSecondary));
                }
                return DropdownButtonFormField<String>(
                  initialValue: _selectedAgentId,
                  decoration: const InputDecoration(labelText: 'Agent'),
                  items: agents.map((Agent a) => DropdownMenuItem(value: a.id, child: Text(a.name))).toList(),
                  onChanged: (v) => setState(() => _selectedAgentId = v),
                );
              },
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: const TextStyle(color: AppTheme.danger)),
            ],
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: (_selectedAgentId == null || _busy) ? null : _assign,
              child: _busy
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Assign'),
            ),
          ],
        ),
      ),
    );
  }
}

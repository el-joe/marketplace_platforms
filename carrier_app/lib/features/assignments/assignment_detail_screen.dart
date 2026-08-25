import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/date_formatter.dart';
import '../../shared/models/agent.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/permission_gate.dart';
import '../../shared/widgets/status_chip.dart';
import 'assignments_provider.dart';

class AssignmentDetailScreen extends ConsumerWidget {
  final String assignmentId;

  const AssignmentDetailScreen({super.key, required this.assignmentId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final assignmentAsync = ref.watch(assignmentDetailProvider(assignmentId));

    return Scaffold(
      appBar: AppBar(title: const Text('Assignment Details')),
      body: assignmentAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load assignment.',
          onRetry: () => ref.read(assignmentDetailProvider(assignmentId).notifier).refresh(),
        ),
        data: (assignment) => RefreshIndicator(
          onRefresh: () => ref.read(assignmentDetailProvider(assignmentId).notifier).refresh(),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              DCard(
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(assignment.subOrderNumber ?? assignment.shipment?.subOrder?.subOrderNumber ?? '-',
                              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                          if (assignment.shipment?.trackingNumber != null) ...[
                            const SizedBox(height: 4),
                            Text('Tracking: ${assignment.shipment!.trackingNumber}',
                                style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                          ],
                        ],
                      ),
                    ),
                    StatusChip(status: assignment.status),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              DCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Agent', style: TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 8),
                    Text(assignment.agent?.name ?? 'Unassigned', style: const TextStyle(color: AppTheme.textSecondary)),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              DCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Timeline', style: TextStyle(fontWeight: FontWeight.w600)),
                    const SizedBox(height: 8),
                    _TimelineRow(label: 'Assigned', dt: assignment.assignedAt),
                    _TimelineRow(label: 'Accepted', dt: assignment.acceptedAt),
                    _TimelineRow(label: 'Picked up', dt: assignment.pickedUpAt),
                    _TimelineRow(label: 'Delivered', dt: assignment.deliveredAt),
                  ],
                ),
              ),
              if (assignment.shipment?.trackingEvents.isNotEmpty ?? false) ...[
                const SizedBox(height: 16),
                DCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Tracking Events', style: TextStyle(fontWeight: FontWeight.w600)),
                      const SizedBox(height: 8),
                      ...assignment.shipment!.trackingEvents.map(
                        (e) => Padding(
                          padding: const EdgeInsets.only(top: 6),
                          child: Row(
                            children: [
                              Expanded(child: Text(e.status ?? '-')),
                              Text(DateFormatter.dateTime(e.occurredAt),
                                  style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              if (assignment.codAmountCollected != null) ...[
                const SizedBox(height: 16),
                DCard(
                  child: Row(
                    children: [
                      const Icon(Icons.payments_outlined, color: AppTheme.warning),
                      const SizedBox(width: 8),
                      Text('COD collected: ${assignment.codAmountCollected}'),
                    ],
                  ),
                ),
              ],
              if (assignment.deliveryOtp != null) ...[
                const SizedBox(height: 16),
                DCard(
                  child: Row(
                    children: [
                      const Icon(Icons.pin_outlined, color: AppTheme.primary),
                      const SizedBox(width: 8),
                      Text('Delivery OTP: ${assignment.deliveryOtp}'),
                    ],
                  ),
                ),
              ],
              if (assignment.isReassignable)
                PermissionGate(
                  permission: 'assign_orders',
                  child: Padding(
                    padding: const EdgeInsets.only(top: 16),
                    child: _ReassignSection(assignmentId: assignmentId, currentAgentId: assignment.agent?.id),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TimelineRow extends StatelessWidget {
  final String label;
  final DateTime? dt;

  const _TimelineRow({required this.label, required this.dt});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Row(
        children: [
          Icon(
            dt != null ? Icons.check_circle : Icons.circle_outlined,
            size: 16,
            color: dt != null ? AppTheme.success : AppTheme.textSecondary,
          ),
          const SizedBox(width: 8),
          Expanded(child: Text(label)),
          Text(DateFormatter.dateTime(dt), style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
        ],
      ),
    );
  }
}

class _ReassignSection extends ConsumerStatefulWidget {
  final String assignmentId;
  final String? currentAgentId;

  const _ReassignSection({required this.assignmentId, this.currentAgentId});

  @override
  ConsumerState<_ReassignSection> createState() => _ReassignSectionState();
}

class _ReassignSectionState extends ConsumerState<_ReassignSection> {
  String? _selectedAgentId;
  bool _busy = false;

  @override
  Widget build(BuildContext context) {
    final agentsAsync = ref.watch(availableAgentsProvider);

    return DCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Reassign Agent', style: TextStyle(fontWeight: FontWeight.w600)),
          const SizedBox(height: 12),
          agentsAsync.when(
            loading: () => const LoadingView(),
            error: (e, _) => Text(e is ApiException ? e.message : 'Failed to load agents.',
                style: const TextStyle(color: AppTheme.danger)),
            data: (agents) {
              final candidates = agents.where((Agent a) => a.id != widget.currentAgentId).toList();
              if (candidates.isEmpty) {
                return const Text('No other active agents available.', style: TextStyle(color: AppTheme.textSecondary));
              }
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: _selectedAgentId,
                    decoration: const InputDecoration(labelText: 'New agent'),
                    items: candidates.map((a) => DropdownMenuItem(value: a.id, child: Text(a.name))).toList(),
                    onChanged: (v) => setState(() => _selectedAgentId = v),
                  ),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: (_selectedAgentId == null || _busy)
                        ? null
                        : () async {
                            setState(() => _busy = true);
                            try {
                              await ref
                                  .read(assignmentDetailProvider(widget.assignmentId).notifier)
                                  .reassign(_selectedAgentId!);
                              if (context.mounted) {
                                ScaffoldMessenger.of(context)
                                    .showSnackBar(const SnackBar(content: Text('Assignment reassigned.')));
                              }
                            } catch (e) {
                              if (context.mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(content: Text(e is ApiException ? e.message : 'Failed to reassign.')),
                                );
                              }
                            } finally {
                              if (mounted) setState(() => _busy = false);
                            }
                          },
                    child: _busy
                        ? const SizedBox(
                            height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Text('Reassign'),
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

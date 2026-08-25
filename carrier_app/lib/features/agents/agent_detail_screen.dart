import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/models/agent.dart';
import '../../shared/models/zone.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/status_chip.dart';
import 'agents_provider.dart';

class AgentDetailScreen extends ConsumerWidget {
  final String agentId;

  const AgentDetailScreen({super.key, required this.agentId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final agentAsync = ref.watch(agentDetailProvider(agentId));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Agent Details'),
        actions: [
          IconButton(
            icon: const Icon(Icons.edit_outlined),
            onPressed: () => context.push('/agents/$agentId/edit'),
          ),
        ],
      ),
      body: agentAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load agent.',
          onRetry: () => ref.read(agentDetailProvider(agentId).notifier).refresh(),
        ),
        data: (agent) => RefreshIndicator(
          onRefresh: () => ref.read(agentDetailProvider(agentId).notifier).refresh(),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              DCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(agent.name,
                              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                        ),
                        StatusChip(status: agent.status),
                      ],
                    ),
                    const SizedBox(height: 12),
                    if (agent.email != null) _InfoRow(icon: Icons.email_outlined, label: agent.email!),
                    if (agent.phone != null) _InfoRow(icon: Icons.phone_outlined, label: agent.phone!),
                    if (agent.vehicleType != null)
                      _InfoRow(icon: Icons.motorcycle_outlined,
                          label: '${agent.vehicleType} ${agent.vehiclePlate ?? ''}'.trim()),
                    if (agent.ratingAvg != null)
                      _InfoRow(icon: Icons.star_outline, label: agent.ratingAvg!.toStringAsFixed(1)),
                    if (agent.totalDeliveries != null)
                      _InfoRow(icon: Icons.local_shipping_outlined, label: '${agent.totalDeliveries} deliveries'),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              DCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Expanded(
                          child: Text('Zone', style: TextStyle(fontWeight: FontWeight.w600)),
                        ),
                        TextButton(
                          onPressed: () => _showChangeZoneSheet(context, ref, agent),
                          child: const Text('Change Zone'),
                        ),
                      ],
                    ),
                    Text(agent.zone?.name ?? 'Unassigned', style: const TextStyle(color: AppTheme.textSecondary)),
                  ],
                ),
              ),
              if (agent.emergencyContactName != null || agent.emergencyContactPhone != null) ...[
                const SizedBox(height: 16),
                DCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Emergency Contact', style: TextStyle(fontWeight: FontWeight.w600)),
                      const SizedBox(height: 8),
                      if (agent.emergencyContactName != null)
                        _InfoRow(icon: Icons.person_outline, label: agent.emergencyContactName!),
                      if (agent.emergencyContactPhone != null)
                        _InfoRow(icon: Icons.phone_outlined, label: agent.emergencyContactPhone!),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.lock_reset_outlined),
                      label: const Text('Reset Password'),
                      onPressed: () => _showResetPasswordDialog(context, ref),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: agent.status == 'suspended'
                    ? ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(backgroundColor: AppTheme.success),
                        icon: const Icon(Icons.play_circle_outline),
                        label: const Text('Activate Agent'),
                        onPressed: () async {
                          try {
                            await ref.read(agentDetailProvider(agentId).notifier).activate();
                          } catch (e) {
                            if (context.mounted) _showError(context, e);
                          }
                        },
                      )
                    : ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger),
                        icon: const Icon(Icons.block_outlined),
                        label: const Text('Suspend Agent'),
                        onPressed: () async {
                          try {
                            await ref.read(agentDetailProvider(agentId).notifier).suspend();
                          } catch (e) {
                            if (context.mounted) _showError(context, e);
                          }
                        },
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showError(BuildContext context, Object e) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(e is ApiException ? e.message : 'Something went wrong.')),
    );
  }

  void _showResetPasswordDialog(BuildContext context, WidgetRef ref) {
    final controller = TextEditingController();
    showDialog(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Reset Password'),
        content: TextField(
          controller: controller,
          obscureText: true,
          decoration: const InputDecoration(labelText: 'New password (min 8 characters)'),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('Cancel')),
          TextButton(
            onPressed: () async {
              final password = controller.text;
              if (password.length < 8) return;
              Navigator.of(dialogContext).pop();
              try {
                await ref.read(agentDetailProvider(agentId).notifier).resetPassword(password);
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Password reset.')));
                }
              } catch (e) {
                if (context.mounted) _showError(context, e);
              }
            },
            child: const Text('Reset'),
          ),
        ],
      ),
    );
  }

  void _showChangeZoneSheet(BuildContext context, WidgetRef ref, Agent agent) {
    showModalBottomSheet(
      context: context,
      builder: (sheetContext) => Consumer(
        builder: (context, ref, _) {
          final zonesAsync = ref.watch(zonesProvider);
          return SafeArea(
            child: zonesAsync.when(
              loading: () => const Padding(padding: EdgeInsets.all(32), child: LoadingView()),
              error: (e, _) => Padding(
                padding: const EdgeInsets.all(16),
                child: ErrorView(message: e is ApiException ? e.message : 'Failed to load zones.'),
              ),
              data: (zones) => ListView(
                shrinkWrap: true,
                padding: const EdgeInsets.symmetric(vertical: 8),
                children: [
                  ListTile(
                    title: const Text('Unassigned'),
                    trailing: agent.zone == null ? const Icon(Icons.check, color: AppTheme.primary) : null,
                    onTap: () async {
                      Navigator.of(sheetContext).pop();
                      await _changeZone(context, ref, null);
                    },
                  ),
                  ...zones.map((Zone zone) => ListTile(
                        title: Text(zone.name),
                        subtitle: zone.atCapacity ? const Text('At capacity', style: TextStyle(color: AppTheme.danger)) : null,
                        trailing: agent.zone?.id == zone.id ? const Icon(Icons.check, color: AppTheme.primary) : null,
                        onTap: () async {
                          Navigator.of(sheetContext).pop();
                          await _changeZone(context, ref, zone.id);
                        },
                      )),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Future<void> _changeZone(BuildContext context, WidgetRef ref, int? zoneId) async {
    try {
      await ref.read(agentDetailProvider(agentId).notifier).assignZone(zoneId);
    } catch (e) {
      if (context.mounted) _showError(context, e);
    }
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label;

  const _InfoRow({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Row(
        children: [
          Icon(icon, size: 16, color: AppTheme.textSecondary),
          const SizedBox(width: 8),
          Expanded(child: Text(label, style: const TextStyle(color: AppTheme.textSecondary))),
        ],
      ),
    );
  }
}

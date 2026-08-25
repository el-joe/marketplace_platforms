import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../auth/auth_provider.dart';
import '../notifications/fcm_service.dart';
import 'profile_provider.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final profileAsync = ref.watch(profileProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: profileAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(message: e is ApiException ? e.message : 'Failed to load profile.'),
        data: (agent) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            DCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(agent.name, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 4),
                  Text(agent.email ?? agent.phone ?? '', style: TextStyle(color: Colors.grey.shade600)),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      const Icon(Icons.star, color: Colors.amber, size: 18),
                      const SizedBox(width: 4),
                      Text(agent.ratingAvg?.toStringAsFixed(1) ?? '-'),
                      const SizedBox(width: 16),
                      const Icon(Icons.local_shipping_outlined, size: 18, color: Colors.grey),
                      const SizedBox(width: 4),
                      Text('${agent.totalDeliveries} deliveries'),
                    ],
                  ),
                  if (agent.vehicleType != null || agent.vehiclePlate != null) ...[
                    const SizedBox(height: 8),
                    Text('${agent.vehicleType ?? '-'} · ${agent.vehiclePlate ?? '-'}'),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),
            _MenuTile(icon: Icons.badge_outlined, label: 'Documents', onTap: () => context.push('/profile/documents')),
            _MenuTile(icon: Icons.lock_outline, label: 'Change Password', onTap: () => context.push('/profile/change-password')),
            _MenuTile(icon: Icons.account_balance_wallet_outlined, label: 'Wallet', onTap: () => context.push('/wallet')),
            _MenuTile(icon: Icons.receipt_long_outlined, label: 'COD Settlements', onTap: () => context.push('/cod-settlements')),
            _MenuTile(icon: Icons.support_agent_outlined, label: 'Support', onTap: () => context.push('/support')),
            const SizedBox(height: 16),
            _MenuTile(
              icon: Icons.logout,
              label: 'Logout',
              color: AppTheme.danger,
              onTap: () async {
                await FcmService.instance.unregister();
                await ref.read(authProvider.notifier).logout();
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _MenuTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final Color? color;

  const _MenuTile({required this.icon, required this.label, required this.onTap, this.color});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: DCard(
        onTap: onTap,
        child: Row(
          children: [
            Icon(icon, color: color ?? Colors.grey.shade700),
            const SizedBox(width: 12),
            Expanded(child: Text(label, style: TextStyle(color: color))),
            const Icon(Icons.chevron_right, color: Colors.grey),
          ],
        ),
      ),
    );
  }
}

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/notification_bell.dart';
import '../../shared/widgets/p_card.dart';
import '../auth/auth_provider.dart';
import '../notifications/fcm_service.dart';
import 'profile_provider.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final profileAsync = ref.watch(profileProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile'),
        actions: const [NotificationBell(), SizedBox(width: 8)],
      ),
      body: profileAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load profile.',
          onRetry: () => ref.invalidate(profileProvider),
        ),
        data: (v) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(profileProvider),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              PCard(
                child: Row(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(32),
                      child: v['logo_url'] != null
                          ? CachedNetworkImage(imageUrl: '${v['logo_url']}', width: 56, height: 56, fit: BoxFit.cover)
                          : Container(
                              width: 56,
                              height: 56,
                              decoration: const BoxDecoration(color: AppTheme.background, shape: BoxShape.circle),
                              child: const Icon(Icons.storefront_outlined, color: AppTheme.textSecondary),
                            ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('${v['store_name'] ?? '-'}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 4),
                          Text('${v['contact_email'] ?? v['contact_phone'] ?? ''}',
                              style: const TextStyle(color: AppTheme.textSecondary)),
                          const SizedBox(height: 6),
                          Row(
                            children: [
                              const Icon(Icons.star, color: AppTheme.primary, size: 16),
                              const SizedBox(width: 4),
                              Text('${v['store_rating_avg'] ?? '-'} (${v['store_rating_count'] ?? 0})'),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              PCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Business', style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 8),
                    _row('Business name', v['business_name']),
                    _row('Business type', v['business_type']),
                    _row('Registration #', v['business_registration_number']),
                    _row('Tax ID', v['tax_id']),
                    _row('Status', v['global_status']),
                    _row('Partner since', v['partner_since_years'] != null ? '${v['partner_since_years']} years' : null),
                    _row('Warranty', v['warranty_months'] != null ? '${v['warranty_months']} months' : null),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              _MenuTile(icon: Icons.badge_outlined, label: 'Documents', onTap: () => context.push('/profile/documents')),
              const SizedBox(height: 16),
              _MenuTile(
                icon: Icons.logout,
                label: 'Logout',
                color: AppTheme.error,
                onTap: () async {
                  await FcmService.instance.unregister();
                  await ref.read(authProvider.notifier).logout();
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _row(String label, dynamic value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: AppTheme.textSecondary)),
          Text('${value ?? '-'}'),
        ],
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
    return PCard(
      onTap: onTap,
      child: Row(
        children: [
          Icon(icon, color: color ?? AppTheme.textSecondary),
          const SizedBox(width: 12),
          Expanded(child: Text(label, style: TextStyle(color: color))),
          const Icon(Icons.chevron_right, color: AppTheme.textSecondary),
        ],
      ),
    );
  }
}

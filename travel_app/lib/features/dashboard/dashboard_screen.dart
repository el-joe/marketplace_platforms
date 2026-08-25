import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/auth/auth_provider.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/stat_card.dart';
import '../../shared/widgets/status_chip.dart';
import '../../shared/widgets/t_card.dart';
import 'dashboard_repository.dart';

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashboardAsync = ref.watch(dashboardProvider);
    final agency = ref.watch(authProvider).agency;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () => context.push('/notifications'),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => ref.refresh(dashboardProvider.future),
        child: dashboardAsync.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => ListView(
            children: [
              const SizedBox(height: 120),
              EmptyState(message: 'Failed to load dashboard.\n$error', icon: Icons.error_outline),
            ],
          ),
          data: (data) => ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (agency != null)
                TCard(
                  child: Row(
                    children: [
                      CircleAvatar(
                        radius: 26,
                        backgroundColor: AppColors.background,
                        backgroundImage: agency.logoUrl != null
                            ? CachedNetworkImageProvider(agency.logoUrl!)
                            : null,
                        child: agency.logoUrl == null
                            ? const Icon(Icons.business, color: AppColors.textSecondary)
                            : null,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(agency.name, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                            const SizedBox(height: 4),
                            if (agency.status != null)
                              StatusChip(status: agency.status!, type: StatusChipType.package),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              const SizedBox(height: 16),
              GridView.count(
                crossAxisCount: 2,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: 1.5,
                children: [
                  StatCard(label: 'Active Packages', value: '${data.activePackages}', icon: Icons.flight_takeoff),
                  StatCard(label: 'Total Bookings', value: '${data.totalBookings}', icon: Icons.book_online),
                  StatCard(label: 'Pending Bookings', value: '${data.pendingBookings}', icon: Icons.hourglass_empty, color: AppColors.warning),
                  StatCard(label: 'Confirmed', value: '${data.confirmedBookings}', icon: Icons.check_circle_outline, color: AppColors.success),
                  StatCard(label: 'New Inquiries', value: '${data.newInquiries}', icon: Icons.mail_outline),
                  if (data.revenueByCurrency.isNotEmpty)
                    StatCard(
                      label: 'Revenue (${data.revenueByCurrency.first['currency']})',
                      value: MoneyFormatter.compact(
                        data.revenueByCurrency.first['total'] as int? ?? 0,
                        data.revenueByCurrency.first['currency'] as String? ?? '',
                      ),
                      icon: Icons.attach_money,
                      color: AppColors.accent,
                    ),
                ],
              ),
              if (data.revenueByCurrency.length > 1) ...[
                const SizedBox(height: 20),
                const Text('Revenue by Currency', style: TextStyle(fontWeight: FontWeight.bold)),
                const SizedBox(height: 8),
                TCard(
                  child: Column(
                    children: [
                      for (final row in data.revenueByCurrency)
                        Padding(
                          padding: const EdgeInsets.symmetric(vertical: 4),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(row['currency'] as String? ?? ''),
                              Text(MoneyFormatter.format(row['total'] as int? ?? 0, row['currency'] as String? ?? '')),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 20),
              _SectionHeader(title: 'Recent Bookings', onSeeAll: () => context.push('/bookings')),
              const SizedBox(height: 8),
              if (data.recentBookings.isEmpty)
                const EmptyState(message: 'No recent bookings')
              else
                for (final booking in data.recentBookings)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: TCard(
                      onTap: () => context.push('/bookings/${booking['id']}'),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(booking['booking_number']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.bold)),
                                Text(
                                  '${booking['package_title'] ?? ''} · ${booking['customer_name'] ?? ''}',
                                  style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
                                ),
                              ],
                            ),
                          ),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              if (booking['status'] != null)
                                StatusChip(status: booking['status'] as String, type: StatusChipType.booking),
                              const SizedBox(height: 4),
                              Text(
                                MoneyFormatter.format(booking['total_price'] as int? ?? 0, booking['currency'] as String? ?? ''),
                                style: const TextStyle(fontSize: 12),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
              const SizedBox(height: 20),
              _SectionHeader(title: 'Recent Packages', onSeeAll: () => context.push('/packages')),
              const SizedBox(height: 8),
              if (data.recentPackages.isEmpty)
                const EmptyState(message: 'No recent packages')
              else
                SizedBox(
                  height: 140,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: data.recentPackages.length,
                    separatorBuilder: (_, __) => const SizedBox(width: 10),
                    itemBuilder: (context, i) {
                      final pkg = data.recentPackages[i];
                      return SizedBox(
                        width: 160,
                        child: TCard(
                          onTap: () => context.push('/packages/${pkg['id']}'),
                          padding: EdgeInsets.zero,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              ClipRRect(
                                borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
                                child: SizedBox(
                                  height: 80,
                                  width: double.infinity,
                                  child: pkg['thumbnail'] != null
                                      ? CachedNetworkImage(imageUrl: pkg['thumbnail'] as String, fit: BoxFit.cover)
                                      : Container(color: AppColors.background),
                                ),
                              ),
                              Padding(
                                padding: const EdgeInsets.all(8),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      pkg['title']?.toString() ?? '',
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold),
                                    ),
                                    const SizedBox(height: 4),
                                    if (pkg['status'] != null)
                                      StatusChip(status: pkg['status'] as String, type: StatusChipType.package),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ),
              const SizedBox(height: 20),
              _SectionHeader(title: 'Recent Inquiries', onSeeAll: () => context.push('/inquiries')),
              const SizedBox(height: 8),
              if (data.recentInquiries.isEmpty)
                const EmptyState(message: 'No recent inquiries')
              else
                for (final inquiry in data.recentInquiries)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: TCard(
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(inquiry['name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.bold)),
                                Text(
                                  inquiry['package_title']?.toString() ?? '',
                                  style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
                                ),
                              ],
                            ),
                          ),
                          if (inquiry['status'] != null)
                            StatusChip(status: inquiry['status'] as String, type: StatusChipType.inquiry),
                        ],
                      ),
                    ),
                  ),
              const SizedBox(height: 20),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _QuickNavChip(label: 'All Bookings', onTap: () => context.push('/bookings')),
                  _QuickNavChip(label: 'Inquiries', onTap: () => context.push('/inquiries')),
                  _QuickNavChip(label: 'Performance', onTap: () => context.push('/performance')),
                  _QuickNavChip(label: 'Campaigns', onTap: () => context.push('/campaigns')),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, required this.onSeeAll});

  final String title;
  final VoidCallback onSeeAll;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
        TextButton(onPressed: onSeeAll, child: const Text('See all')),
      ],
    );
  }
}

class _QuickNavChip extends StatelessWidget {
  const _QuickNavChip({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ActionChip(
      label: Text(label),
      onPressed: onTap,
      backgroundColor: AppColors.card,
    );
  }
}

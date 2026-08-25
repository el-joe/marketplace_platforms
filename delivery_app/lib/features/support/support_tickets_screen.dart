import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/utils/date_formatter.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/empty_view.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/status_chip.dart';
import 'support_provider.dart';

class SupportTicketsScreen extends ConsumerWidget {
  const SupportTicketsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ticketsAsync = ref.watch(ticketsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Support')),
      floatingActionButton: FloatingActionButton(
        onPressed: () => context.push('/support/new'),
        child: const Icon(Icons.add),
      ),
      body: ticketsAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load tickets.',
          onRetry: () => ref.read(ticketsProvider.notifier).refresh(),
        ),
        data: (paginated) => RefreshIndicator(
          onRefresh: () => ref.read(ticketsProvider.notifier).refresh(),
          child: paginated.items.isEmpty
              ? ListView(children: const [
                  SizedBox(height: 120),
                  EmptyView(message: 'No support tickets yet.', icon: Icons.support_agent_outlined),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: paginated.items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemBuilder: (context, index) {
                    final t = paginated.items[index];
                    return DCard(
                      onTap: () => context.push('/support/${t.ticketNumber}'),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(child: Text(t.subject ?? t.ticketNumber, style: const TextStyle(fontWeight: FontWeight.w600))),
                              StatusChip(status: t.status ?? ''),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(t.ticketNumber, style: TextStyle(color: Colors.grey.shade500, fontSize: 12)),
                          const SizedBox(height: 4),
                          Text(DateFormatter.relative(t.createdAt),
                              style: TextStyle(color: Colors.grey.shade500, fontSize: 12)),
                        ],
                      ),
                    );
                  },
                ),
        ),
      ),
    );
  }
}

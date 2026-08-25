import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/date_formatter.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import 'finance_provider.dart';

class LedgerScreen extends ConsumerWidget {
  const LedgerScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ledgerAsync = ref.watch(ledgerProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Ledger')),
      body: ledgerAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load ledger.',
          onRetry: () => ref.invalidate(ledgerProvider),
        ),
        data: (paginated) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(ledgerProvider),
          child: paginated.items.isEmpty
              ? ListView(children: const [
                  SizedBox(height: 120),
                  EmptyState(message: 'No ledger entries.', icon: Icons.list_alt_outlined),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: paginated.items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final e = paginated.items[index];
                    final debit = (e['debit'] as num?) ?? 0;
                    final credit = (e['credit'] as num?) ?? 0;
                    final isCredit = credit > 0;
                    return PCard(
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('${e['description'] ?? e['account_type'] ?? '-'}',
                                    style: const TextStyle(fontWeight: FontWeight.w600)),
                                const SizedBox(height: 4),
                                Text(DateFormatter.dateTime(DateFormatter.parse(e['created_at'] as String?)),
                                    style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                              ],
                            ),
                          ),
                          Text(
                            MoneyFormatter.format(isCredit ? credit : debit, e['currency'] as String?),
                            style: TextStyle(fontWeight: FontWeight.w600, color: isCredit ? AppTheme.success : AppTheme.error),
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
}

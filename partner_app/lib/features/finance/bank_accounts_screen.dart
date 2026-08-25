import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import 'finance_provider.dart';

class BankAccountsScreen extends ConsumerWidget {
  const BankAccountsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final accountsAsync = ref.watch(bankAccountsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Bank Accounts')),
      body: accountsAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load bank accounts.',
          onRetry: () => ref.invalidate(bankAccountsProvider),
        ),
        data: (accounts) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(bankAccountsProvider),
          child: accounts.isEmpty
              ? ListView(children: const [
                  SizedBox(height: 120),
                  EmptyState(message: 'No bank accounts on file.', icon: Icons.account_balance_outlined),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: accounts.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final a = accounts[index];
                    return PCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(child: Text('${a['bank_name'] ?? '-'}', style: const TextStyle(fontWeight: FontWeight.w600))),
                              if (a['is_primary'] == true)
                                const Icon(Icons.star, size: 16, color: AppTheme.primary),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text('${a['account_holder_name'] ?? '-'}', style: const TextStyle(color: AppTheme.textSecondary)),
                          Text('${a['account_number_masked'] ?? '-'} · ${a['iban'] ?? '-'}',
                              style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13)),
                          const SizedBox(height: 6),
                          Text('Status: ${a['verification_status'] ?? '-'}'),
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

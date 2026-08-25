import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/paginated.dart';
import 'finance_repository.dart';

final financeRepositoryProvider = Provider((ref) => FinanceRepository());

final financeSummaryProvider = FutureProvider<Map<String, dynamic>>(
  (ref) => ref.read(financeRepositoryProvider).summary(),
);

final transactionsProvider = FutureProvider<Map<String, dynamic>>(
  (ref) => ref.read(financeRepositoryProvider).transactions(),
);

final ledgerProvider = FutureProvider<Paginated<Map<String, dynamic>>>(
  (ref) => ref.read(financeRepositoryProvider).ledger(),
);

final commissionRatesProvider = FutureProvider<List<Map<String, dynamic>>>(
  (ref) => ref.read(financeRepositoryProvider).commissionRates(),
);

final salesReportProvider = FutureProvider<Map<String, dynamic>>(
  (ref) => ref.read(financeRepositoryProvider).salesReport(),
);

final payoutsProvider =
    AsyncNotifierProvider<PayoutsNotifier, Paginated<Map<String, dynamic>>>(PayoutsNotifier.new);

class PayoutsNotifier extends AsyncNotifier<Paginated<Map<String, dynamic>>> {
  @override
  Future<Paginated<Map<String, dynamic>>> build() => ref.read(financeRepositoryProvider).payouts();

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => ref.read(financeRepositoryProvider).payouts());
  }
}

final payoutDetailProvider = FutureProvider.family<Map<String, dynamic>, int>(
  (ref, id) => ref.read(financeRepositoryProvider).payoutShow(id),
);

final bankAccountsProvider = FutureProvider<List<Map<String, dynamic>>>(
  (ref) => ref.read(financeRepositoryProvider).bankAccounts(),
);

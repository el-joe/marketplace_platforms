import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/claim.dart';
import '../../shared/models/paginated.dart';
import 'reports_repository.dart';

final reportsRepositoryProvider = Provider((ref) => ReportsRepository());

final ordersReportProvider =
    FutureProvider.autoDispose<Map<String, dynamic>>((ref) => ref.read(reportsRepositoryProvider).orders());

final earningsReportProvider =
    FutureProvider.autoDispose<Map<String, dynamic>>((ref) => ref.read(reportsRepositoryProvider).earnings());

final payoutsReportProvider =
    FutureProvider.autoDispose<Map<String, dynamic>>((ref) => ref.read(reportsRepositoryProvider).payouts());

final codSettlementsReportProvider =
    FutureProvider.autoDispose<Map<String, dynamic>>((ref) => ref.read(reportsRepositoryProvider).codSettlements());

final performancePeriodProvider = StateProvider.autoDispose<String>((ref) => 'month');

final performanceReportProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) {
  final period = ref.watch(performancePeriodProvider);
  return ref.read(reportsRepositoryProvider).performance(period: period);
});

final performanceTrendProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>(
    (ref) => ref.read(reportsRepositoryProvider).performanceTrend());

final claimsProvider =
    AsyncNotifierProvider.autoDispose<ClaimsNotifier, Paginated<CarrierClaim>>(ClaimsNotifier.new);

class ClaimsNotifier extends AutoDisposeAsyncNotifier<Paginated<CarrierClaim>> {
  ReportsRepository get _repository => ref.read(reportsRepositoryProvider);

  @override
  Future<Paginated<CarrierClaim>> build() => _repository.claims();

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _repository.claims());
  }
}

final claimDetailProvider =
    FutureProvider.autoDispose.family<Map<String, dynamic>, String>((ref, id) => ref.read(reportsRepositoryProvider).claimShow(id));

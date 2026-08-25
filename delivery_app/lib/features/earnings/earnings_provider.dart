import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/earning.dart';
import 'earnings_repository.dart';

final earningsRepositoryProvider = Provider((ref) => EarningsRepository());

final earningsProvider = AsyncNotifierProvider<EarningsNotifier, EarningsSummary>(EarningsNotifier.new);

class EarningsNotifier extends AsyncNotifier<EarningsSummary> {
  EarningsRepository get _repository => ref.read(earningsRepositoryProvider);

  @override
  Future<EarningsSummary> build() => _repository.getEarnings();

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _repository.getEarnings());
  }
}

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/dashboard.dart';
import 'dashboard_repository.dart';

final dashboardRepositoryProvider = Provider((ref) => DashboardRepository());

final dashboardProvider = AsyncNotifierProvider<DashboardNotifier, Dashboard>(DashboardNotifier.new);

class DashboardNotifier extends AsyncNotifier<Dashboard> {
  DashboardRepository get _repository => ref.read(dashboardRepositoryProvider);

  @override
  Future<Dashboard> build() => _repository.getDashboard();

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _repository.getDashboard());
  }
}

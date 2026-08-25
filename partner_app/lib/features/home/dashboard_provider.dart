import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'dashboard_repository.dart';

final dashboardRepositoryProvider = Provider((ref) => DashboardRepository());

final dashboardProvider = AsyncNotifierProvider<DashboardNotifier, Map<String, dynamic>>(DashboardNotifier.new);

class DashboardNotifier extends AsyncNotifier<Map<String, dynamic>> {
  DashboardRepository get _repository => ref.read(dashboardRepositoryProvider);

  @override
  Future<Map<String, dynamic>> build() => _repository.getDashboard();

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _repository.getDashboard());
  }
}

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/dashboard.dart';
import 'dashboard_repository.dart';
import 'location_tracking_provider.dart';

final dashboardRepositoryProvider = Provider((ref) => DashboardRepository());

final dashboardProvider = AsyncNotifierProvider<DashboardNotifier, Dashboard>(DashboardNotifier.new);

class DashboardNotifier extends AsyncNotifier<Dashboard> {
  DashboardRepository get _repository => ref.read(dashboardRepositoryProvider);

  @override
  Future<Dashboard> build() => _repository.getDashboard();

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _repository.getDashboard());
  }

  Future<void> startShift() async {
    await _repository.startShift();
    await ref.read(locationTrackingProvider.notifier).start();
    await refresh();
  }

  Future<void> endShift() async {
    ref.read(locationTrackingProvider.notifier).stop();
    await _repository.endShift();
    await refresh();
  }

  Future<void> setAvailability(bool isAvailable) async {
    await _repository.setAvailability(isAvailable);
    await refresh();
  }
}

final shiftStatusProvider = Provider<String?>((ref) {
  final dashboard = ref.watch(dashboardProvider);
  return dashboard.valueOrNull?.status;
});

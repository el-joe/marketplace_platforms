import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/paginated.dart';
import 'performance_repository.dart';

final performanceRepositoryProvider = Provider((ref) => PerformanceRepository());

final performanceDaysProvider = StateProvider<int>((ref) => 30);

final performanceProvider = FutureProvider<Map<String, dynamic>>(
  (ref) => ref.read(performanceRepositoryProvider).summary(days: ref.watch(performanceDaysProvider)),
);

final reviewsProvider = FutureProvider<Paginated<Map<String, dynamic>>>(
  (ref) => ref.read(performanceRepositoryProvider).reviews(),
);

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/paginated.dart';
import 'returns_repository.dart';

final returnsRepositoryProvider = Provider((ref) => ReturnsRepository());

final returnsStatusFilterProvider = StateProvider<String?>((ref) => null);

final returnsProvider =
    AsyncNotifierProvider<ReturnsNotifier, Paginated<Map<String, dynamic>>>(ReturnsNotifier.new);

class ReturnsNotifier extends AsyncNotifier<Paginated<Map<String, dynamic>>> {
  ReturnsRepository get _repository => ref.read(returnsRepositoryProvider);

  @override
  Future<Paginated<Map<String, dynamic>>> build() {
    final status = ref.watch(returnsStatusFilterProvider);
    return _repository.list(status: status);
  }

  Future<void> refresh() async {
    final status = ref.read(returnsStatusFilterProvider);
    state = await AsyncValue.guard(() => _repository.list(status: status));
  }
}

final returnDetailProvider = FutureProvider.family<Map<String, dynamic>, String>(
  (ref, returnNumber) => ref.read(returnsRepositoryProvider).show(returnNumber),
);

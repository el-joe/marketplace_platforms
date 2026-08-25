import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/paginated.dart';
import 'warranty_repository.dart';

final warrantyRepositoryProvider = Provider((ref) => WarrantyRepository());

final warrantyStatusFilterProvider = StateProvider<String?>((ref) => null);

final warrantyProvider =
    AsyncNotifierProvider<WarrantyNotifier, Paginated<Map<String, dynamic>>>(WarrantyNotifier.new);

class WarrantyNotifier extends AsyncNotifier<Paginated<Map<String, dynamic>>> {
  WarrantyRepository get _repository => ref.read(warrantyRepositoryProvider);

  @override
  Future<Paginated<Map<String, dynamic>>> build() {
    final status = ref.watch(warrantyStatusFilterProvider);
    return _repository.list(status: status);
  }

  Future<void> refresh() async {
    final status = ref.read(warrantyStatusFilterProvider);
    state = await AsyncValue.guard(() => _repository.list(status: status));
  }
}

final warrantyDetailProvider = FutureProvider.family<Map<String, dynamic>, String>(
  (ref, id) => ref.read(warrantyRepositoryProvider).show(id),
);

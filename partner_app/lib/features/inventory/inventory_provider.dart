import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/paginated.dart';
import 'inventory_repository.dart';

final inventoryRepositoryProvider = Provider((ref) => InventoryRepository());

final lowStockOnlyProvider = StateProvider<bool>((ref) => false);

final inventoryProvider =
    AsyncNotifierProvider<InventoryNotifier, Paginated<Map<String, dynamic>>>(InventoryNotifier.new);

class InventoryNotifier extends AsyncNotifier<Paginated<Map<String, dynamic>>> {
  InventoryRepository get _repository => ref.read(inventoryRepositoryProvider);

  @override
  Future<Paginated<Map<String, dynamic>>> build() {
    final lowStock = ref.watch(lowStockOnlyProvider);
    return _repository.list(lowStock: lowStock);
  }

  Future<void> refresh() async {
    final lowStock = ref.read(lowStockOnlyProvider);
    state = await AsyncValue.guard(() => _repository.list(lowStock: lowStock));
  }
}

final inventoryMovementsProvider = FutureProvider.family<Paginated<Map<String, dynamic>>, String>(
  (ref, id) => ref.read(inventoryRepositoryProvider).movements(id),
);

final transfersProvider = FutureProvider<Paginated<Map<String, dynamic>>>(
  (ref) => ref.read(inventoryRepositoryProvider).transfers(),
);

final transferDetailProvider = FutureProvider.family<Map<String, dynamic>, String>(
  (ref, transferNumber) => ref.read(inventoryRepositoryProvider).transferShow(transferNumber),
);

import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'warehouses_repository.dart';

final warehousesRepositoryProvider = Provider((ref) => WarehousesRepository());

final warehousesProvider = FutureProvider<List<Map<String, dynamic>>>(
  (ref) => ref.read(warehousesRepositoryProvider).list(),
);

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/paginated.dart';
import 'orders_repository.dart';

final ordersRepositoryProvider = Provider((ref) => OrdersRepository());

class OrdersFilter {
  final String? status;
  final String? search;
  final bool issuesOnly;

  const OrdersFilter({this.status, this.search, this.issuesOnly = false});
}

final ordersFilterProvider = StateProvider<OrdersFilter>((ref) => const OrdersFilter());

final ordersProvider =
    AsyncNotifierProvider<OrdersNotifier, Paginated<Map<String, dynamic>>>(OrdersNotifier.new);

class OrdersNotifier extends AsyncNotifier<Paginated<Map<String, dynamic>>> {
  OrdersRepository get _repository => ref.read(ordersRepositoryProvider);

  @override
  Future<Paginated<Map<String, dynamic>>> build() {
    final filter = ref.watch(ordersFilterProvider);
    return _repository.list(status: filter.status, search: filter.search, issuesOnly: filter.issuesOnly);
  }

  Future<void> refresh() async {
    final filter = ref.read(ordersFilterProvider);
    state = await AsyncValue.guard(
      () => _repository.list(status: filter.status, search: filter.search, issuesOnly: filter.issuesOnly),
    );
  }
}

final orderDetailProvider = FutureProvider.family<Map<String, dynamic>, String>(
  (ref, subOrderNumber) => ref.read(ordersRepositoryProvider).show(subOrderNumber),
);

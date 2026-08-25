import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/paginated.dart';
import 'listings_repository.dart';

final listingsRepositoryProvider = Provider((ref) => ListingsRepository());

class ListingsFilter {
  final String? status;
  final String? search;
  const ListingsFilter({this.status, this.search});
}

final listingsFilterProvider = StateProvider<ListingsFilter>((ref) => const ListingsFilter());

final listingsProvider =
    AsyncNotifierProvider<ListingsNotifier, Paginated<Map<String, dynamic>>>(ListingsNotifier.new);

class ListingsNotifier extends AsyncNotifier<Paginated<Map<String, dynamic>>> {
  ListingsRepository get _repository => ref.read(listingsRepositoryProvider);

  @override
  Future<Paginated<Map<String, dynamic>>> build() {
    final filter = ref.watch(listingsFilterProvider);
    return _repository.list(status: filter.status, search: filter.search);
  }

  Future<void> refresh() async {
    final filter = ref.read(listingsFilterProvider);
    state = await AsyncValue.guard(() => _repository.list(status: filter.status, search: filter.search));
  }
}

final listingDetailProvider = FutureProvider.family<Map<String, dynamic>, String>(
  (ref, id) => ref.read(listingsRepositoryProvider).show(id),
);

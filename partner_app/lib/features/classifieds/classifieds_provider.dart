import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/paginated.dart';
import 'classifieds_repository.dart';

final classifiedsRepositoryProvider = Provider((ref) => ClassifiedsRepository());

final classifiedsProvider =
    AsyncNotifierProvider<ClassifiedsNotifier, Paginated<Map<String, dynamic>>>(ClassifiedsNotifier.new);

class ClassifiedsNotifier extends AsyncNotifier<Paginated<Map<String, dynamic>>> {
  ClassifiedsRepository get _repository => ref.read(classifiedsRepositoryProvider);

  @override
  Future<Paginated<Map<String, dynamic>>> build() => _repository.list();

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _repository.list());
  }
}

final classifiedDetailProvider = FutureProvider.family<Map<String, dynamic>, String>(
  (ref, id) => ref.read(classifiedsRepositoryProvider).show(id),
);

final classifiedInquiriesProvider = FutureProvider.family<Paginated<Map<String, dynamic>>, String>(
  (ref, id) => ref.read(classifiedsRepositoryProvider).inquiries(id),
);

import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/paginated.dart';
import '../../shared/models/supervisor.dart';
import 'supervisors_repository.dart';

final supervisorsRepositoryProvider = Provider((ref) => SupervisorsRepository());

final supervisorsProvider = AsyncNotifierProvider<SupervisorsNotifier, Paginated<Supervisor>>(SupervisorsNotifier.new);

class SupervisorsNotifier extends AsyncNotifier<Paginated<Supervisor>> {
  SupervisorsRepository get _repository => ref.read(supervisorsRepositoryProvider);

  @override
  Future<Paginated<Supervisor>> build() => _repository.list();

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _repository.list());
  }

  Future<void> delete(String id) async {
    await _repository.delete(id);
    await refresh();
  }
}

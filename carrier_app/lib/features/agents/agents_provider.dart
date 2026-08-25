import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/agent.dart';
import '../../shared/models/paginated.dart';
import '../../shared/models/zone.dart';
import 'agents_repository.dart';

final agentsRepositoryProvider = Provider((ref) => AgentsRepository());

final zonesProvider = FutureProvider<List<Zone>>((ref) => ref.read(agentsRepositoryProvider).zones());

class AgentsFilter {
  final String? search;
  final String? status;

  const AgentsFilter({this.search, this.status});

  AgentsFilter copyWith({String? search, String? status}) =>
      AgentsFilter(search: search ?? this.search, status: status ?? this.status);
}

final agentsFilterProvider = StateProvider<AgentsFilter>((ref) => const AgentsFilter());

final agentsProvider = AsyncNotifierProvider<AgentsNotifier, Paginated<Agent>>(AgentsNotifier.new);

class AgentsNotifier extends AsyncNotifier<Paginated<Agent>> {
  AgentsRepository get _repository => ref.read(agentsRepositoryProvider);

  @override
  Future<Paginated<Agent>> build() {
    final filter = ref.watch(agentsFilterProvider);
    return _repository.list(search: filter.search, status: filter.status);
  }

  Future<void> refresh() async {
    final filter = ref.read(agentsFilterProvider);
    state = await AsyncValue.guard(() => _repository.list(search: filter.search, status: filter.status));
  }
}

final agentDetailProvider =
    AsyncNotifierProvider.family<AgentDetailNotifier, Agent, String>(AgentDetailNotifier.new);

class AgentDetailNotifier extends FamilyAsyncNotifier<Agent, String> {
  AgentsRepository get _repository => ref.read(agentsRepositoryProvider);

  @override
  Future<Agent> build(String arg) => _repository.show(arg);

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _repository.show(arg));
  }

  Future<void> assignZone(int? zoneId) async {
    await _repository.assignZone(arg, zoneId);
    await refresh();
  }

  Future<void> suspend() async {
    await _repository.suspend(arg);
    await refresh();
  }

  Future<void> activate() async {
    await _repository.activate(arg);
    await refresh();
  }

  Future<void> resetPassword(String password) => _repository.resetPassword(arg, password);
}

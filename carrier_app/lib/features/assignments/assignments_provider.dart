import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/agent.dart';
import '../../shared/models/assignment.dart';
import '../../shared/models/paginated.dart';
import 'assignments_repository.dart';

final assignmentsRepositoryProvider = Provider((ref) => AssignmentsRepository());

final availableAgentsProvider = FutureProvider<List<Agent>>((ref) => ref.read(assignmentsRepositoryProvider).availableAgents());

class AssignmentsFilter {
  final String? search;
  final String? status;
  final String? dateFrom;
  final String? dateTo;

  const AssignmentsFilter({this.search, this.status, this.dateFrom, this.dateTo});

  AssignmentsFilter copyWith({String? search, String? status, String? dateFrom, String? dateTo}) => AssignmentsFilter(
        search: search ?? this.search,
        status: status ?? this.status,
        dateFrom: dateFrom ?? this.dateFrom,
        dateTo: dateTo ?? this.dateTo,
      );
}

final assignmentsFilterProvider = StateProvider<AssignmentsFilter>((ref) => const AssignmentsFilter());

final assignmentsProvider = AsyncNotifierProvider<AssignmentsNotifier, Paginated<Assignment>>(AssignmentsNotifier.new);

class AssignmentsNotifier extends AsyncNotifier<Paginated<Assignment>> {
  AssignmentsRepository get _repository => ref.read(assignmentsRepositoryProvider);

  @override
  Future<Paginated<Assignment>> build() {
    final filter = ref.watch(assignmentsFilterProvider);
    return _repository.list(
      search: filter.search,
      status: filter.status,
      dateFrom: filter.dateFrom,
      dateTo: filter.dateTo,
    );
  }

  Future<void> refresh() async {
    final filter = ref.read(assignmentsFilterProvider);
    state = await AsyncValue.guard(() => _repository.list(
          search: filter.search,
          status: filter.status,
          dateFrom: filter.dateFrom,
          dateTo: filter.dateTo,
        ));
  }
}

final assignmentDetailProvider =
    AsyncNotifierProvider.family<AssignmentDetailNotifier, Assignment, String>(AssignmentDetailNotifier.new);

class AssignmentDetailNotifier extends FamilyAsyncNotifier<Assignment, String> {
  AssignmentsRepository get _repository => ref.read(assignmentsRepositoryProvider);

  @override
  Future<Assignment> build(String arg) => _repository.show(arg);

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _repository.show(arg));
  }

  Future<void> reassign(String newAgentId) async {
    await _repository.reassign(arg, newAgentId);
    await refresh();
  }
}

final unassignedProvider = AsyncNotifierProvider<UnassignedNotifier, Paginated<UnassignedShipment>>(UnassignedNotifier.new);

class UnassignedNotifier extends AsyncNotifier<Paginated<UnassignedShipment>> {
  AssignmentsRepository get _repository => ref.read(assignmentsRepositoryProvider);

  @override
  Future<Paginated<UnassignedShipment>> build() => _repository.unassigned();

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _repository.unassigned());
  }

  Future<void> assign(String shipmentId, String agentId) async {
    await _repository.assign(shipmentId, agentId);
    await refresh();
  }
}

import '../../core/api/api_client.dart';
import '../../shared/models/agent.dart';
import '../../shared/models/assignment.dart';
import '../../shared/models/paginated.dart';

class AssignmentsRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Paginated<Assignment>> list({
    String? search,
    String? status,
    String? dateFrom,
    String? dateTo,
    int page = 1,
  }) {
    return _client.request<Paginated<Assignment>>(
      (dio) => dio.get('/assignments', queryParameters: {
        if (search != null && search.isNotEmpty) 'search': search,
        if (status != null && status.isNotEmpty) 'status': status,
        if (dateFrom != null) 'date_from': dateFrom,
        if (dateTo != null) 'date_to': dateTo,
        'page': page,
      }),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, Assignment.fromJson),
    );
  }

  Future<Assignment> show(String id) {
    return _client.request<Assignment>(
      (dio) => dio.get('/assignments/$id'),
      parse: (data) => Assignment.fromJson((data as Map<String, dynamic>)['assignment'] as Map<String, dynamic>),
    );
  }

  Future<Assignment> reassign(String id, String newAgentId) {
    return _client.request<Assignment>(
      (dio) => dio.post('/assignments/$id/reassign', data: {'new_delivery_agent_id': newAgentId}),
      parse: (data) => Assignment.fromJson((data as Map<String, dynamic>)['assignment'] as Map<String, dynamic>),
    );
  }

  Future<Paginated<UnassignedShipment>> unassigned({int page = 1}) {
    return _client.request<Paginated<UnassignedShipment>>(
      (dio) => dio.get('/assignments/unassigned', queryParameters: {'page': page}),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, UnassignedShipment.fromJson),
    );
  }

  Future<Assignment> assign(String shipmentId, String agentId) {
    return _client.request<Assignment>(
      (dio) => dio.post('/assignments/$shipmentId/assign', data: {'agent_id': agentId}),
      parse: (data) => Assignment.fromJson((data as Map<String, dynamic>)['assignment'] as Map<String, dynamic>),
    );
  }

  /// Active agents available for assignment/reassignment. The backend
  /// doesn't return an `available_agents` list on the assignment payload, so
  /// we source candidates from the standard agents list filtered to active
  /// status.
  Future<List<Agent>> availableAgents() {
    return _client.request<List<Agent>>(
      (dio) => dio.get('/agents', queryParameters: {'status': 'active', 'per_page': 100}),
      parse: (data) => ((data as Map<String, dynamic>)['items'] as List)
          .map((e) => Agent.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}

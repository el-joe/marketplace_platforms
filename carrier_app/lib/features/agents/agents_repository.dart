import '../../core/api/api_client.dart';
import '../../shared/models/agent.dart';
import '../../shared/models/paginated.dart';
import '../../shared/models/zone.dart';

class AgentsRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Paginated<Agent>> list({String? search, String? status, int page = 1}) {
    return _client.request<Paginated<Agent>>(
      (dio) => dio.get('/agents', queryParameters: {
        if (search != null && search.isNotEmpty) 'search': search,
        if (status != null && status.isNotEmpty) 'status': status,
        'page': page,
      }),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, Agent.fromJson),
    );
  }

  Future<Agent> show(String id) {
    return _client.request<Agent>(
      (dio) => dio.get('/agents/$id'),
      parse: (data) => Agent.fromJson((data as Map<String, dynamic>)['agent'] as Map<String, dynamic>),
    );
  }

  Future<Agent> create(Map<String, dynamic> data) {
    return _client.request<Agent>(
      (dio) => dio.post('/agents', data: data),
      parse: (data) => Agent.fromJson((data as Map<String, dynamic>)['agent'] as Map<String, dynamic>),
    );
  }

  Future<Agent> update(String id, Map<String, dynamic> data) {
    return _client.request<Agent>(
      (dio) => dio.put('/agents/$id', data: data),
      parse: (data) => Agent.fromJson((data as Map<String, dynamic>)['agent'] as Map<String, dynamic>),
    );
  }

  Future<Agent> assignZone(String id, int? zoneId) {
    return _client.request<Agent>(
      (dio) => dio.patch('/agents/$id/zone', data: {'zone_id': zoneId}),
      parse: (data) => Agent.fromJson((data as Map<String, dynamic>)['agent'] as Map<String, dynamic>),
    );
  }

  Future<Agent> suspend(String id) {
    return _client.request<Agent>(
      (dio) => dio.patch('/agents/$id/suspend'),
      parse: (data) => Agent.fromJson((data as Map<String, dynamic>)['agent'] as Map<String, dynamic>),
    );
  }

  Future<Agent> activate(String id) {
    return _client.request<Agent>(
      (dio) => dio.patch('/agents/$id/activate'),
      parse: (data) => Agent.fromJson((data as Map<String, dynamic>)['agent'] as Map<String, dynamic>),
    );
  }

  Future<void> resetPassword(String id, String password) {
    return _client.request<void>(
      (dio) => dio.post('/agents/$id/reset-password', data: {'password': password}),
      parse: (_) {},
    );
  }

  Future<List<Zone>> zones() {
    return _client.request<List<Zone>>(
      (dio) => dio.get('/zones'),
      parse: (data) =>
          ((data as Map<String, dynamic>)['zones'] as List).map((e) => Zone.fromJson(e as Map<String, dynamic>)).toList(),
    );
  }
}

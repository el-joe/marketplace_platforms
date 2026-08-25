import '../../core/api/api_client.dart';
import '../../shared/models/paginated.dart';
import '../../shared/models/supervisor.dart';

class SupervisorsRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Paginated<Supervisor>> list({int page = 1}) {
    return _client.request<Paginated<Supervisor>>(
      (dio) => dio.get('/supervisors', queryParameters: {'page': page}),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, Supervisor.fromJson),
    );
  }

  Future<Supervisor> create(Map<String, dynamic> data) {
    return _client.request<Supervisor>(
      (dio) => dio.post('/supervisors', data: data),
      parse: (data) => Supervisor.fromJson((data as Map<String, dynamic>)['supervisor'] as Map<String, dynamic>),
    );
  }

  Future<Supervisor> update(String id, Map<String, dynamic> data) {
    return _client.request<Supervisor>(
      (dio) => dio.put('/supervisors/$id', data: data),
      parse: (data) => Supervisor.fromJson((data as Map<String, dynamic>)['supervisor'] as Map<String, dynamic>),
    );
  }

  Future<void> delete(String id) {
    return _client.request<void>((dio) => dio.delete('/supervisors/$id'), parse: (_) {});
  }
}

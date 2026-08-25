import '../../core/api/api_client.dart';
import '../../shared/models/paginated.dart';

class ClassifiedsRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Paginated<Map<String, dynamic>>> list({String? status, String? search}) {
    return _client.request<Paginated<Map<String, dynamic>>>(
      (dio) => dio.get('/classifieds', queryParameters: {
        if (status != null && status.isNotEmpty) 'status': status,
        if (search != null && search.isNotEmpty) 'search': search,
      }),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, (json) => json),
    );
  }

  Future<Map<String, dynamic>> show(String id) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/classifieds/$id'),
      parse: (data) => data as Map<String, dynamic>,
    );
  }

  Future<Paginated<Map<String, dynamic>>> inquiries(String id) {
    return _client.request<Paginated<Map<String, dynamic>>>(
      (dio) => dio.get('/classifieds/$id/inquiries'),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, (json) => json),
    );
  }
}

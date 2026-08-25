import '../../core/api/api_client.dart';
import '../../shared/models/paginated.dart';

class PerformanceRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Map<String, dynamic>> summary({int days = 30}) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/performance', queryParameters: {'days': days}),
      parse: (data) => data as Map<String, dynamic>,
    );
  }

  Future<Paginated<Map<String, dynamic>>> reviews({int? rating}) {
    return _client.request<Paginated<Map<String, dynamic>>>(
      (dio) => dio.get('/performance/reviews', queryParameters: {if (rating != null) 'rating': rating}),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, (json) => json),
    );
  }
}

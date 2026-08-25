import '../../core/api/api_client.dart';
import '../../shared/models/paginated.dart';

class OrdersRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Paginated<Map<String, dynamic>>> list({String? status, String? search, bool issuesOnly = false}) {
    return _client.request<Paginated<Map<String, dynamic>>>(
      (dio) => dio.get('/orders', queryParameters: {
        if (status != null && status.isNotEmpty) 'status': status,
        if (search != null && search.isNotEmpty) 'search': search,
        if (issuesOnly) 'issues': true,
      }),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, (json) => json),
    );
  }

  Future<Map<String, dynamic>> show(String subOrderNumber) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/orders/$subOrderNumber'),
      parse: (data) => data as Map<String, dynamic>,
    );
  }
}

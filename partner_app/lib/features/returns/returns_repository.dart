import '../../core/api/api_client.dart';
import '../../shared/models/paginated.dart';

class ReturnsRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Paginated<Map<String, dynamic>>> list({String? status}) {
    return _client.request<Paginated<Map<String, dynamic>>>(
      (dio) => dio.get('/returns', queryParameters: {if (status != null && status.isNotEmpty) 'status': status}),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, (json) => json),
    );
  }

  Future<Map<String, dynamic>> show(String returnNumber) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/returns/$returnNumber'),
      parse: (data) => data as Map<String, dynamic>,
    );
  }
}

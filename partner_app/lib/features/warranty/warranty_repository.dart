import '../../core/api/api_client.dart';
import '../../shared/models/paginated.dart';

class WarrantyRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Paginated<Map<String, dynamic>>> list({String? status}) {
    return _client.request<Paginated<Map<String, dynamic>>>(
      (dio) => dio.get('/warranty-claims', queryParameters: {if (status != null && status.isNotEmpty) 'status': status}),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, (json) => json),
    );
  }

  Future<Map<String, dynamic>> show(String id) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/warranty-claims/$id'),
      parse: (data) => data as Map<String, dynamic>,
    );
  }
}

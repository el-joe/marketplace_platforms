import '../../core/api/api_client.dart';

class WarehousesRepository {
  final ApiClient _client = ApiClient.instance;

  Future<List<Map<String, dynamic>>> list() {
    return _client.request<List<Map<String, dynamic>>>(
      (dio) => dio.get('/warehouses'),
      parse: (data) => (data as List).cast<Map<String, dynamic>>(),
    );
  }
}

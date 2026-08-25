import '../../core/api/api_client.dart';
import '../../shared/models/paginated.dart';

class InventoryRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Paginated<Map<String, dynamic>>> list({int? warehouseId, bool lowStock = false}) {
    return _client.request<Paginated<Map<String, dynamic>>>(
      (dio) => dio.get('/inventory', queryParameters: {
        if (warehouseId != null) 'warehouse_id': warehouseId,
        if (lowStock) 'low_stock': true,
      }),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, (json) => json),
    );
  }

  Future<Paginated<Map<String, dynamic>>> movements(String id) {
    return _client.request<Paginated<Map<String, dynamic>>>(
      (dio) => dio.get('/inventory/$id/movements'),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, (json) => json),
    );
  }

  Future<Paginated<Map<String, dynamic>>> transfers() {
    return _client.request<Paginated<Map<String, dynamic>>>(
      (dio) => dio.get('/inventory/transfers'),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, (json) => json),
    );
  }

  Future<Map<String, dynamic>> transferShow(String transferNumber) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/inventory/transfers/$transferNumber'),
      parse: (data) => data as Map<String, dynamic>,
    );
  }
}

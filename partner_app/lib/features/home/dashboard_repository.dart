import '../../core/api/api_client.dart';

class DashboardRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Map<String, dynamic>> getDashboard() {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/dashboard'),
      parse: (data) => data as Map<String, dynamic>,
    );
  }
}

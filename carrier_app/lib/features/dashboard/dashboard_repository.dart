import '../../core/api/api_client.dart';
import '../../shared/models/dashboard.dart';

class DashboardRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Dashboard> getDashboard() {
    return _client.request<Dashboard>(
      (dio) => dio.get('/dashboard'),
      parse: (data) => Dashboard.fromJson(data as Map<String, dynamic>),
    );
  }
}

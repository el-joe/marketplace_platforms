import '../../core/api/api_client.dart';

class ProfileRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Map<String, dynamic>> show() {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/profile'),
      parse: (data) => data as Map<String, dynamic>,
    );
  }

  Future<List<Map<String, dynamic>>> documents() {
    return _client.request<List<Map<String, dynamic>>>(
      (dio) => dio.get('/profile/documents'),
      parse: (data) => (data as List).cast<Map<String, dynamic>>(),
    );
  }
}

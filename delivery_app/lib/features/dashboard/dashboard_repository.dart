import '../../core/api/api_client.dart';
import '../../shared/models/dashboard.dart';
import '../../shared/models/shift.dart';

class DashboardRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Dashboard> getDashboard() {
    return _client.request<Dashboard>(
      (dio) => dio.get('/dashboard'),
      parse: (data) => Dashboard.fromJson(data as Map<String, dynamic>),
    );
  }

  Future<Shift> startShift() {
    return _client.request<Shift>(
      (dio) => dio.post('/shift/start'),
      parse: (data) => Shift.fromJson((data as Map<String, dynamic>)['shift'] as Map<String, dynamic>),
    );
  }

  Future<void> endShift() {
    return _client.request<void>((dio) => dio.post('/shift/end'), parse: (_) {});
  }

  Future<bool> setAvailability(bool isAvailable) {
    return _client.request<bool>(
      (dio) => dio.put('/shift/availability', data: {'is_available': isAvailable}),
      parse: (data) => (data as Map<String, dynamic>)['is_available'] as bool,
    );
  }

  Future<void> updateLocation(double latitude, double longitude) {
    return _client.request<void>(
      (dio) => dio.put('/location', data: {'latitude': latitude, 'longitude': longitude}),
      parse: (_) {},
    );
  }
}

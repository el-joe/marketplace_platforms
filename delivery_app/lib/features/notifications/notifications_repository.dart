import '../../core/api/api_client.dart';
import '../../shared/models/notification.dart';
import '../../shared/models/paginated.dart';

class NotificationsRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Paginated<AppNotification>> list({bool unreadOnly = false}) {
    return _client.request<Paginated<AppNotification>>(
      (dio) => dio.get('/notifications', queryParameters: {if (unreadOnly) 'unread_only': true}),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, AppNotification.fromJson),
    );
  }

  Future<int> unreadCount() {
    return _client.request<int>(
      (dio) => dio.get('/notifications/unread-count'),
      parse: (data) => (data as Map<String, dynamic>)['unread_count'] as int,
    );
  }

  Future<void> markRead(String id) {
    return _client.request<void>((dio) => dio.put('/notifications/$id/read'), parse: (_) {});
  }

  Future<void> markAllRead() {
    return _client.request<void>((dio) => dio.put('/notifications/read-all'), parse: (_) {});
  }
}

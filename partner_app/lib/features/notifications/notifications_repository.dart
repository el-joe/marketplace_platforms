import '../../core/api/api_client.dart';
import '../../shared/models/notification.dart';
import '../../shared/models/paginated.dart';

class NotificationsRepository {
  final ApiClient _client = ApiClient.instance;

  /// `GET /notifications` — unlike most list endpoints, `meta` here is a
  /// sibling of `data` (not nested inside it): `{ success, data: [...],
  /// meta: {...} }`. `requestEnvelope` gives us the whole body so we can
  /// stitch both together into the shared [Paginated] shape.
  Future<Paginated<AppNotification>> list() {
    return _client.requestEnvelope<Paginated<AppNotification>>(
      (dio) => dio.get('/notifications'),
      parse: (body) => Paginated.fromJson(
        {'items': body['data'], 'meta': body['meta']},
        AppNotification.fromJson,
      ),
    );
  }

  /// `GET /notifications/unread-count` → `{ data: { count } }`.
  Future<int> unreadCount() {
    return _client.request<int>(
      (dio) => dio.get('/notifications/unread-count'),
      parse: (data) => (data as Map<String, dynamic>)['count'] as int? ?? 0,
    );
  }

  Future<void> markRead(String id) {
    return _client.request<void>((dio) => dio.put('/notifications/$id/read'), parse: (_) {});
  }

  Future<void> markAllRead() {
    return _client.request<void>((dio) => dio.put('/notifications/read-all'), parse: (_) {});
  }
}

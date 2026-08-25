import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/notification.dart';
import '../../shared/models/paginated.dart';
import 'notifications_repository.dart';

final notificationsRepositoryProvider = Provider((ref) => NotificationsRepository());

final unreadCountProvider = FutureProvider<int>((ref) => ref.read(notificationsRepositoryProvider).unreadCount());

final notificationsProvider =
    AsyncNotifierProvider<NotificationsNotifier, Paginated<AppNotification>>(NotificationsNotifier.new);

class NotificationsNotifier extends AsyncNotifier<Paginated<AppNotification>> {
  NotificationsRepository get _repository => ref.read(notificationsRepositoryProvider);

  @override
  Future<Paginated<AppNotification>> build() => _repository.list();

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _repository.list());
    ref.invalidate(unreadCountProvider);
  }

  Future<void> markRead(String id) async {
    await _repository.markRead(id);
    await refresh();
  }

  Future<void> markAllRead() async {
    await _repository.markAllRead();
    await refresh();
  }
}

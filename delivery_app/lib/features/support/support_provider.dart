import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/paginated.dart';
import '../../shared/models/support_ticket.dart';
import 'support_repository.dart';

final supportRepositoryProvider = Provider((ref) => SupportRepository());

final ticketsProvider =
    AsyncNotifierProvider<TicketsNotifier, Paginated<SupportTicket>>(TicketsNotifier.new);

class TicketsNotifier extends AsyncNotifier<Paginated<SupportTicket>> {
  SupportRepository get _repository => ref.read(supportRepositoryProvider);

  @override
  Future<Paginated<SupportTicket>> build() => _repository.list();

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _repository.list());
  }
}

final ticketDetailProvider =
    AsyncNotifierProvider.family<TicketDetailNotifier, SupportTicket, String>(TicketDetailNotifier.new);

class TicketDetailNotifier extends FamilyAsyncNotifier<SupportTicket, String> {
  SupportRepository get _repository => ref.read(supportRepositoryProvider);

  @override
  Future<SupportTicket> build(String arg) => _repository.show(arg);

  Future<void> sendMessage(String message) async {
    await _repository.addMessage(arg, message);
    state = await AsyncValue.guard(() => _repository.show(arg));
  }

  Future<void> rate({required int rating, String? comment}) async {
    final updated = await _repository.rate(arg, rating: rating, comment: comment);
    state = AsyncValue.data(updated);
  }
}

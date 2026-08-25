import '../../core/api/api_client.dart';
import '../../shared/models/paginated.dart';
import '../../shared/models/support_ticket.dart';

class SupportRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Paginated<SupportTicket>> list() {
    return _client.request<Paginated<SupportTicket>>(
      (dio) => dio.get('/support/tickets'),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, SupportTicket.fromJson),
    );
  }

  Future<SupportTicket> create({
    required String category,
    required String priority,
    required String subject,
    required String message,
    int? relatedAssignmentId,
  }) {
    return _client.request<SupportTicket>(
      (dio) => dio.post('/support/tickets', data: {
        'category': category,
        'priority': priority,
        'subject': subject,
        'message': message,
        if (relatedAssignmentId != null) 'assignment_id': relatedAssignmentId,
      }),
      parse: (data) => SupportTicket.fromJson(data as Map<String, dynamic>),
    );
  }

  Future<SupportTicket> show(String ticketNumber) {
    return _client.request<SupportTicket>(
      (dio) => dio.get('/support/tickets/$ticketNumber'),
      parse: (data) => SupportTicket.fromJson(data as Map<String, dynamic>),
    );
  }

  Future<TicketMessage> addMessage(String ticketNumber, String message) {
    return _client.request<TicketMessage>(
      (dio) => dio.post('/support/tickets/$ticketNumber/messages', data: {'message': message}),
      parse: (data) => TicketMessage.fromJson(data as Map<String, dynamic>),
    );
  }

  Future<SupportTicket> rate(String ticketNumber, {required int rating, String? comment}) {
    return _client.request<SupportTicket>(
      (dio) => dio.put('/support/tickets/$ticketNumber/rate', data: {
        'satisfaction_rating': rating,
        if (comment != null) 'satisfaction_comment': comment,
      }),
      parse: (data) => SupportTicket.fromJson(data as Map<String, dynamic>),
    );
  }
}

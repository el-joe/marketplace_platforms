class TicketMessage {
  final int id;
  final String? message;
  final DateTime? createdAt;

  TicketMessage({required this.id, this.message, this.createdAt});

  factory TicketMessage.fromJson(Map<String, dynamic> json) => TicketMessage(
        id: (json['id'] as num).toInt(),
        message: json['message'] as String?,
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at']) : null,
      );
}

class SupportTicket {
  final int id;
  final String ticketNumber;
  final String? category;
  final String? priority;
  final String? status;
  final String? subject;
  final int? relatedAssignmentId;
  final DateTime? createdAt;
  final DateTime? resolvedAt;
  final int? satisfactionRating;
  final String? satisfactionComment;
  final List<TicketMessage> messages;

  SupportTicket({
    required this.id,
    required this.ticketNumber,
    this.category,
    this.priority,
    this.status,
    this.subject,
    this.relatedAssignmentId,
    this.createdAt,
    this.resolvedAt,
    this.satisfactionRating,
    this.satisfactionComment,
    this.messages = const [],
  });

  factory SupportTicket.fromJson(Map<String, dynamic> json) => SupportTicket(
        id: (json['id'] as num).toInt(),
        ticketNumber: json['ticket_number'] as String? ?? '',
        category: json['category'] as String?,
        priority: json['priority'] as String?,
        status: json['status'] as String?,
        subject: json['subject'] as String?,
        relatedAssignmentId: (json['related_assignment_id'] as num?)?.toInt(),
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at']) : null,
        resolvedAt: json['resolved_at'] != null ? DateTime.tryParse(json['resolved_at']) : null,
        satisfactionRating: (json['satisfaction_rating'] as num?)?.toInt(),
        satisfactionComment: json['satisfaction_comment'] as String?,
        messages: (json['messages'] as List? ?? [])
            .map((e) => TicketMessage.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

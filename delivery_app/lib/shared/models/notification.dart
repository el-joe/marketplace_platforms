class AppNotification {
  final String id;
  final String? type;
  final String? typeShort;
  final Map<String, dynamic> data;
  final DateTime? readAt;
  final DateTime? createdAt;

  AppNotification({
    required this.id,
    this.type,
    this.typeShort,
    this.data = const {},
    this.readAt,
    this.createdAt,
  });

  bool get isRead => readAt != null;

  factory AppNotification.fromJson(Map<String, dynamic> json) => AppNotification(
        id: json['id'].toString(),
        type: json['type'] as String?,
        typeShort: json['type_short'] as String?,
        data: (json['data'] as Map?)?.cast<String, dynamic>() ?? {},
        readAt: json['read_at'] != null ? DateTime.tryParse(json['read_at']) : null,
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at']) : null,
      );

  String get title => data['title'] as String? ?? typeShort ?? 'Notification';
  String get body => data['message'] as String? ?? data['body'] as String? ?? '';
}

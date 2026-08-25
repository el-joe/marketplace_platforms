class AgentDocument {
  final int id;
  final String? documentType;
  final String? status;
  final String? expiresAt;
  final String? rejectionReason;
  final DateTime? verifiedAt;
  final DateTime? updatedAt;

  AgentDocument({
    required this.id,
    this.documentType,
    this.status,
    this.expiresAt,
    this.rejectionReason,
    this.verifiedAt,
    this.updatedAt,
  });

  factory AgentDocument.fromJson(Map<String, dynamic> json) => AgentDocument(
        id: (json['id'] as num).toInt(),
        documentType: json['document_type'] as String?,
        status: json['status'] as String?,
        expiresAt: json['expires_at'] as String?,
        rejectionReason: json['rejection_reason'] as String?,
        verifiedAt: json['verified_at'] != null ? DateTime.tryParse(json['verified_at']) : null,
        updatedAt: json['updated_at'] != null ? DateTime.tryParse(json['updated_at']) : null,
      );
}

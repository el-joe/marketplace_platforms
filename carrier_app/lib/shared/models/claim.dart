class ClaimRef {
  final String id;
  final String? name;

  ClaimRef({required this.id, this.name});

  factory ClaimRef.fromJson(Map<String, dynamic> json) => ClaimRef(id: json['id'].toString(), name: json['name'] as String?);
}

class ClaimShipmentRef {
  final String id;
  final String? trackingNumber;

  ClaimShipmentRef({required this.id, this.trackingNumber});

  factory ClaimShipmentRef.fromJson(Map<String, dynamic> json) =>
      ClaimShipmentRef(id: json['id'].toString(), trackingNumber: json['tracking_number'] as String?);
}

class CarrierClaim {
  final String id;
  final String? claimNumber;
  final String? claimType;
  final String status;
  final int? claimedAmount;
  final int? compensatedAmount;
  final ClaimRef? deliveryAgent;
  final ClaimShipmentRef? shipment;
  final DateTime? createdAt;

  // Detailed
  final String? description;
  final List<dynamic>? evidenceFiles;
  final String? resolutionNotes;
  final DateTime? resolvedAt;

  CarrierClaim({
    required this.id,
    this.claimNumber,
    this.claimType,
    required this.status,
    this.claimedAmount,
    this.compensatedAmount,
    this.deliveryAgent,
    this.shipment,
    this.createdAt,
    this.description,
    this.evidenceFiles,
    this.resolutionNotes,
    this.resolvedAt,
  });

  factory CarrierClaim.fromJson(Map<String, dynamic> json) => CarrierClaim(
        id: json['id'].toString(),
        claimNumber: json['claim_number'] as String?,
        claimType: json['claim_type'] as String?,
        status: json['status'] as String? ?? '',
        claimedAmount: (json['claimed_amount'] as num?)?.toInt(),
        compensatedAmount: (json['compensated_amount'] as num?)?.toInt(),
        deliveryAgent: json['delivery_agent'] != null ? ClaimRef.fromJson(json['delivery_agent'] as Map<String, dynamic>) : null,
        shipment: json['shipment'] != null ? ClaimShipmentRef.fromJson(json['shipment'] as Map<String, dynamic>) : null,
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at']) : null,
        description: json['description'] as String?,
        evidenceFiles: json['evidence_files'] as List?,
        resolutionNotes: json['resolution_notes'] as String?,
        resolvedAt: json['resolved_at'] != null ? DateTime.tryParse(json['resolved_at']) : null,
      );
}

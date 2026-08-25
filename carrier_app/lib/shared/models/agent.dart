class AgentZoneRef {
  final int id;
  final String? name;

  AgentZoneRef({required this.id, this.name});

  factory AgentZoneRef.fromJson(Map<String, dynamic> json) =>
      AgentZoneRef(id: (json['id'] as num).toInt(), name: json['name'] as String?);
}

class Agent {
  final String id;
  final String name;
  final String? email;
  final String? phone;
  final String status;
  final String? vehicleType;
  final String? vehiclePlate;
  final bool isAvailable;
  final double? ratingAvg;
  final AgentZoneRef? zone;
  final int? assignmentsCount;
  final DateTime? createdAt;

  // Detailed fields (present on show()).
  final String? nationalId;
  final String? emergencyContactName;
  final String? emergencyContactPhone;
  final int? totalDeliveries;

  Agent({
    required this.id,
    required this.name,
    this.email,
    this.phone,
    required this.status,
    this.vehicleType,
    this.vehiclePlate,
    required this.isAvailable,
    this.ratingAvg,
    this.zone,
    this.assignmentsCount,
    this.createdAt,
    this.nationalId,
    this.emergencyContactName,
    this.emergencyContactPhone,
    this.totalDeliveries,
  });

  factory Agent.fromJson(Map<String, dynamic> json) => Agent(
        id: json['id'].toString(),
        name: json['name'] as String? ?? '',
        email: json['email'] as String?,
        phone: json['phone'] as String?,
        status: json['status'] as String? ?? '',
        vehicleType: json['vehicle_type'] as String?,
        vehiclePlate: json['vehicle_plate'] as String?,
        isAvailable: json['is_available'] as bool? ?? false,
        ratingAvg: (json['rating_avg'] as num?)?.toDouble(),
        zone: json['zone'] != null ? AgentZoneRef.fromJson(json['zone'] as Map<String, dynamic>) : null,
        assignmentsCount: (json['assignments_count'] as num?)?.toInt(),
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at']) : null,
        nationalId: json['national_id'] as String?,
        emergencyContactName: json['emergency_contact_name'] as String?,
        emergencyContactPhone: json['emergency_contact_phone'] as String?,
        totalDeliveries: (json['total_deliveries'] as num?)?.toInt(),
      );
}

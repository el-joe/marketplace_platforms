class TodayStats {
  final int deliveriesToday;
  final int earningsToday;
  final DateTime? shiftStartedAt;
  final String currency;

  TodayStats({
    required this.deliveriesToday,
    required this.earningsToday,
    required this.shiftStartedAt,
    required this.currency,
  });

  factory TodayStats.fromJson(Map<String, dynamic> json) => TodayStats(
        deliveriesToday: (json['deliveries_today'] as num?)?.toInt() ?? 0,
        earningsToday: (json['earnings_today'] as num?)?.toInt() ?? 0,
        shiftStartedAt:
            json['shift_started_at'] != null ? DateTime.tryParse(json['shift_started_at']) : null,
        currency: json['currency'] as String? ?? 'AED',
      );
}

class AgentLocation {
  final double latitude;
  final double longitude;
  final DateTime? recordedAt;

  AgentLocation({required this.latitude, required this.longitude, this.recordedAt});

  factory AgentLocation.fromJson(Map<String, dynamic> json) => AgentLocation(
        latitude: (json['latitude'] as num).toDouble(),
        longitude: (json['longitude'] as num).toDouble(),
        recordedAt: json['recorded_at'] != null ? DateTime.tryParse(json['recorded_at']) : null,
      );
}

class Agent {
  final int id;
  final String name;
  final String? email;
  final String? phone;
  final String status;
  final String? agentType;
  final String? vehicleType;
  final String? vehiclePlate;
  final bool isAvailable;
  final bool isOnShift;
  final double? ratingAvg;
  final int totalDeliveries;
  final int? perDeliveryFee;
  final String currency;
  final AgentLocation? currentLocation;
  final TodayStats? todayStats;

  Agent({
    required this.id,
    required this.name,
    this.email,
    this.phone,
    required this.status,
    this.agentType,
    this.vehicleType,
    this.vehiclePlate,
    required this.isAvailable,
    required this.isOnShift,
    this.ratingAvg,
    required this.totalDeliveries,
    this.perDeliveryFee,
    required this.currency,
    this.currentLocation,
    this.todayStats,
  });

  factory Agent.fromJson(Map<String, dynamic> json) => Agent(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String? ?? '',
        email: json['email'] as String?,
        phone: json['phone'] as String?,
        status: json['status'] as String? ?? '',
        agentType: json['agent_type'] as String?,
        vehicleType: json['vehicle_type'] as String?,
        vehiclePlate: json['vehicle_plate'] as String?,
        isAvailable: json['is_available'] as bool? ?? false,
        isOnShift: json['is_on_shift'] as bool? ?? false,
        ratingAvg: (json['rating_avg'] as num?)?.toDouble(),
        totalDeliveries: (json['total_deliveries'] as num?)?.toInt() ?? 0,
        perDeliveryFee: (json['per_delivery_fee'] as num?)?.toInt(),
        currency: json['currency'] as String? ?? 'AED',
        currentLocation: json['current_location'] != null
            ? AgentLocation.fromJson(json['current_location'] as Map<String, dynamic>)
            : null,
        todayStats:
            json['today_stats'] != null ? TodayStats.fromJson(json['today_stats'] as Map<String, dynamic>) : null,
      );
}

class Shift {
  final int id;
  final String? shiftDate;
  final int? zoneId;
  final String status;
  final DateTime? actualStart;
  final DateTime? actualEnd;
  final int? durationMinutes;
  final int totalDeliveries;
  final int totalEarnings;

  Shift({
    required this.id,
    this.shiftDate,
    this.zoneId,
    required this.status,
    this.actualStart,
    this.actualEnd,
    this.durationMinutes,
    required this.totalDeliveries,
    required this.totalEarnings,
  });

  factory Shift.fromJson(Map<String, dynamic> json) => Shift(
        id: (json['id'] as num).toInt(),
        shiftDate: json['shift_date'] as String?,
        zoneId: (json['zone_id'] as num?)?.toInt(),
        status: json['status'] as String? ?? '',
        actualStart: json['actual_start'] != null ? DateTime.tryParse(json['actual_start']) : null,
        actualEnd: json['actual_end'] != null ? DateTime.tryParse(json['actual_end']) : null,
        durationMinutes: (json['duration_minutes'] as num?)?.toInt(),
        totalDeliveries: (json['total_deliveries'] as num?)?.toInt() ?? 0,
        totalEarnings: (json['total_earnings'] as num?)?.toInt() ?? 0,
      );
}

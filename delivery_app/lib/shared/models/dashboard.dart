import 'assignment.dart';

class DashboardStats {
  final int total;
  final int completed;
  final int failed;
  final int active;

  DashboardStats({required this.total, required this.completed, required this.failed, required this.active});

  factory DashboardStats.fromJson(Map<String, dynamic> json) => DashboardStats(
        total: (json['total'] as num?)?.toInt() ?? 0,
        completed: (json['completed'] as num?)?.toInt() ?? 0,
        failed: (json['failed'] as num?)?.toInt() ?? 0,
        active: (json['active'] as num?)?.toInt() ?? 0,
      );
}

class DashboardZone {
  final int id;
  final String? name;
  final String? code;
  final int? baseDeliveryFee;
  final int? codFee;

  DashboardZone({required this.id, this.name, this.code, this.baseDeliveryFee, this.codFee});

  factory DashboardZone.fromJson(Map<String, dynamic> json) => DashboardZone(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String?,
        code: json['code'] as String?,
        baseDeliveryFee: (json['base_delivery_fee'] as num?)?.toInt(),
        codFee: (json['cod_fee'] as num?)?.toInt(),
      );
}

class Dashboard {
  final DashboardStats stats;
  final int earningsToday;
  final String currency;
  final bool isAvailable;
  final String status;
  final List<Assignment> pendingAssignments;
  final DashboardZone? zone;

  Dashboard({
    required this.stats,
    required this.earningsToday,
    required this.currency,
    required this.isAvailable,
    required this.status,
    required this.pendingAssignments,
    this.zone,
  });

  factory Dashboard.fromJson(Map<String, dynamic> json) => Dashboard(
        stats: DashboardStats.fromJson(json['stats'] as Map<String, dynamic>? ?? {}),
        earningsToday: (json['earnings_today'] as num?)?.toInt() ?? 0,
        currency: json['currency'] as String? ?? 'AED',
        isAvailable: json['is_available'] as bool? ?? false,
        status: json['status'] as String? ?? '',
        pendingAssignments: (json['pending_assignments'] as List? ?? [])
            .map((e) => Assignment.fromJson(e as Map<String, dynamic>))
            .toList(),
        zone: json['zone'] != null ? DashboardZone.fromJson(json['zone'] as Map<String, dynamic>) : null,
      );
}

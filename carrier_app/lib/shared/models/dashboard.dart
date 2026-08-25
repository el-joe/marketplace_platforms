class DashboardCompany {
  final String id;
  final String name;
  final String? status;

  DashboardCompany({required this.id, required this.name, this.status});

  factory DashboardCompany.fromJson(Map<String, dynamic> json) => DashboardCompany(
        id: json['id'].toString(),
        name: json['name'] as String? ?? '',
        status: json['status'] as String?,
      );
}

class AgentsCount {
  final int total;
  final int onShift;
  final int available;

  AgentsCount({required this.total, required this.onShift, required this.available});

  factory AgentsCount.fromJson(Map<String, dynamic> json) => AgentsCount(
        total: (json['total'] as num?)?.toInt() ?? 0,
        onShift: (json['on_shift'] as num?)?.toInt() ?? 0,
        available: (json['available'] as num?)?.toInt() ?? 0,
      );
}

class TodayAssignments {
  final int total;
  final int completed;
  final int failed;
  final int inTransit;

  TodayAssignments({required this.total, required this.completed, required this.failed, required this.inTransit});

  factory TodayAssignments.fromJson(Map<String, dynamic> json) => TodayAssignments(
        total: (json['total'] as num?)?.toInt() ?? 0,
        completed: (json['completed'] as num?)?.toInt() ?? 0,
        failed: (json['failed'] as num?)?.toInt() ?? 0,
        inTransit: (json['in_transit'] as num?)?.toInt() ?? 0,
      );
}

class RecentAssignment {
  final String id;
  final String? subOrderNumber;
  final String? agentName;
  final String status;
  final DateTime? assignedAt;

  RecentAssignment({
    required this.id,
    this.subOrderNumber,
    this.agentName,
    required this.status,
    this.assignedAt,
  });

  factory RecentAssignment.fromJson(Map<String, dynamic> json) => RecentAssignment(
        id: json['id'].toString(),
        subOrderNumber: json['sub_order_number'] as String?,
        agentName: json['agent_name'] as String?,
        status: json['status'] as String? ?? '',
        assignedAt: json['assigned_at'] != null ? DateTime.tryParse(json['assigned_at']) : null,
      );
}

class ServedAreaSummary {
  final int countriesCount;
  final int citiesCount;

  ServedAreaSummary({required this.countriesCount, required this.citiesCount});

  factory ServedAreaSummary.fromJson(Map<String, dynamic> json) => ServedAreaSummary(
        countriesCount: (json['countries_count'] as num?)?.toInt() ?? 0,
        citiesCount: (json['cities_count'] as num?)?.toInt() ?? 0,
      );
}

class Dashboard {
  final DashboardCompany company;
  final List<String> permissions;
  final AgentsCount agentsCount;
  final TodayAssignments todayAssignments;
  final String companyStatus;
  final ServedAreaSummary servedAreaSummary;
  final List<RecentAssignment> recentAssignments;

  Dashboard({
    required this.company,
    required this.permissions,
    required this.agentsCount,
    required this.todayAssignments,
    required this.companyStatus,
    required this.servedAreaSummary,
    required this.recentAssignments,
  });

  factory Dashboard.fromJson(Map<String, dynamic> json) => Dashboard(
        company: DashboardCompany.fromJson(json['company'] as Map<String, dynamic>? ?? {}),
        permissions: (json['permissions'] as List? ?? []).map((e) => e.toString()).toList(),
        agentsCount: AgentsCount.fromJson(json['agents_count'] as Map<String, dynamic>? ?? {}),
        todayAssignments: TodayAssignments.fromJson(json['today_assignments'] as Map<String, dynamic>? ?? {}),
        companyStatus: json['company_status'] as String? ?? '',
        servedAreaSummary: ServedAreaSummary.fromJson(json['served_area_summary'] as Map<String, dynamic>? ?? {}),
        recentAssignments: (json['recent_assignments'] as List? ?? [])
            .map((e) => RecentAssignment.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

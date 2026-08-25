class Zone {
  final int id;
  final String name;
  final String? code;
  final int? maxActiveAgents;
  final int activeAgents;
  final bool atCapacity;

  Zone({
    required this.id,
    required this.name,
    this.code,
    this.maxActiveAgents,
    this.activeAgents = 0,
    this.atCapacity = false,
  });

  factory Zone.fromJson(Map<String, dynamic> json) => Zone(
        id: (json['id'] as num).toInt(),
        name: json['name'] as String? ?? '',
        code: json['code'] as String?,
        maxActiveAgents: (json['max_active_agents'] as num?)?.toInt(),
        activeAgents: (json['active_agents'] as num?)?.toInt() ?? 0,
        atCapacity: json['at_capacity'] as bool? ?? false,
      );
}

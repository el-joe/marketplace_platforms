class SupervisorCompany {
  final String id;
  final String name;
  final String? status;

  SupervisorCompany({required this.id, required this.name, this.status});

  factory SupervisorCompany.fromJson(Map<String, dynamic> json) => SupervisorCompany(
        id: json['id'].toString(),
        name: json['name'] as String? ?? '',
        status: json['status'] as String?,
      );
}

class Supervisor {
  final String id;
  final String name;
  final String email;
  final String? phone;
  final bool isActive;
  final List<String> permissions;
  final bool isOwner;
  final bool receivesAllNotifications;
  final SupervisorCompany? company;
  final DateTime? createdAt;

  Supervisor({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    required this.isActive,
    required this.permissions,
    required this.isOwner,
    this.receivesAllNotifications = false,
    this.company,
    this.createdAt,
  });

  bool hasPermission(String permission) => permissions.contains(permission);

  factory Supervisor.fromJson(Map<String, dynamic> json) => Supervisor(
        id: json['id'].toString(),
        name: json['name'] as String? ?? '',
        email: json['email'] as String? ?? '',
        phone: json['phone'] as String?,
        isActive: json['is_active'] as bool? ?? true,
        permissions: (json['permissions'] as List? ?? []).map((e) => e.toString()).toList(),
        isOwner: json['is_owner'] as bool? ?? false,
        receivesAllNotifications: json['receives_all_notifications'] as bool? ?? false,
        company: json['company'] != null ? SupervisorCompany.fromJson(json['company'] as Map<String, dynamic>) : null,
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at']) : null,
      );
}

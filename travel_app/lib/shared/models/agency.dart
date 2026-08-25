class Agency {
  const Agency({
    required this.id,
    required this.name,
    required this.email,
    this.logoUrl,
    this.licenseNumber,
    this.status,
  });

  factory Agency.fromJson(Map<String, dynamic> json) => Agency(
        id: json['id'] as int,
        name: json['name'] as String? ?? '',
        email: json['email'] as String? ?? '',
        logoUrl: json['logo_url'] as String?,
        licenseNumber: json['license_number'] as String?,
        status: json['status'] as String?,
      );

  final int id;
  final String name;
  final String email;
  final String? logoUrl;
  final String? licenseNumber;
  final String? status;
}

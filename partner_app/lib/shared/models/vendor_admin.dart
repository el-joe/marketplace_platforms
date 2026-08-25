/// `GET /auth/me` payload — `{ admin: {...}, vendor: {...} }`.
class VendorAdmin {
  final int id;
  final String name;
  final String? email;
  final String? role;
  final bool isOwner;
  final Vendor vendor;

  VendorAdmin({required this.id, required this.name, this.email, this.role, this.isOwner = false, required this.vendor});

  factory VendorAdmin.fromJson(Map<String, dynamic> json) {
    final admin = (json['admin'] as Map?)?.cast<String, dynamic>() ?? {};
    final vendor = (json['vendor'] as Map?)?.cast<String, dynamic>() ?? {};
    return VendorAdmin(
      id: (admin['id'] as num?)?.toInt() ?? 0,
      name: admin['name'] as String? ?? '',
      email: admin['email'] as String?,
      role: admin['role'] as String?,
      isOwner: admin['is_owner'] as bool? ?? false,
      vendor: Vendor.fromJson(vendor),
    );
  }
}

class Vendor {
  final int id;
  final String? storeName;
  final String? logoUrl;
  final String? globalStatus;
  final String? vendorType;
  final String? currencyCode;
  final double? ratingAvg;
  final int? totalSalesCount;

  Vendor({
    required this.id,
    this.storeName,
    this.logoUrl,
    this.globalStatus,
    this.vendorType,
    this.currencyCode,
    this.ratingAvg,
    this.totalSalesCount,
  });

  factory Vendor.fromJson(Map<String, dynamic> json) {
    final country = (json['country'] as Map?)?.cast<String, dynamic>();
    return Vendor(
      id: (json['id'] as num?)?.toInt() ?? 0,
      storeName: json['store_name'] as String?,
      logoUrl: json['logo_url'] as String?,
      globalStatus: json['global_status'] as String?,
      vendorType: json['vendor_type'] as String?,
      currencyCode: country?['currency_code'] as String?,
      ratingAvg: (json['rating_avg'] as num?)?.toDouble(),
      totalSalesCount: (json['total_sales_count'] as num?)?.toInt(),
    );
  }
}

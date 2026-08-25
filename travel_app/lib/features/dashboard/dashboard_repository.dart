import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_client.dart';
import '../../core/api/endpoints.dart';

class DashboardData {
  const DashboardData({
    required this.activePackages,
    required this.totalBookings,
    required this.pendingBookings,
    required this.confirmedBookings,
    required this.newInquiries,
    required this.revenueByCurrency,
    required this.recentBookings,
    required this.recentPackages,
    required this.recentInquiries,
  });

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    final packageCounts = json['package_counts'] as Map<String, dynamic>? ?? {};
    return DashboardData(
      activePackages: packageCounts['active'] as int? ?? 0,
      totalBookings: json['total_bookings'] as int? ?? 0,
      pendingBookings: json['pending_bookings'] as int? ?? 0,
      confirmedBookings: json['confirmed_bookings'] as int? ?? 0,
      newInquiries: json['new_inquiries'] as int? ?? 0,
      revenueByCurrency: (json['revenue_by_currency'] as List<dynamic>? ?? [])
          .cast<Map<String, dynamic>>(),
      recentBookings: (json['recent_bookings'] as List<dynamic>? ?? [])
          .cast<Map<String, dynamic>>(),
      recentPackages: (json['recent_packages'] as List<dynamic>? ?? [])
          .cast<Map<String, dynamic>>(),
      recentInquiries: (json['recent_inquiries'] as List<dynamic>? ?? [])
          .cast<Map<String, dynamic>>(),
    );
  }

  final int activePackages;
  final int totalBookings;
  final int pendingBookings;
  final int confirmedBookings;
  final int newInquiries;
  final List<Map<String, dynamic>> revenueByCurrency;
  final List<Map<String, dynamic>> recentBookings;
  final List<Map<String, dynamic>> recentPackages;
  final List<Map<String, dynamic>> recentInquiries;
}

class DashboardRepository {
  DashboardRepository(this._dio);

  final Dio _dio;

  Future<DashboardData> fetchDashboard() async {
    final response = await _dio.get(Endpoints.dashboard);
    final data = (response.data['data'] ?? response.data) as Map<String, dynamic>;
    return DashboardData.fromJson(data);
  }
}

final dashboardRepositoryProvider = Provider<DashboardRepository>(
  (ref) => DashboardRepository(ref.watch(apiClientProvider)),
);

final dashboardProvider = FutureProvider.autoDispose<DashboardData>((ref) {
  return ref.watch(dashboardRepositoryProvider).fetchDashboard();
});

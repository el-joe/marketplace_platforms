import '../../core/api/api_client.dart';
import '../../shared/models/claim.dart';
import '../../shared/models/paginated.dart';

class ReportsRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Map<String, dynamic>> orders({String? agentId, String? status, String? dateFrom, String? dateTo, int page = 1}) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/reports/orders', queryParameters: {
        if (agentId != null) 'agent_id': agentId,
        if (status != null && status.isNotEmpty) 'status': status,
        if (dateFrom != null) 'date_from': dateFrom,
        if (dateTo != null) 'date_to': dateTo,
        'page': page,
      }),
      parse: (data) => data as Map<String, dynamic>,
    );
  }

  Future<Map<String, dynamic>> earnings({String? agentId, String? dateFrom, String? dateTo}) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/reports/earnings', queryParameters: {
        if (agentId != null) 'agent_id': agentId,
        if (dateFrom != null) 'date_from': dateFrom,
        if (dateTo != null) 'date_to': dateTo,
      }),
      parse: (data) => data as Map<String, dynamic>,
    );
  }

  Future<Map<String, dynamic>> payouts({String? agentId, String? status, int page = 1}) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/reports/payouts', queryParameters: {
        if (agentId != null) 'agent_id': agentId,
        if (status != null && status.isNotEmpty) 'status': status,
        'page': page,
      }),
      parse: (data) => data as Map<String, dynamic>,
    );
  }

  Future<Map<String, dynamic>> codSettlements({String? agentId, String? status, int page = 1}) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/reports/cod-settlements', queryParameters: {
        if (agentId != null) 'agent_id': agentId,
        if (status != null && status.isNotEmpty) 'status': status,
        'page': page,
      }),
      parse: (data) => data as Map<String, dynamic>,
    );
  }

  Future<Map<String, dynamic>> performance({String period = 'month'}) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/reports/performance', queryParameters: {'period': period}),
      parse: (data) => data as Map<String, dynamic>,
    );
  }

  /// Returns a list of `{month, avg_rating, count}` points. The backend
  /// returns this endpoint's payload as a bare JSON array (not wrapped in an
  /// object), unlike every other reports endpoint.
  Future<List<Map<String, dynamic>>> performanceTrend() {
    return _client.request<List<Map<String, dynamic>>>(
      (dio) => dio.get('/reports/performance/trend'),
      parse: (data) => (data as List).cast<Map<String, dynamic>>(),
    );
  }

  Future<Paginated<CarrierClaim>> claims({String? status, String? claimType, int page = 1}) {
    return _client.request<Paginated<CarrierClaim>>(
      (dio) => dio.get('/reports/claims', queryParameters: {
        if (status != null && status.isNotEmpty) 'status': status,
        if (claimType != null && claimType.isNotEmpty) 'claim_type': claimType,
        'page': page,
      }),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, CarrierClaim.fromJson),
    );
  }

  Future<Map<String, dynamic>> claimShow(String id) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/reports/claims/$id'),
      parse: (data) => data as Map<String, dynamic>,
    );
  }
}

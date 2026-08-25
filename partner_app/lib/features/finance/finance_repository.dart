import '../../core/api/api_client.dart';
import '../../shared/models/paginated.dart';

class FinanceRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Map<String, dynamic>> summary() {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/finance/summary'),
      parse: (data) => data as Map<String, dynamic>,
    );
  }

  /// `GET /finance/transactions` — response `data` is `{ items, meta, summary
  /// }`; `items` is already the resource-mapped list (not wrapped further).
  Future<Map<String, dynamic>> transactions({String? type}) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/finance/transactions', queryParameters: {if (type != null) 'type': type}),
      parse: (data) => data as Map<String, dynamic>,
    );
  }

  Future<Paginated<Map<String, dynamic>>> ledger() {
    return _client.request<Paginated<Map<String, dynamic>>>(
      (dio) => dio.get('/finance/ledger'),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, (json) => json),
    );
  }

  Future<List<Map<String, dynamic>>> commissionRates() {
    return _client.request<List<Map<String, dynamic>>>(
      (dio) => dio.get('/finance/commission-rates'),
      parse: (data) => (data as List).cast<Map<String, dynamic>>(),
    );
  }

  Future<Map<String, dynamic>> salesReport({String? dateFrom, String? dateTo}) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/finance/sales-report', queryParameters: {
        if (dateFrom != null) 'date_from': dateFrom,
        if (dateTo != null) 'date_to': dateTo,
      }),
      parse: (data) => data as Map<String, dynamic>,
    );
  }

  Future<Paginated<Map<String, dynamic>>> payouts({String? status}) {
    return _client.request<Paginated<Map<String, dynamic>>>(
      (dio) => dio.get('/finance/payouts', queryParameters: {if (status != null && status.isNotEmpty) 'status': status}),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, (json) => json),
    );
  }

  Future<Map<String, dynamic>> payoutShow(int id) {
    return _client.request<Map<String, dynamic>>(
      (dio) => dio.get('/finance/payouts/$id'),
      parse: (data) => data as Map<String, dynamic>,
    );
  }

  Future<List<Map<String, dynamic>>> bankAccounts() {
    return _client.request<List<Map<String, dynamic>>>(
      (dio) => dio.get('/finance/bank-accounts'),
      parse: (data) => (data as List).cast<Map<String, dynamic>>(),
    );
  }
}

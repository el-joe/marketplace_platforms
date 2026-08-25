import '../../core/api/api_client.dart';
import '../../shared/models/earning.dart';

class EarningsRepository {
  final ApiClient _client = ApiClient.instance;

  Future<EarningsSummary> getEarnings({String? dateFrom, String? dateTo}) {
    return _client.request<EarningsSummary>(
      (dio) => dio.get('/earnings', queryParameters: {
        if (dateFrom != null) 'date_from': dateFrom,
        if (dateTo != null) 'date_to': dateTo,
      }),
      parse: (data) => EarningsSummary.fromJson(data as Map<String, dynamic>),
    );
  }
}

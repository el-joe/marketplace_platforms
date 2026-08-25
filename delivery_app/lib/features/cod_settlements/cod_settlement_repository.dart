import '../../core/api/api_client.dart';
import '../../shared/models/cod_settlement.dart';
import '../../shared/models/paginated.dart';

class CodSettlementRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Paginated<CodSettlement>> list() {
    return _client.request<Paginated<CodSettlement>>(
      (dio) => dio.get('/cod-settlements'),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, CodSettlement.fromJson),
    );
  }

  Future<CurrentCod> current() {
    return _client.request<CurrentCod>(
      (dio) => dio.get('/cod-settlements/current'),
      parse: (data) => CurrentCod.fromJson(data as Map<String, dynamic>),
    );
  }
}

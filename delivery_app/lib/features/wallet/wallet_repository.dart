import '../../core/api/api_client.dart';
import '../../shared/models/paginated.dart';
import '../../shared/models/wallet.dart';

class WalletRepository {
  final ApiClient _client = ApiClient.instance;

  Future<Wallet> getWallet() {
    return _client.request<Wallet>(
      (dio) => dio.get('/wallet'),
      parse: (data) => Wallet.fromJson(data as Map<String, dynamic>),
    );
  }

  Future<Paginated<WalletTransaction>> getTransactions() {
    return _client.request<Paginated<WalletTransaction>>(
      (dio) => dio.get('/wallet/transactions'),
      parse: (data) => Paginated.fromJson(data as Map<String, dynamic>, WalletTransaction.fromJson),
    );
  }

  Future<void> requestWithdrawal({
    required int amount,
    required String bankName,
    required String bankIban,
  }) {
    return _client.request<void>(
      (dio) => dio.post('/wallet/withdraw', data: {
        'amount': amount,
        'bank_name': bankName,
        'bank_iban': bankIban,
      }),
      parse: (_) {},
    );
  }
}

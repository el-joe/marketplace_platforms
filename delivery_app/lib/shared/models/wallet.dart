class Wallet {
  final int balance;
  final int pendingBalance;
  final String currency;
  final bool isFrozen;

  Wallet({required this.balance, required this.pendingBalance, required this.currency, required this.isFrozen});

  factory Wallet.fromJson(Map<String, dynamic> json) => Wallet(
        balance: (json['balance'] as num?)?.toInt() ?? 0,
        pendingBalance: (json['pending_balance'] as num?)?.toInt() ?? 0,
        currency: json['currency'] as String? ?? 'AED',
        isFrozen: json['is_frozen'] as bool? ?? false,
      );
}

class WalletTransaction {
  final int id;
  final String? type;
  final int amount;
  final int? balanceAfter;
  final String? description;
  final String? sourceType;
  final DateTime? createdAt;

  WalletTransaction({
    required this.id,
    this.type,
    required this.amount,
    this.balanceAfter,
    this.description,
    this.sourceType,
    this.createdAt,
  });

  factory WalletTransaction.fromJson(Map<String, dynamic> json) => WalletTransaction(
        id: (json['id'] as num).toInt(),
        type: json['type'] as String?,
        amount: (json['amount'] as num?)?.toInt() ?? 0,
        balanceAfter: (json['balance_after'] as num?)?.toInt(),
        description: json['description'] as String?,
        sourceType: json['source_type'] as String?,
        createdAt: json['created_at'] != null ? DateTime.tryParse(json['created_at']) : null,
      );
}

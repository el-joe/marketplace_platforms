class CodSettlementAssignment {
  final int id;
  final String? subOrderNumber;
  final DateTime? deliveredAt;
  final int codCollected;

  CodSettlementAssignment({
    required this.id,
    this.subOrderNumber,
    this.deliveredAt,
    required this.codCollected,
  });

  factory CodSettlementAssignment.fromJson(Map<String, dynamic> json) => CodSettlementAssignment(
        id: (json['id'] as num).toInt(),
        subOrderNumber: json['sub_order_number'] as String?,
        deliveredAt: json['delivered_at'] != null ? DateTime.tryParse(json['delivered_at']) : null,
        codCollected: (json['cod_collected'] as num?)?.toInt() ?? 0,
      );
}

class CodSettlement {
  final int id;
  final String? status;
  final String? periodStart;
  final String? periodEnd;
  final int totalCodCollected;
  final int totalEarningsOwed;
  final int netToRemit;
  final DateTime? settledAt;
  final List<CodSettlementAssignment> assignments;

  CodSettlement({
    required this.id,
    this.status,
    this.periodStart,
    this.periodEnd,
    required this.totalCodCollected,
    required this.totalEarningsOwed,
    required this.netToRemit,
    this.settledAt,
    this.assignments = const [],
  });

  factory CodSettlement.fromJson(Map<String, dynamic> json) => CodSettlement(
        id: (json['id'] as num).toInt(),
        status: json['status'] as String?,
        periodStart: json['period_start'] as String?,
        periodEnd: json['period_end'] as String?,
        totalCodCollected: (json['total_cod_collected'] as num?)?.toInt() ?? 0,
        totalEarningsOwed: (json['total_earnings_owed'] as num?)?.toInt() ?? 0,
        netToRemit: (json['net_to_remit'] as num?)?.toInt() ?? 0,
        settledAt: json['settled_at'] != null ? DateTime.tryParse(json['settled_at']) : null,
        assignments: (json['assignments'] as List? ?? [])
            .map((e) => CodSettlementAssignment.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class CurrentCod {
  final String currency;
  final int codTotal;
  final int earningsTotal;
  final int netToRemit;
  final List<CodSettlementAssignment> deliveries;

  CurrentCod({
    required this.currency,
    required this.codTotal,
    required this.earningsTotal,
    required this.netToRemit,
    required this.deliveries,
  });

  factory CurrentCod.fromJson(Map<String, dynamic> json) => CurrentCod(
        currency: json['currency'] as String? ?? 'AED',
        codTotal: (json['cod_total'] as num?)?.toInt() ?? 0,
        earningsTotal: (json['earnings_total'] as num?)?.toInt() ?? 0,
        netToRemit: (json['net_to_remit'] as num?)?.toInt() ?? 0,
        deliveries: (json['deliveries'] as List? ?? [])
            .map((e) => CodSettlementAssignment.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

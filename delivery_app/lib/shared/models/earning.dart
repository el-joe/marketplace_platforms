class Earning {
  final int id;
  final String? earningType;
  final num amount;
  final String? currency;
  final String? status;
  final DateTime? earnedAt;
  final int? assignmentId;

  Earning({
    required this.id,
    this.earningType,
    required this.amount,
    this.currency,
    this.status,
    this.earnedAt,
    this.assignmentId,
  });

  factory Earning.fromJson(Map<String, dynamic> json) => Earning(
        id: (json['id'] as num).toInt(),
        earningType: json['earning_type'] as String?,
        amount: (json['amount'] as num?) ?? 0,
        currency: json['currency'] as String?,
        status: json['status'] as String?,
        earnedAt: json['earned_at'] != null ? DateTime.tryParse(json['earned_at']) : null,
        assignmentId: (json['assignment_id'] as num?)?.toInt(),
      );
}

class EarningsDay {
  final String date;
  final num total;
  final List<Earning> earnings;

  EarningsDay({required this.date, required this.total, required this.earnings});

  factory EarningsDay.fromJson(Map<String, dynamic> json) => EarningsDay(
        date: json['date'] as String? ?? '',
        total: (json['total'] as num?) ?? 0,
        earnings: (json['earnings'] as List? ?? [])
            .map((e) => Earning.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class EarningsSummary {
  final String currency;
  final num todayTotal;
  final List<EarningsDay> days;
  final int currentPage;
  final int lastPage;

  EarningsSummary({
    required this.currency,
    required this.todayTotal,
    required this.days,
    required this.currentPage,
    required this.lastPage,
  });

  factory EarningsSummary.fromJson(Map<String, dynamic> json) {
    final meta = (json['meta'] ?? {}) as Map<String, dynamic>;
    return EarningsSummary(
      currency: json['currency'] as String? ?? 'AED',
      todayTotal: (json['today_total'] as num?) ?? 0,
      days: (json['days'] as List? ?? []).map((e) => EarningsDay.fromJson(e as Map<String, dynamic>)).toList(),
      currentPage: (meta['current_page'] as num?)?.toInt() ?? 1,
      lastPage: (meta['last_page'] as num?)?.toInt() ?? 1,
    );
  }
}

import 'package:intl/intl.dart';

/// All amounts are BIGINT base-currency units — never divide or multiply.
class MoneyFormatter {
  MoneyFormatter._();

  static String format(int amount, String currency) =>
      '${NumberFormat('#,##0', 'en').format(amount)} $currency';

  static String compact(int amount, String currency) {
    if (amount >= 1000000) return '${(amount / 1000000).toStringAsFixed(1)}M $currency';
    if (amount >= 1000) return '${(amount / 1000).toStringAsFixed(1)}K $currency';
    return '$amount $currency';
  }
}

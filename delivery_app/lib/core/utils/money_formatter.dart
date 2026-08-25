import 'package:intl/intl.dart';

/// Money values from the API are BIGINT base-currency units (already whole
/// currency units for this backend — never divide or multiply). Always
/// display via this formatter so formatting stays consistent app-wide.
class MoneyFormatter {
  MoneyFormatter._();

  static String format(num amount, String currency) {
    final formatter = NumberFormat.currency(
      symbol: '$currency ',
      decimalDigits: 2,
      locale: 'en_US',
    );
    return formatter.format(amount);
  }

  static String formatCompact(num amount, String currency) {
    final formatter = NumberFormat.compactCurrency(
      symbol: '$currency ',
      locale: 'en_US',
    );
    return formatter.format(amount);
  }
}

import 'package:intl/intl.dart';

/// Money values from the Partner API are BIGINT base-currency units — the
/// backend never sends fractional/minor units for this app. NEVER divide or
/// multiply an amount; always format it through this helper so display stays
/// consistent everywhere.
class MoneyFormatter {
  MoneyFormatter._();

  static String format(num? amount, String? currency) {
    final formatter = NumberFormat.currency(
      symbol: '${currency ?? ''} ',
      decimalDigits: 2,
      locale: 'en_US',
    );
    return formatter.format(amount ?? 0);
  }

  static String formatCompact(num? amount, String? currency) {
    final formatter = NumberFormat.compactCurrency(
      symbol: '${currency ?? ''} ',
      locale: 'en_US',
    );
    return formatter.format(amount ?? 0);
  }
}

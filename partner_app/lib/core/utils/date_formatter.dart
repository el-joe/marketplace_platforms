import 'package:intl/intl.dart';

class DateFormatter {
  DateFormatter._();

  static String date(DateTime? dt) => dt == null ? '-' : DateFormat('d MMM yyyy').format(dt.toLocal());

  static String dateTime(DateTime? dt) =>
      dt == null ? '-' : DateFormat('d MMM yyyy, h:mm a').format(dt.toLocal());

  static DateTime? parse(String? value) => value == null ? null : DateTime.tryParse(value);

  static String relative(DateTime? dt) {
    if (dt == null) return '-';
    final diff = DateTime.now().difference(dt.toLocal());
    if (diff.inSeconds < 60) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    if (diff.inDays < 7) return '${diff.inDays}d ago';
    return date(dt);
  }
}

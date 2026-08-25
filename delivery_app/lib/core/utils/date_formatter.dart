import 'package:intl/intl.dart';

class DateFormatter {
  DateFormatter._();

  static String time(DateTime? dt) => dt == null ? '--' : DateFormat.jm().format(dt.toLocal());

  static String date(DateTime? dt) => dt == null ? '--' : DateFormat.yMMMd().format(dt.toLocal());

  static String dateTime(DateTime? dt) =>
      dt == null ? '--' : DateFormat.yMMMd().add_jm().format(dt.toLocal());

  static String relative(DateTime? dt) {
    if (dt == null) return '--';
    final local = dt.toLocal();
    final diff = DateTime.now().difference(local);
    if (diff.inSeconds < 60) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    if (diff.inDays < 7) return '${diff.inDays}d ago';
    return date(local);
  }
}

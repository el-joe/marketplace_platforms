class Endpoints {
  Endpoints._();

  static const baseUrl = 'https://api.noon.codefanz.com/api/travel-agency/v1';

  static const login = '/auth/login';
  static const logout = '/auth/logout';
  static const refresh = '/auth/refresh';
  static const me = '/auth/me';

  static const dashboard = '/dashboard';

  static const packages = '/packages';
  static String package(int id) => '/packages/$id';

  static const bookings = '/bookings';
  static String booking(int id) => '/bookings/$id';

  static const inquiries = '/inquiries';

  static const campaigns = '/campaigns';
  static String campaign(int id) => '/campaigns/$id';

  static const financeRevenue = '/finance/revenue';
  static const financePayouts = '/finance/payouts';
  static const financeWallet = '/finance/wallet';
  static const financeSalesReport = '/finance/sales-report';

  static const reportsRevenue = '/reports/revenue';
  static const reportsBookings = '/reports/bookings';
  static const reportsPackages = '/reports/packages';

  static const performance = '/performance';

  static const bankAccounts = '/bank-accounts';

  static const profile = '/profile';

  static const notifications = '/notifications';
  static const notificationsUnreadCount = '/notifications/unread-count';
  static String notificationRead(int id) => '/notifications/$id/read';

  static const fcmRegister = '/fcm/register-device';
  static const fcmUnregister = '/fcm/unregister-device';
}

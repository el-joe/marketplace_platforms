import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/assignments/assignment_detail_screen.dart';
import '../../features/assignments/assignments_list_screen.dart';
import '../../features/auth/auth_provider.dart';
import '../../features/auth/login_screen.dart';
import '../../features/cod_settlements/cod_settlements_screen.dart';
import '../../features/dashboard/dashboard_screen.dart';
import '../../features/earnings/earnings_screen.dart';
import '../../features/notifications/notifications_screen.dart';
import '../../features/profile/change_password_screen.dart';
import '../../features/profile/documents_screen.dart';
import '../../features/profile/profile_screen.dart';
import '../../features/support/new_ticket_screen.dart';
import '../../features/support/support_ticket_detail_screen.dart';
import '../../features/support/support_tickets_screen.dart';
import '../../features/wallet/wallet_screen.dart';
import '../../features/wallet/withdraw_screen.dart';
import '../../shared/widgets/bottom_nav_scaffold.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authProvider);

  return GoRouter(
    initialLocation: '/dashboard',
    refreshListenable: _AuthListenable(ref),
    redirect: (context, state) {
      final loggingIn = state.matchedLocation == '/login';
      if (authState.status == AuthStatus.unknown) return null;

      final authenticated = authState.status == AuthStatus.authenticated;
      if (!authenticated && !loggingIn) return '/login';
      if (authenticated && loggingIn) return '/dashboard';
      return null;
    },
    routes: [
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      ShellRoute(
        builder: (context, state, child) =>
            BottomNavScaffold(location: state.matchedLocation, child: child),
        routes: [
          GoRoute(path: '/dashboard', builder: (context, state) => const DashboardScreen()),
          GoRoute(path: '/assignments', builder: (context, state) => const AssignmentsListScreen()),
          GoRoute(path: '/earnings', builder: (context, state) => const EarningsScreen()),
          GoRoute(path: '/notifications', builder: (context, state) => const NotificationsScreen()),
          GoRoute(path: '/profile', builder: (context, state) => const ProfileScreen()),
        ],
      ),
      GoRoute(
        path: '/assignments/:id',
        builder: (context, state) =>
            AssignmentDetailScreen(assignmentId: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(path: '/wallet', builder: (context, state) => const WalletScreen()),
      GoRoute(path: '/wallet/withdraw', builder: (context, state) => const WithdrawScreen()),
      GoRoute(path: '/cod-settlements', builder: (context, state) => const CodSettlementsScreen()),
      GoRoute(path: '/support', builder: (context, state) => const SupportTicketsScreen()),
      GoRoute(path: '/support/new', builder: (context, state) => const NewTicketScreen()),
      GoRoute(
        path: '/support/:ticketNumber',
        builder: (context, state) =>
            SupportTicketDetailScreen(ticketNumber: state.pathParameters['ticketNumber']!),
      ),
      GoRoute(path: '/profile/documents', builder: (context, state) => const DocumentsScreen()),
      GoRoute(path: '/profile/change-password', builder: (context, state) => const ChangePasswordScreen()),
    ],
  );
});

class _AuthListenable extends ChangeNotifier {
  _AuthListenable(Ref ref) {
    ref.listen(authProvider, (_, __) => notifyListeners());
  }
}

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/agents/agent_detail_screen.dart';
import '../../features/agents/agent_form_screen.dart';
import '../../features/agents/agents_list_screen.dart';
import '../../features/assignments/assignment_detail_screen.dart';
import '../../features/assignments/assignments_list_screen.dart';
import '../../features/assignments/unassigned_screen.dart';
import '../../features/auth/auth_provider.dart';
import '../../features/auth/login_screen.dart';
import '../../features/dashboard/dashboard_screen.dart';
import '../../features/notifications/notifications_screen.dart';
import '../../features/reports/claim_detail_screen.dart';
import '../../features/reports/reports_screen.dart';
import '../../features/supervisors/supervisors_list_screen.dart';
import '../../shared/widgets/bottom_nav_scaffold.dart';

/// Used so the router's permission-redirect can surface a SnackBar without
/// needing a BuildContext of its own.
final rootScaffoldMessengerKey = GlobalKey<ScaffoldMessengerState>();

/// Route prefixes that require a permission, keyed by the permission name.
const _protectedPrefixes = <String, String>{
  '/agents': 'manage_agents',
  '/assignments': 'view_orders',
  '/reports': 'view_reports',
  '/supervisors': 'manage_agents',
};

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

      if (authenticated) {
        final permissions = authState.supervisor?.permissions ?? const [];
        for (final entry in _protectedPrefixes.entries) {
          if (state.matchedLocation.startsWith(entry.key) && !permissions.contains(entry.value)) {
            WidgetsBinding.instance.addPostFrameCallback((_) {
              rootScaffoldMessengerKey.currentState?.showSnackBar(
                const SnackBar(content: Text("You don't have permission to access that page.")),
              );
            });
            return '/dashboard';
          }
        }
      }
      return null;
    },
    routes: [
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      ShellRoute(
        builder: (context, state, child) =>
            BottomNavScaffold(location: state.matchedLocation, child: child),
        routes: [
          GoRoute(path: '/dashboard', builder: (context, state) => const DashboardScreen()),
          GoRoute(path: '/agents', builder: (context, state) => const AgentsListScreen()),
          GoRoute(path: '/assignments', builder: (context, state) => const AssignmentsListScreen()),
          GoRoute(path: '/reports', builder: (context, state) => const ReportsScreen()),
          GoRoute(path: '/notifications', builder: (context, state) => const NotificationsScreen()),
        ],
      ),
      GoRoute(path: '/agents/new', builder: (context, state) => const AgentFormScreen()),
      GoRoute(
        path: '/agents/:id',
        builder: (context, state) => AgentDetailScreen(agentId: state.pathParameters['id']!),
      ),
      GoRoute(
        path: '/agents/:id/edit',
        builder: (context, state) => AgentFormScreen(agentId: state.pathParameters['id']!),
      ),
      GoRoute(path: '/assignments/unassigned', builder: (context, state) => const UnassignedScreen()),
      GoRoute(
        path: '/assignments/:id',
        builder: (context, state) => AssignmentDetailScreen(assignmentId: state.pathParameters['id']!),
      ),
      GoRoute(
        path: '/reports/claims/:id',
        builder: (context, state) => ClaimDetailScreen(claimId: state.pathParameters['id']!),
      ),
      GoRoute(path: '/supervisors', builder: (context, state) => const SupervisorsListScreen()),
    ],
  );
});

class _AuthListenable extends ChangeNotifier {
  _AuthListenable(Ref ref) {
    ref.listen(authProvider, (_, __) => notifyListeners());
  }
}

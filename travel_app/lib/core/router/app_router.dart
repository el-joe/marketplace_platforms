import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/login_screen.dart';
import '../../features/dashboard/dashboard_screen.dart';
import '../../shared/widgets/bottom_nav.dart';
import '../../shared/widgets/placeholder_screen.dart';
import '../auth/auth_provider.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authProvider);

  return GoRouter(
    initialLocation: '/login',
    redirect: (context, state) {
      final loggingIn = state.matchedLocation == '/login';
      if (!authState.isAuthenticated && !loggingIn) return '/login';
      if (authState.isAuthenticated && loggingIn) return '/dashboard';
      return null;
    },
    routes: [
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      GoRoute(
        path: '/notifications',
        builder: (context, state) => const PlaceholderScreen(title: 'Notifications'),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) => BottomNavShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(routes: [
            GoRoute(path: '/dashboard', builder: (context, state) => const DashboardScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/packages',
              builder: (context, state) => const PlaceholderScreen(title: 'Packages'),
              routes: [
                GoRoute(
                  path: ':id',
                  builder: (context, state) => PlaceholderScreen(title: 'Package ${state.pathParameters['id']}'),
                ),
              ],
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/bookings',
              builder: (context, state) => const PlaceholderScreen(title: 'Bookings'),
              routes: [
                GoRoute(
                  path: ':id',
                  builder: (context, state) => PlaceholderScreen(title: 'Booking ${state.pathParameters['id']}'),
                ),
              ],
            ),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/finance', builder: (context, state) => const PlaceholderScreen(title: 'Finance')),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(
              path: '/profile',
              builder: (context, state) => const PlaceholderScreen(title: 'Profile'),
              routes: [
                GoRoute(
                  path: 'bank-accounts',
                  builder: (context, state) => const PlaceholderScreen(title: 'Bank Accounts'),
                ),
              ],
            ),
          ]),
        ],
      ),
      GoRoute(
        path: '/inquiries',
        builder: (context, state) => const PlaceholderScreen(title: 'Inquiries'),
      ),
      GoRoute(
        path: '/campaigns',
        builder: (context, state) => const PlaceholderScreen(title: 'Campaigns'),
        routes: [
          GoRoute(
            path: ':id',
            builder: (context, state) => PlaceholderScreen(title: 'Campaign ${state.pathParameters['id']}'),
          ),
        ],
      ),
      GoRoute(
        path: '/reports',
        builder: (context, state) => const PlaceholderScreen(title: 'Reports'),
      ),
      GoRoute(
        path: '/performance',
        builder: (context, state) => const PlaceholderScreen(title: 'Performance'),
      ),
    ],
  );
});

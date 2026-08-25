import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/auth_provider.dart';
import '../../features/auth/login_screen.dart';
import '../../features/classifieds/classified_detail_screen.dart';
import '../../features/classifieds/classified_inquiries_screen.dart';
import '../../features/classifieds/classifieds_list_screen.dart';
import '../../features/finance/bank_accounts_screen.dart';
import '../../features/finance/commission_rates_screen.dart';
import '../../features/finance/finance_screen.dart';
import '../../features/finance/ledger_screen.dart';
import '../../features/finance/payout_detail_screen.dart';
import '../../features/finance/payouts_screen.dart';
import '../../features/finance/sales_report_screen.dart';
import '../../features/finance/transactions_screen.dart';
import '../../features/home/home_screen.dart';
import '../../features/inventory/inventory_list_screen.dart';
import '../../features/inventory/inventory_movements_screen.dart';
import '../../features/inventory/transfer_detail_screen.dart';
import '../../features/inventory/transfers_list_screen.dart';
import '../../features/listings/listing_detail_screen.dart';
import '../../features/listings/listings_list_screen.dart';
import '../../features/notifications/notifications_screen.dart';
import '../../features/orders/order_detail_screen.dart';
import '../../features/orders/orders_list_screen.dart';
import '../../features/performance/performance_screen.dart';
import '../../features/performance/reviews_screen.dart';
import '../../features/profile/documents_screen.dart';
import '../../features/profile/profile_screen.dart';
import '../../features/returns/return_detail_screen.dart';
import '../../features/returns/returns_list_screen.dart';
import '../../features/warehouses/warehouses_screen.dart';
import '../../features/warranty/warranty_detail_screen.dart';
import '../../features/warranty/warranty_list_screen.dart';
import '../../shared/widgets/bottom_nav_scaffold.dart';

final routerProvider = Provider<GoRouter>((ref) {
  final authState = ref.watch(authProvider);

  return GoRouter(
    initialLocation: '/home',
    refreshListenable: _AuthListenable(ref),
    redirect: (context, state) {
      final loggingIn = state.matchedLocation == '/login';
      if (authState.status == AuthStatus.unknown) return null;

      final authenticated = authState.status == AuthStatus.authenticated;
      if (!authenticated && !loggingIn) return '/login';
      if (authenticated && loggingIn) return '/home';
      return null;
    },
    routes: [
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),
      ShellRoute(
        builder: (context, state, child) => BottomNavScaffold(location: state.matchedLocation, child: child),
        routes: [
          GoRoute(path: '/home', builder: (context, state) => const HomeScreen()),
          GoRoute(path: '/orders', builder: (context, state) => const OrdersListScreen()),
          GoRoute(path: '/inventory', builder: (context, state) => const InventoryListScreen()),
          GoRoute(path: '/finance', builder: (context, state) => const FinanceScreen()),
          GoRoute(path: '/profile', builder: (context, state) => const ProfileScreen()),
        ],
      ),
      GoRoute(path: '/notifications', builder: (context, state) => const NotificationsScreen()),

      // Orders
      GoRoute(
        path: '/orders/:subOrderNumber',
        builder: (context, state) => OrderDetailScreen(subOrderNumber: state.pathParameters['subOrderNumber']!),
      ),

      // Returns
      GoRoute(path: '/returns', builder: (context, state) => const ReturnsListScreen()),
      GoRoute(
        path: '/returns/:returnNumber',
        builder: (context, state) => ReturnDetailScreen(returnNumber: state.pathParameters['returnNumber']!),
      ),

      // Warranty
      GoRoute(path: '/warranty', builder: (context, state) => const WarrantyListScreen()),
      GoRoute(
        path: '/warranty/:id',
        builder: (context, state) => WarrantyDetailScreen(id: state.pathParameters['id']!),
      ),

      // Listings
      GoRoute(path: '/listings', builder: (context, state) => const ListingsListScreen()),
      GoRoute(
        path: '/listings/:id',
        builder: (context, state) => ListingDetailScreen(id: state.pathParameters['id']!),
      ),

      // Inventory
      GoRoute(
        path: '/inventory/:id/movements',
        builder: (context, state) => InventoryMovementsScreen(inventoryId: state.pathParameters['id']!),
      ),
      GoRoute(path: '/inventory/transfers', builder: (context, state) => const TransfersListScreen()),
      GoRoute(
        path: '/inventory/transfers/:number',
        builder: (context, state) => TransferDetailScreen(transferNumber: state.pathParameters['number']!),
      ),

      // Warehouses
      GoRoute(path: '/warehouses', builder: (context, state) => const WarehousesScreen()),

      // Classifieds
      GoRoute(path: '/classifieds', builder: (context, state) => const ClassifiedsListScreen()),
      GoRoute(
        path: '/classifieds/:id',
        builder: (context, state) => ClassifiedDetailScreen(id: state.pathParameters['id']!),
      ),
      GoRoute(
        path: '/classifieds/:id/inquiries',
        builder: (context, state) => ClassifiedInquiriesScreen(id: state.pathParameters['id']!),
      ),

      // Performance
      GoRoute(path: '/performance', builder: (context, state) => const PerformanceScreen()),
      GoRoute(path: '/performance/reviews', builder: (context, state) => const ReviewsScreen()),

      // Finance sub-screens
      GoRoute(path: '/finance/payouts', builder: (context, state) => const PayoutsScreen()),
      GoRoute(
        path: '/finance/payouts/:id',
        builder: (context, state) => PayoutDetailScreen(id: int.parse(state.pathParameters['id']!)),
      ),
      GoRoute(path: '/finance/transactions', builder: (context, state) => const TransactionsScreen()),
      GoRoute(path: '/finance/ledger', builder: (context, state) => const LedgerScreen()),
      GoRoute(path: '/finance/commission-rates', builder: (context, state) => const CommissionRatesScreen()),
      GoRoute(path: '/finance/sales-report', builder: (context, state) => const SalesReportScreen()),
      GoRoute(path: '/finance/bank-accounts', builder: (context, state) => const BankAccountsScreen()),

      // Profile
      GoRoute(path: '/profile/documents', builder: (context, state) => const DocumentsScreen()),
    ],
  );
});

class _AuthListenable extends ChangeNotifier {
  _AuthListenable(Ref ref) {
    ref.listen(authProvider, (_, __) => notifyListeners());
  }
}

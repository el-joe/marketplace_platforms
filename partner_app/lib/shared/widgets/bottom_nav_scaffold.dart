import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/notifications/notifications_provider.dart';

class BottomNavScaffold extends ConsumerStatefulWidget {
  final Widget child;
  final String location;

  const BottomNavScaffold({super.key, required this.child, required this.location});

  @override
  ConsumerState<BottomNavScaffold> createState() => _BottomNavScaffoldState();
}

class _BottomNavScaffoldState extends ConsumerState<BottomNavScaffold> {
  Timer? _pollTimer;

  static const _tabs = [
    (path: '/home', icon: Icons.home_outlined, activeIcon: Icons.home, label: 'Home'),
    (path: '/orders', icon: Icons.receipt_long_outlined, activeIcon: Icons.receipt_long, label: 'Orders'),
    (path: '/inventory', icon: Icons.inventory_2_outlined, activeIcon: Icons.inventory_2, label: 'Inventory'),
    (path: '/finance', icon: Icons.account_balance_wallet_outlined, activeIcon: Icons.account_balance_wallet, label: 'Finance'),
    (path: '/profile', icon: Icons.person_outline, activeIcon: Icons.person, label: 'Profile'),
  ];

  int get _currentIndex {
    final index = _tabs.indexWhere((t) => widget.location.startsWith(t.path));
    return index == -1 ? 0 : index;
  }

  @override
  void initState() {
    super.initState();
    // Unread notification badge is polled every 60s while any tab is open.
    _pollTimer = Timer.periodic(const Duration(seconds: 60), (_) {
      ref.invalidate(unreadCountProvider);
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: widget.child,
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (index) => context.go(_tabs[index].path),
        items: _tabs
            .map((t) => BottomNavigationBarItem(icon: Icon(t.icon), activeIcon: Icon(t.activeIcon), label: t.label))
            .toList(),
      ),
    );
  }
}

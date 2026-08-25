import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class BottomNavScaffold extends StatelessWidget {
  final Widget child;
  final String location;

  const BottomNavScaffold({super.key, required this.child, required this.location});

  static const _tabs = [
    (path: '/dashboard', icon: Icons.home_outlined, activeIcon: Icons.home, label: 'Home'),
    (path: '/assignments', icon: Icons.local_shipping_outlined, activeIcon: Icons.local_shipping, label: 'Deliveries'),
    (path: '/earnings', icon: Icons.account_balance_wallet_outlined, activeIcon: Icons.account_balance_wallet, label: 'Earnings'),
    (path: '/notifications', icon: Icons.notifications_outlined, activeIcon: Icons.notifications, label: 'Alerts'),
    (path: '/profile', icon: Icons.person_outline, activeIcon: Icons.person, label: 'Profile'),
  ];

  int get _currentIndex {
    final index = _tabs.indexWhere((t) => location.startsWith(t.path));
    return index == -1 ? 0 : index;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: child,
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (index) => context.go(_tabs[index].path),
        items: _tabs
            .map((t) => BottomNavigationBarItem(
                  icon: Icon(t.icon),
                  activeIcon: Icon(t.activeIcon),
                  label: t.label,
                ))
            .toList(),
      ),
    );
  }
}

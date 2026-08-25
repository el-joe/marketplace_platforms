import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

class BottomNavShell extends StatelessWidget {
  const BottomNavShell({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  static const _items = [
    (icon: Icons.home_outlined, activeIcon: Icons.home, label: 'Dashboard'),
    (icon: Icons.flight_takeoff_outlined, activeIcon: Icons.flight_takeoff, label: 'Packages'),
    (icon: Icons.book_online_outlined, activeIcon: Icons.book_online, label: 'Bookings'),
    (icon: Icons.account_balance_wallet_outlined, activeIcon: Icons.account_balance_wallet, label: 'Finance'),
    (icon: Icons.person_outline, activeIcon: Icons.person, label: 'Profile'),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: navigationShell.currentIndex,
        onTap: (index) => navigationShell.goBranch(
          index,
          initialLocation: index == navigationShell.currentIndex,
        ),
        items: [
          for (final item in _items)
            BottomNavigationBarItem(
              icon: Icon(item.icon),
              activeIcon: Icon(item.activeIcon),
              label: item.label,
            ),
        ],
      ),
    );
  }
}

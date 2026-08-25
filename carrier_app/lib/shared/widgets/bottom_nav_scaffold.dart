import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/auth_provider.dart';

typedef _Tab = ({String path, IconData icon, IconData activeIcon, String label, String? permission});

const _allTabs = <_Tab>[
  (path: '/dashboard', icon: Icons.home_outlined, activeIcon: Icons.home, label: 'Home', permission: null),
  (path: '/agents', icon: Icons.groups_outlined, activeIcon: Icons.groups, label: 'Agents', permission: 'manage_agents'),
  (path: '/assignments', icon: Icons.local_shipping_outlined, activeIcon: Icons.local_shipping, label: 'Orders', permission: 'view_orders'),
  (path: '/reports', icon: Icons.bar_chart_outlined, activeIcon: Icons.bar_chart, label: 'Reports', permission: 'view_reports'),
  (path: '/notifications', icon: Icons.notifications_outlined, activeIcon: Icons.notifications, label: 'Alerts', permission: null),
];

/// The bottom-nav shell. Tabs the current supervisor lacks permission for
/// are removed entirely (not just disabled) — see [_allTabs].
class BottomNavScaffold extends ConsumerWidget {
  final Widget child;
  final String location;

  const BottomNavScaffold({super.key, required this.child, required this.location});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final permissions = ref.watch(permissionsProvider);
    final tabs = _allTabs.where((t) => t.permission == null || permissions.contains(t.permission)).toList();

    var currentIndex = tabs.indexWhere((t) => location.startsWith(t.path));
    if (currentIndex == -1) currentIndex = 0;

    return Scaffold(
      body: child,
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: currentIndex,
        onTap: (index) => context.go(tabs[index].path),
        items: tabs
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

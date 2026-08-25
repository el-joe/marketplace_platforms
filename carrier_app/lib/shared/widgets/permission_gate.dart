import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/auth_provider.dart';

/// Shows [child] only when the current supervisor has [permission], else
/// [fallback] (defaults to an empty box). Use to gate FABs, action buttons,
/// nav entries, and any other permission-scoped UI.
class PermissionGate extends ConsumerWidget {
  final String permission;
  final Widget child;
  final Widget? fallback;

  const PermissionGate({
    super.key,
    required this.permission,
    required this.child,
    this.fallback,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final permissions = ref.watch(permissionsProvider);
    if (permissions.has(permission)) return child;
    return fallback ?? const SizedBox.shrink();
  }
}

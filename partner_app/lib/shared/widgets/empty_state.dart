import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

/// Shown instead of a blank screen whenever a list/detail has no data.
class EmptyState extends StatelessWidget {
  final String message;
  final IconData icon;

  const EmptyState({super.key, required this.message, this.icon = Icons.inbox_outlined});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: AppTheme.textSecondary),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center, style: const TextStyle(color: AppTheme.textSecondary)),
          ],
        ),
      ),
    );
  }
}

/// Wraps a list body so it always supports pull-to-refresh, even when
/// showing the [EmptyState] (an empty ListView so the gesture still works).
class RefreshableList extends StatelessWidget {
  final Future<void> Function() onRefresh;
  final bool isEmpty;
  final String emptyMessage;
  final IconData emptyIcon;
  final Widget child;

  const RefreshableList({
    super.key,
    required this.onRefresh,
    required this.isEmpty,
    required this.child,
    this.emptyMessage = 'Nothing here yet.',
    this.emptyIcon = Icons.inbox_outlined,
  });

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: isEmpty
          ? ListView(
              children: [
                const SizedBox(height: 120),
                EmptyState(message: emptyMessage, icon: emptyIcon),
              ],
            )
          : child,
    );
  }
}

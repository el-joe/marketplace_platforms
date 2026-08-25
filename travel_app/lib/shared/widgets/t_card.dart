import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

class TCard extends StatelessWidget {
  const TCard({super.key, required this.child, this.padding, this.onTap});

  final Widget child;
  final EdgeInsetsGeometry? padding;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.card,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: padding ?? const EdgeInsets.all(14),
          child: child,
        ),
      ),
    );
  }
}

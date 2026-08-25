import 'package:flutter/material.dart';

/// Temporary placeholder for features not yet built out beyond the
/// auth + dashboard template. Replace each with a real screen when its
/// feature is implemented.
class PlaceholderScreen extends StatelessWidget {
  const PlaceholderScreen({super.key, required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: Center(
        child: Text('$title — coming soon', style: const TextStyle(color: Colors.grey)),
      ),
    );
  }
}

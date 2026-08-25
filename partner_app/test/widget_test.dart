// Smoke test — confirms the app boots to the login screen when unauthenticated.

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:partner_app/main.dart';

void main() {
  testWidgets('App boots and shows the login screen', (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: PartnerApp()));
    await tester.pump();

    expect(find.text('Partner Portal'), findsOneWidget);
  });
}

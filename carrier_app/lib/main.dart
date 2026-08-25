import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/router/app_router.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/auth_provider.dart';
import 'features/notifications/fcm_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  try {
    await Firebase.initializeApp();
    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
    // TODO: run `flutterfire configure` to generate firebase_options.dart and platform config before shipping.
  } catch (_) {
    // App must still run without a configured Firebase project (e.g. local dev).
  }

  runApp(const ProviderScope(child: CarrierApp()));
}

class CarrierApp extends ConsumerStatefulWidget {
  const CarrierApp({super.key});

  @override
  ConsumerState<CarrierApp> createState() => _CarrierAppState();
}

class _CarrierAppState extends ConsumerState<CarrierApp> {
  @override
  void initState() {
    super.initState();
    ref.listenManual(authProvider, (previous, next) {
      if (previous?.status != AuthStatus.authenticated && next.status == AuthStatus.authenticated) {
        FcmService.instance.initialize();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final router = ref.watch(routerProvider);

    return MaterialApp.router(
      title: 'Carrier Portal',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.dark(),
      scaffoldMessengerKey: rootScaffoldMessengerKey,
      routerConfig: router,
    );
  }
}

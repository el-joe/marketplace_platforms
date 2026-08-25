import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import '../auth/auth_repository.dart';

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  // Background messages are handled by the OS notification tray; nothing to
  // do here beyond letting Firebase deliver the payload.
}

class FcmService {
  FcmService._();
  static final FcmService instance = FcmService._();

  final _messaging = FirebaseMessaging.instance;
  final _authRepository = AuthRepository();
  String? _registeredToken;

  Future<void> initialize() async {
    try {
      await _messaging.requestPermission(alert: true, badge: true, sound: true);

      final token = await _messaging.getToken();
      if (token != null) {
        _registeredToken = token;
        await _authRepository.registerDeviceToken(token);
      }

      _messaging.onTokenRefresh.listen((newToken) {
        _registeredToken = newToken;
        _authRepository.registerDeviceToken(newToken).catchError((_) {});
      });

      FirebaseMessaging.onMessage.listen((message) {
        debugPrint('Foreground FCM message: ${message.notification?.title}');
      });
    } catch (e) {
      // No configured Firebase project in this environment — the app must
      // still run without push notifications.
      debugPrint('FCM initialization failed: $e');
    }
  }

  Future<void> unregister() async {
    try {
      if (_registeredToken != null) {
        await _authRepository.removeDeviceToken(_registeredToken!);
      }
    } catch (_) {
      // Best-effort — logout should not fail because of this.
    }
  }
}

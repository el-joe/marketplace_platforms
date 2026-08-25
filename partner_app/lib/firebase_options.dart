// GENERATED PLACEHOLDER — NOT a real `flutterfire configure` output.
//
// This file exists so the app compiles and runs without a configured
// Firebase project. `main.dart` wraps `Firebase.initializeApp()` in a
// try/catch, so push notifications are simply unavailable until this file
// is replaced.
//
// Before shipping: delete this file and run
//   flutterfire configure
// from the `partner_app` directory (requires an authenticated `firebase`
// CLI and a real Firebase project) to generate the real platform options.

import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart' show TargetPlatform, defaultTargetPlatform, kIsWeb;

class DefaultFirebaseOptions {
  static FirebaseOptions get currentPlatform {
    if (kIsWeb) return web;
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return android;
      case TargetPlatform.iOS:
        return ios;
      default:
        throw UnsupportedError(
          'DefaultFirebaseOptions have not been configured for this platform — '
          'run `flutterfire configure` to generate real values.',
        );
    }
  }

  static const web = FirebaseOptions(
    apiKey: 'placeholder',
    appId: 'placeholder',
    messagingSenderId: 'placeholder',
    projectId: 'placeholder',
  );

  static const android = FirebaseOptions(
    apiKey: 'placeholder',
    appId: 'placeholder',
    messagingSenderId: 'placeholder',
    projectId: 'placeholder',
  );

  static const ios = FirebaseOptions(
    apiKey: 'placeholder',
    appId: 'placeholder',
    messagingSenderId: 'placeholder',
    projectId: 'placeholder',
    iosBundleId: 'com.example.partnerApp',
  );
}

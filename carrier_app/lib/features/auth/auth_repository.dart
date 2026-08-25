import 'dart:io';

import '../../core/api/api_client.dart';
import '../../core/auth/token_storage.dart';
import '../../shared/models/supervisor.dart';

class AuthRepository {
  final ApiClient _client = ApiClient.instance;

  /// Performs the login call and persists the returned token pair, returning
  /// the authenticated supervisor.
  Future<Supervisor> login({
    required String email,
    required String password,
    String? fcmToken,
  }) async {
    late Supervisor supervisor;
    late String accessToken;
    late String refreshToken;

    await _client.request<void>(
      (dio) => dio.post('/auth/login', data: {
        'email': email,
        'password': password,
        if (fcmToken != null) 'fcm_token': fcmToken,
        if (fcmToken != null) 'platform': Platform.isIOS ? 'ios' : 'android',
      }),
      parse: (data) {
        final payload = data as Map<String, dynamic>;
        supervisor = Supervisor.fromJson(payload['supervisor'] as Map<String, dynamic>);
        accessToken = payload['access_token'] as String;
        refreshToken = payload['refresh_token'] as String;
      },
    );

    await TokenStorage.instance.saveTokens(accessToken: accessToken, refreshToken: refreshToken);
    return supervisor;
  }

  Future<Supervisor> me() {
    return _client.request<Supervisor>(
      (dio) => dio.get('/auth/me'),
      parse: (data) => Supervisor.fromJson((data as Map<String, dynamic>)['supervisor'] as Map<String, dynamic>),
    );
  }

  Future<void> logout() async {
    try {
      await _client.request<void>((dio) => dio.post('/auth/logout'), parse: (_) {});
    } finally {
      await TokenStorage.instance.clear();
    }
  }

  Future<void> registerDeviceToken(String token) {
    return _client.request<void>(
      (dio) => dio.post('/auth/device-token', data: {
        'token': token,
        'platform': Platform.isIOS ? 'ios' : 'android',
      }),
      parse: (_) {},
    );
  }

  Future<void> removeDeviceToken() {
    return _client.request<void>((dio) => dio.delete('/auth/device-token'), parse: (_) {});
  }
}

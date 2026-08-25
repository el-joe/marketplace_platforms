import 'dart:io';

import '../../core/api/api_client.dart';
import '../../core/auth/token_storage.dart';
import '../../shared/models/vendor_admin.dart';

class AuthRepository {
  final ApiClient _client = ApiClient.instance;

  /// `POST /auth/login` — response is `{ success, access_token, token_type,
  /// expires_in }` (token fields sit at the top level, not inside `data`).
  Future<VendorAdmin> login({required String email, required String password}) async {
    final response = await _client.dio.post('/auth/login', data: {'email': email, 'password': password});
    final accessToken = response.data['access_token'] as String;
    await TokenStorage.instance.saveAccessToken(accessToken);
    return me();
  }

  Future<VendorAdmin> me() {
    return _client.request<VendorAdmin>(
      (dio) => dio.get('/auth/me'),
      parse: (data) => VendorAdmin.fromJson(data as Map<String, dynamic>),
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

  Future<void> removeDeviceToken(String token) {
    return _client.request<void>(
      (dio) => dio.delete('/auth/device-token', data: {'token': token}),
      parse: (_) {},
    );
  }
}

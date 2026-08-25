import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// The Partner API (tymon/jwt-auth) issues a single bearer access token —
/// there is no separate refresh token. `POST /auth/refresh-token` is called
/// with the *current* (possibly soon-to-expire) token as the Authorization
/// header and returns a fresh token to replace it.
class TokenStorage {
  TokenStorage._();
  static final TokenStorage instance = TokenStorage._();

  final _storage = const FlutterSecureStorage();

  static const _accessTokenKey = 'partner_access_token';

  Future<void> saveAccessToken(String accessToken) async {
    await _storage.write(key: _accessTokenKey, value: accessToken);
  }

  Future<String?> get accessToken => _storage.read(key: _accessTokenKey);

  Future<void> clear() async {
    await _storage.delete(key: _accessTokenKey);
  }

  Future<bool> get hasToken async => (await accessToken) != null;
}

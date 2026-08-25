import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/agency.dart';
import '../api/api_client.dart';
import '../api/endpoints.dart';

class AuthState {
  const AuthState({this.agency, this.isLoading = false, this.error});

  final Agency? agency;
  final bool isLoading;
  final String? error;

  bool get isAuthenticated => agency != null;

  AuthState copyWith({Agency? agency, bool? isLoading, String? error}) => AuthState(
        agency: agency ?? this.agency,
        isLoading: isLoading ?? this.isLoading,
        error: error,
      );
}

class AuthNotifier extends StateNotifier<AuthState> {
  AuthNotifier(this._ref) : super(const AuthState());

  final Ref _ref;

  Future<bool> login(String email, String password) async {
    state = state.copyWith(isLoading: true, error: null);
    final dio = _ref.read(apiClientProvider);
    final tokenStorage = _ref.read(tokenStorageProvider);

    try {
      final response = await dio.post(Endpoints.login, data: {
        'email': email,
        'password': password,
      });
      final token = response.data['data']?['token'] ?? response.data['token'];
      if (token == null) throw Exception('No token in response');
      await tokenStorage.saveToken(token as String);

      final me = await dio.get(Endpoints.me);
      final agencyJson = (me.data['data'] ?? me.data) as Map<String, dynamic>;
      state = AuthState(agency: Agency.fromJson(agencyJson));
      return true;
    } on DioException catch (e) {
      final message = e.response?.data is Map
          ? (e.response?.data['message'] as String? ?? 'Login failed')
          : 'Login failed';
      state = state.copyWith(isLoading: false, error: message);
      return false;
    } catch (e) {
      state = state.copyWith(isLoading: false, error: 'Login failed');
      return false;
    }
  }

  Future<void> logout() async {
    final dio = _ref.read(apiClientProvider);
    final tokenStorage = _ref.read(tokenStorageProvider);
    try {
      await dio.post(Endpoints.logout);
    } catch (_) {
      // ignore network errors on logout
    }
    await tokenStorage.clear();
    state = const AuthState();
  }

  Future<void> tryRestoreSession() async {
    final tokenStorage = _ref.read(tokenStorageProvider);
    final token = await tokenStorage.readToken();
    if (token == null) return;

    state = state.copyWith(isLoading: true);
    final dio = _ref.read(apiClientProvider);
    try {
      final me = await dio.get(Endpoints.me);
      final agencyJson = (me.data['data'] ?? me.data) as Map<String, dynamic>;
      state = AuthState(agency: Agency.fromJson(agencyJson));
    } catch (_) {
      await tokenStorage.clear();
      state = const AuthState();
    }
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>(
  (ref) => AuthNotifier(ref),
);

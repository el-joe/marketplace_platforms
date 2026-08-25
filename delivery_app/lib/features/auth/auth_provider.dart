import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/auth/token_storage.dart';
import '../../shared/models/agent.dart';
import 'auth_repository.dart';

final authRepositoryProvider = Provider((ref) => AuthRepository());

enum AuthStatus { unknown, authenticated, unauthenticated }

class AuthState {
  final AuthStatus status;
  final Agent? agent;

  const AuthState({required this.status, this.agent});

  const AuthState.unknown() : this(status: AuthStatus.unknown);

  AuthState copyWith({AuthStatus? status, Agent? agent}) =>
      AuthState(status: status ?? this.status, agent: agent ?? this.agent);
}

class AuthNotifier extends StateNotifier<AuthState> {
  final AuthRepository _repository;

  AuthNotifier(this._repository) : super(const AuthState.unknown()) {
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    final hasTokens = await TokenStorage.instance.hasTokens;
    if (!hasTokens) {
      state = const AuthState(status: AuthStatus.unauthenticated);
      return;
    }
    try {
      final agent = await _repository.me();
      state = AuthState(status: AuthStatus.authenticated, agent: agent);
    } on ApiException {
      await TokenStorage.instance.clear();
      state = const AuthState(status: AuthStatus.unauthenticated);
    }
  }

  Future<void> login(String emailOrPhone, String password, {String? fcmToken}) async {
    final agent = await _repository.login(
      emailOrPhone: emailOrPhone,
      password: password,
      fcmToken: fcmToken,
    );
    state = AuthState(status: AuthStatus.authenticated, agent: agent);
  }

  Future<void> refreshAgent() async {
    if (state.status != AuthStatus.authenticated) return;
    final agent = await _repository.me();
    state = state.copyWith(agent: agent);
  }

  Future<void> logout() async {
    await _repository.logout();
    state = const AuthState(status: AuthStatus.unauthenticated);
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>(
  (ref) => AuthNotifier(ref.watch(authRepositoryProvider)),
);

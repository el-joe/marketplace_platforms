import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/auth/token_storage.dart';
import '../../shared/models/vendor_admin.dart';
import 'auth_repository.dart';

final authRepositoryProvider = Provider((ref) => AuthRepository());

enum AuthStatus { unknown, authenticated, unauthenticated }

class AuthState {
  final AuthStatus status;
  final VendorAdmin? admin;

  const AuthState({required this.status, this.admin});

  const AuthState.unknown() : this(status: AuthStatus.unknown);

  AuthState copyWith({AuthStatus? status, VendorAdmin? admin}) =>
      AuthState(status: status ?? this.status, admin: admin ?? this.admin);
}

class AuthNotifier extends StateNotifier<AuthState> {
  final AuthRepository _repository;

  AuthNotifier(this._repository) : super(const AuthState.unknown()) {
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    final hasToken = await TokenStorage.instance.hasToken;
    if (!hasToken) {
      state = const AuthState(status: AuthStatus.unauthenticated);
      return;
    }
    try {
      final admin = await _repository.me();
      state = AuthState(status: AuthStatus.authenticated, admin: admin);
    } on ApiException {
      await TokenStorage.instance.clear();
      state = const AuthState(status: AuthStatus.unauthenticated);
    }
  }

  Future<void> login(String email, String password) async {
    final admin = await _repository.login(email: email, password: password);
    state = AuthState(status: AuthStatus.authenticated, admin: admin);
  }

  Future<void> logout() async {
    await _repository.logout();
    state = const AuthState(status: AuthStatus.unauthenticated);
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>(
  (ref) => AuthNotifier(ref.watch(authRepositoryProvider)),
);

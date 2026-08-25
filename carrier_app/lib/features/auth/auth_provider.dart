import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/auth/token_storage.dart';
import '../../shared/models/supervisor.dart';
import 'auth_repository.dart';

final authRepositoryProvider = Provider((ref) => AuthRepository());

enum AuthStatus { unknown, authenticated, unauthenticated }

class AuthState {
  final AuthStatus status;
  final Supervisor? supervisor;

  const AuthState({required this.status, this.supervisor});

  const AuthState.unknown() : this(status: AuthStatus.unknown);

  AuthState copyWith({AuthStatus? status, Supervisor? supervisor}) =>
      AuthState(status: status ?? this.status, supervisor: supervisor ?? this.supervisor);
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
      final supervisor = await _repository.me();
      state = AuthState(status: AuthStatus.authenticated, supervisor: supervisor);
    } on ApiException {
      await TokenStorage.instance.clear();
      state = const AuthState(status: AuthStatus.unauthenticated);
    }
  }

  Future<void> login(String email, String password, {String? fcmToken}) async {
    final supervisor = await _repository.login(email: email, password: password, fcmToken: fcmToken);
    state = AuthState(status: AuthStatus.authenticated, supervisor: supervisor);
  }

  Future<void> refreshSupervisor() async {
    if (state.status != AuthStatus.authenticated) return;
    final supervisor = await _repository.me();
    state = state.copyWith(supervisor: supervisor);
  }

  Future<void> logout() async {
    await _repository.logout();
    state = const AuthState(status: AuthStatus.unauthenticated);
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>(
  (ref) => AuthNotifier(ref.watch(authRepositoryProvider)),
);

/// The current supervisor's permissions — empty when unauthenticated.
final permissionsProvider = Provider<List<String>>((ref) {
  final auth = ref.watch(authProvider);
  return auth.supervisor?.permissions ?? const [];
});

/// A convenience extension so screens can call `permissions.has('x')`.
extension PermissionCheck on List<String> {
  bool has(String permission) => contains(permission);
}

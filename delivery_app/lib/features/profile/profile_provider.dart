import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/agent.dart';
import '../../shared/models/document.dart';
import 'profile_repository.dart';

final profileRepositoryProvider = Provider((ref) => ProfileRepository());

final profileProvider = AsyncNotifierProvider<ProfileNotifier, Agent>(ProfileNotifier.new);

class ProfileNotifier extends AsyncNotifier<Agent> {
  ProfileRepository get _repository => ref.read(profileRepositoryProvider);

  @override
  Future<Agent> build() => _repository.getProfile();

  Future<void> update({String? vehicleType, String? vehiclePlate}) async {
    final updated = await _repository.updateProfile(vehicleType: vehicleType, vehiclePlate: vehiclePlate);
    state = AsyncValue.data(updated);
  }
}

final documentsProvider = FutureProvider<List<AgentDocument>>((ref) => ref.read(profileRepositoryProvider).documents());

import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';

import '../../shared/models/assignment.dart';
import 'assignments_repository.dart';

final assignmentsRepositoryProvider = Provider((ref) => AssignmentsRepository());

final assignmentsProvider = AsyncNotifierProvider<AssignmentsNotifier, AssignmentsDashboard>(
  AssignmentsNotifier.new,
);

class AssignmentsNotifier extends AsyncNotifier<AssignmentsDashboard> {
  AssignmentsRepository get _repository => ref.read(assignmentsRepositoryProvider);

  @override
  Future<AssignmentsDashboard> build() => _repository.list();

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _repository.list());
  }
}

final assignmentDetailProvider =
    AsyncNotifierProvider.family<AssignmentDetailNotifier, Assignment, int>(AssignmentDetailNotifier.new);

class AssignmentDetailNotifier extends FamilyAsyncNotifier<Assignment, int> {
  AssignmentsRepository get _repository => ref.read(assignmentsRepositoryProvider);

  @override
  Future<Assignment> build(int arg) => _repository.show(arg);

  Future<Position?> _currentPosition() async {
    try {
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
        return null;
      }
      return await Geolocator.getCurrentPosition();
    } catch (_) {
      return null;
    }
  }

  Future<void> accept() async {
    final updated = await _repository.accept(arg);
    state = AsyncValue.data(updated);
    ref.invalidate(assignmentsProvider);
  }

  Future<void> markPickedUp() async {
    final position = await _currentPosition();
    final updated = await _repository.pickedUp(
      arg,
      latitude: position?.latitude ?? 0,
      longitude: position?.longitude ?? 0,
    );
    state = AsyncValue.data(updated);
    ref.invalidate(assignmentsProvider);
  }

  Future<Assignment> verifyOtp(String otp) async {
    final updated = await _repository.verifyOtp(arg, otp);
    state = AsyncValue.data(updated);
    return updated;
  }

  Future<void> deliver({
    required String otpCode,
    File? proofImage,
    int? codAmountCollected,
    String? discrepancyNote,
  }) async {
    final position = await _currentPosition();
    final updated = await _repository.deliver(
      arg,
      otpCode: otpCode,
      latitude: position?.latitude ?? 0,
      longitude: position?.longitude ?? 0,
      proofImage: proofImage,
      codAmountCollected: codAmountCollected,
      discrepancyNote: discrepancyNote,
    );
    state = AsyncValue.data(updated);
    ref.invalidate(assignmentsProvider);
  }

  Future<void> fail({
    required String failureReason,
    String? failureNotes,
    String? customerRejectionReason,
  }) async {
    final position = await _currentPosition();
    final updated = await _repository.fail(
      arg,
      failureReason: failureReason,
      failureNotes: failureNotes,
      latitude: position?.latitude ?? 0,
      longitude: position?.longitude ?? 0,
      customerRejectionReason: customerRejectionReason,
    );
    state = AsyncValue.data(updated);
    ref.invalidate(assignmentsProvider);
  }
}

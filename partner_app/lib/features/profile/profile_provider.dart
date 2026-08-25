import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'profile_repository.dart';

final profileRepositoryProvider = Provider((ref) => ProfileRepository());

final profileProvider = FutureProvider<Map<String, dynamic>>(
  (ref) => ref.read(profileRepositoryProvider).show(),
);

final profileDocumentsProvider = FutureProvider<List<Map<String, dynamic>>>(
  (ref) => ref.read(profileRepositoryProvider).documents(),
);

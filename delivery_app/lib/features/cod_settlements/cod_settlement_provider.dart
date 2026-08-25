import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/cod_settlement.dart';
import '../../shared/models/paginated.dart';
import 'cod_settlement_repository.dart';

final codSettlementRepositoryProvider = Provider((ref) => CodSettlementRepository());

final currentCodProvider = FutureProvider<CurrentCod>((ref) => ref.read(codSettlementRepositoryProvider).current());

final codSettlementsProvider =
    FutureProvider<Paginated<CodSettlement>>((ref) => ref.read(codSettlementRepositoryProvider).list());

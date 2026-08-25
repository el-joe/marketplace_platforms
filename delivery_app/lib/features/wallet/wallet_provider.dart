import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../shared/models/paginated.dart';
import '../../shared/models/wallet.dart';
import 'wallet_repository.dart';

final walletRepositoryProvider = Provider((ref) => WalletRepository());

final walletProvider = FutureProvider<Wallet>((ref) => ref.read(walletRepositoryProvider).getWallet());

final walletTransactionsProvider =
    FutureProvider<Paginated<WalletTransaction>>((ref) => ref.read(walletRepositoryProvider).getTransactions());

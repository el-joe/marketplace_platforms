import Credits from "@/src/features/noon/profile/credits";
import {
  getWallet,
  getWalletTransactions,
} from "@/src/features/noon/profile/credits/api/wallet.actions";
import { getGiftCardWallet } from "@/src/features/noon/profile/credits/api/gift-card-wallet.actions";

export default async function CreditsPage() {
  const [wallet, transactions, giftCardWallet] = await Promise.all([
    getWallet(),
    getWalletTransactions(),
    getGiftCardWallet(),
  ]);
  return (
    <Credits
      wallet={wallet}
      transactions={transactions.items}
      giftCardWallets={giftCardWallet.wallets}
    />
  );
}

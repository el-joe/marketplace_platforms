import Credits from "@/src/features/noon/profile/credits";
import {
  getWallet,
  getWalletTransactions,
} from "@/src/features/noon/profile/credits/api/wallet.actions";

export default async function CreditsPage() {
  const [wallet, transactions] = await Promise.all([
    getWallet(),
    getWalletTransactions(),
  ]);
  return <Credits wallet={wallet} transactions={transactions.items} />;
}

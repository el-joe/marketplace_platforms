import { getTranslations } from "next-intl/server";
import { ChevronRight } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import Card from "@/src/components/shared/Card";
import Image from "next/image";
import CreditsEmptyState from "./components/empty-state";
import TransactionsList from "./components/transactions-list";
import WithdrawToBank from "./components/withdraw-to-bank";
import AddCreditsModal from "./components/add-credits-modal";
import { Wallet, WalletTransaction } from "./helpers/types";

interface Props {
  wallet: Wallet;
  transactions: WalletTransaction[];
}

export default async function Credits({ wallet, transactions }: Props) {
  const t = await getTranslations("profile");

  const isEmpty = transactions.length === 0;

  return (
    <>
      <div className="flex items-center justify-between">
        <h1 className="text-[28px] font-bold  text-light">
          {t("creditsTitle")}
        </h1>

        <WithdrawToBank
          wallet={wallet}
          trigger={
            <Button
              variant="outline"
              className="rounded-full h-11 px-4 gap-1 text-sm font-medium bg-white"
            >
              {t("withdrawToBank")}
              <ChevronRight className="size-4" />
            </Button>
          }
        />
      </div>

      <Card className="mt-4 overflow-hidden">
        <div className="px-6 py-5">
          <p className="text-sm text-gray">{t("availableBalance")}</p>
          <div className="flex items-center gap-1 mt-2 font-bold">
            <span className="text-2xl">{wallet.currency}</span>
            <span className="text-4xl">{wallet.balance.toFixed(2)}</span>
          </div>
        </div>

        <AddCreditsModal
          trigger={
            <button className="w-full flex items-center justify-between bg-black text-white px-6 h-16 cursor-pointer">
              <span className="flex items-center gap-1 font-semibold">
                {t("redeemGiftcards")}
                <Image
                  src="/images/profile/redeem-giftcards-push.svg"
                  alt={t("redeemGiftcards")}
                  width={32}
                  height={32}
                />
              </span>
              <ChevronRight className="size-5" />
            </button>
          }
        />
      </Card>

      {isEmpty ? (
        <CreditsEmptyState />
      ) : (
        <TransactionsList transactions={transactions} />
      )}
    </>
  );
}

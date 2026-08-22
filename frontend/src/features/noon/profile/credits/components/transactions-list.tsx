import { format } from "date-fns";
import Card from "@/src/components/shared/Card";
import Price from "@/src/components/shared/Price";
import type { WalletTransaction } from "../helpers/types";

type Props = {
  transactions: WalletTransaction[];
};

export default async function TransactionsList({ transactions }: Props) {
  return (
    <Card className="mt-4 divide-y divide-border">
      {transactions.map((transaction) => {
        const isCredit = transaction.type === "credit";

        return (
          <div
            key={transaction.id}
            className="flex items-center justify-between px-6 py-4"
          >
            <div>
              <p className="font-medium">{transaction.description}</p>
              <p className="text-sm text-gray mt-1">
                {format(
                  new Date(transaction.created_at),
                  "dd MMM yyyy, hh:mm a",
                )}
              </p>
            </div>
            <p className={isCredit ? "text-green" : "text-red"}>
              {isCredit ? "+" : "-"}
              <Price
                currentPrice={transaction.amount}
                size="sm"
              />
            </p>
          </div>
        );
      })}
    </Card>
  );
}

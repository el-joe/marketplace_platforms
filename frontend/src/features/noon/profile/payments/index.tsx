import { getTranslations } from "next-intl/server";
import PaymentTransactionCard from "./components/payment-method-card";
import PaymentsEmptyState from "./components/empty-state";
import { getPaymentHistory } from "./api/payments.actions";

export default async function Payments() {
  const t = await getTranslations("profile");

  let history = null;
  try {
    history = await getPaymentHistory();
  } catch {
    // show empty state on error
  }

  const transactions = history?.items ?? [];

  return (
    <>
      <h1 className="text-[28px] font-bold text-light">{t("paymentsTitle")}</h1>
      <p className="mt-1 text-sm text-gray">{t("paymentsSubtitle")}</p>

      {transactions.length === 0 ? (
        <PaymentsEmptyState />
      ) : (
        <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
          {transactions.map((transaction) => (
            <PaymentTransactionCard
              key={transaction.id}
              transaction={transaction}
            />
          ))}
        </div>
      )}
    </>
  );
}

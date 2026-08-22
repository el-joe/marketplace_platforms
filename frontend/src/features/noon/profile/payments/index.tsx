import { getTranslations } from "next-intl/server";
import { getPaymentMethods } from "./api/payments.actions";
import PaymentMethodCard from "./components/payment-method-card";
import PaymentsEmptyState from "./components/empty-state";

export default async function Payments() {
  const t = await getTranslations("profile");
  const paymentMethods = await getPaymentMethods();

  console.log(paymentMethods);

  return (
    <>
      <h1 className="text-[28px] font-bold text-light">{t("paymentsTitle")}</h1>
      <p className="mt-1 text-sm text-gray">{t("paymentsSubtitle")}</p>

      {paymentMethods.length === 0 ? (
        <PaymentsEmptyState />
      ) : (
        <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
          {paymentMethods.map((paymentMethod) => (
            <PaymentMethodCard
              key={paymentMethod.id}
              paymentMethod={paymentMethod}
            />
          ))}
        </div>
      )}
    </>
  );
}

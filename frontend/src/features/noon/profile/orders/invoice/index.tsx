import { getTranslations } from "next-intl/server";
import Card from "@/src/components/shared/Card";
import PrintButton from "./components/print-button";
import type { OrderInvoice, PaymentMethod } from "../helpers/types";

type Props = {
  invoice: OrderInvoice;
};

const paymentMethodLabelKeys: Record<PaymentMethod, string> = {
  cod: "cashOnDelivery",
  card: "cardPayment",
  wallet: "paymentMethodWallet",
  bnpl: "paymentMethodBnpl",
  bank_transfer: "paymentMethodBankTransfer",
};

function formatAmount(amount: number, currency: string) {
  return `${amount.toLocaleString()} ${currency}`;
}

export default async function OrderInvoiceView({ invoice }: Props) {
  const t = await getTranslations("profile");
  const { currency, summary, shipping_address } = invoice;

  return (
    <div className="mx-auto max-w-3xl">
      <div className="mb-4 flex items-center justify-between print:hidden">
        <h1 className="text-2xl font-bold">{t("invoiceTitle")}</h1>
        <PrintButton label={t("printInvoice")} />
      </div>

      <Card className="border border-border p-6">
        <div className="flex flex-wrap items-start justify-between gap-4 border-b border-border pb-4">
          <div>
            <h2 className="text-xl font-bold">{t("invoiceTitle")}</h2>
            <p className="text-sm text-gray">
              {t("invoiceOrderNumber")}: {invoice.order_number}
            </p>
            {invoice.placed_at ? (
              <p className="text-sm text-gray">
                {t("invoicePlacedAt")}:{" "}
                {new Date(invoice.placed_at).toLocaleDateString()}
              </p>
            ) : null}
            <p className="text-sm text-gray">
              {t("invoicePaymentMethod")}:{" "}
              {t(paymentMethodLabelKeys[invoice.payment_method])}
            </p>
          </div>

          {shipping_address ? (
            <div className="text-sm">
              <p className="font-bold">{t("invoiceShippingAddress")}</p>
              {shipping_address.recipient_name ? (
                <p>{shipping_address.recipient_name}</p>
              ) : null}
              {shipping_address.street_address || shipping_address.street ? (
                <p>
                  {shipping_address.street_address ?? shipping_address.street}
                </p>
              ) : null}
              {shipping_address.area || shipping_address.city ? (
                <p>
                  {[shipping_address.area, shipping_address.city]
                    .filter(Boolean)
                    .join(", ")}
                </p>
              ) : null}
              {shipping_address.country ? (
                <p>{shipping_address.country}</p>
              ) : null}
            </div>
          ) : null}
        </div>

        {invoice.sub_orders.map((subOrder) => (
          <div key={subOrder.sub_order_number} className="mt-6">
            <h3 className="font-bold">
              {subOrder.vendor_name} —{" "}
              <span className="font-normal text-gray text-sm">
                {subOrder.sub_order_number}
              </span>
            </h3>

            <table className="mt-2 w-full text-sm">
              <thead>
                <tr className="border-b border-border text-start text-gray">
                  <th className="py-2 text-start font-normal">
                    {t("itemsValueLabel")}
                  </th>
                  <th className="py-2 text-end font-normal">
                    {t("invoiceQuantity")}
                  </th>
                  <th className="py-2 text-end font-normal">
                    {t("invoiceUnitPrice")}
                  </th>
                  <th className="py-2 text-end font-normal">
                    {t("invoiceLineTotal")}
                  </th>
                </tr>
              </thead>
              <tbody>
                {subOrder.items.map((item) => (
                  <tr key={item.sku} className="border-b border-border">
                    <td className="py-2">{item.name_en}</td>
                    <td className="py-2 text-end">{item.quantity}</td>
                    <td className="py-2 text-end">
                      {formatAmount(item.unit_price, currency)}
                    </td>
                    <td className="py-2 text-end">
                      {formatAmount(item.line_total, currency)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ))}

        <div className="mt-6 border-t border-border pt-4 text-sm">
          <div className="flex items-center justify-between">
            <p className="text-gray">{t("invoiceSubtotal")}</p>
            <p>{formatAmount(summary.subtotal, currency)}</p>
          </div>
          {summary.discount > 0 && (
            <div className="mt-2 flex items-center justify-between">
              <p className="text-gray">{t("invoiceDiscount")}</p>
              <p className="text-green">
                -{formatAmount(summary.discount, currency)}
              </p>
            </div>
          )}
          <div className="mt-2 flex items-center justify-between">
            <p className="text-gray">{t("invoiceShipping")}</p>
            <p>{formatAmount(summary.shipping, currency)}</p>
          </div>
          {summary.cod_fee > 0 && (
            <div className="mt-2 flex items-center justify-between">
              <p className="text-gray">{t("invoiceCodFee")}</p>
              <p>{formatAmount(summary.cod_fee, currency)}</p>
            </div>
          )}
          <div className="mt-2 flex items-center justify-between">
            <p className="text-gray">{t("invoiceTax")}</p>
            <p>{formatAmount(summary.tax, currency)}</p>
          </div>
          {summary.warranty_total > 0 && (
            <div className="mt-2 flex items-center justify-between">
              <p className="text-gray">{t("invoiceWarrantyTotal")}</p>
              <p>{formatAmount(summary.warranty_total, currency)}</p>
            </div>
          )}
          {summary.coupon_code_used ? (
            <div className="mt-2 flex items-center justify-between">
              <p className="text-gray">{t("invoiceCouponUsed")}</p>
              <p>{summary.coupon_code_used}</p>
            </div>
          ) : null}
          <div className="mt-4 flex items-center justify-between border-t border-border pt-4 font-bold">
            <p>{t("invoiceTotal")}</p>
            <p>{formatAmount(summary.total, currency)}</p>
          </div>
        </div>
      </Card>
    </div>
  );
}

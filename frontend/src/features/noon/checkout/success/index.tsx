"use client";

import { IPlaceOrderResponse } from "../types/place-order.type";
import SuccessHeader from "./success-header";
import RedirectAlert from "./redirect-alert";
import BankTransferCard from "./bank-transfer-card";
import ShipmentsList from "./shipments-list";
import OrderSummaryCard from "./order-summary-card";
import TrustFeatures from "./trust-features";
import { useRouter } from "@/i18n/navigation";

interface Props {
  orderNumber?: string;
  initialOrderData?: IPlaceOrderResponse | null;
}

export default function CheckoutSuccess({
  orderNumber,
  initialOrderData,
}: Props) {
  let storedOrder: IPlaceOrderResponse | null = null;
  try {
    const raw = sessionStorage.getItem("last_placed_order");
    storedOrder = raw ? JSON.parse(raw) : null;
  } catch {
    storedOrder = null;
  }

  // Fall back to server-fetched initialOrderData whenever sessionStorage is
  // empty/invalid (tab closed and reopened, emailed confirmation link
  // visited later, private browsing) instead of always redirecting home —
  // that previously made bank transfer instructions/upload permanently
  // inaccessible for those customers even though the backend still has the
  // data.
  const order = storedOrder ?? initialOrderData ?? null;
  const router = useRouter();

  if (!order) {
    router.push("/");
    return null;
  }

  return (
    <div className="bg-[#f7f7fa] min-h-[calc(100vh-160px)] py-8 md:py-12">
      <div className="container max-w-304 mx-auto px-4">
        <div className="flex flex-col gap-6">
          {/* Top Success Banner / Header */}
          <SuccessHeader order={order} />

          {/* Payment Redirection Alert (if required) */}
          {order.requires_redirect && order.payment_redirect_url && (
            <RedirectAlert redirectUrl={order.payment_redirect_url} />
          )}

          {/* Main Content 2-Column Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {/* Left Column: Shipment & Sub-order Details (8 cols) */}
            <div className="lg:col-span-8 flex flex-col gap-6">
              {order.bank_transfer_details && (
                <BankTransferCard
                  orderNumber={order.order_number}
                  details={order.bank_transfer_details}
                />
              )}
              <ShipmentsList
                subOrders={order.sub_orders}
                currency={order.currency}
              />
              <TrustFeatures />
            </div>

            {/* Right Column: Order Summary (4 cols) */}
            <div className="lg:col-span-4 sticky top-24 flex flex-col gap-6">
              <OrderSummaryCard order={order} />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

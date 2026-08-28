import { fetchInstance } from "@/src/lib/utils";

type ApiEnvelope<T> = { success: boolean; data: T };

export type AccountDashboard = {
  customer: {
    name: string;
    email: string;
    referral_code: string;
  };
  stats: {
    total_orders: number;
    active_warranties: number;
    wallet_balance: number;
    wallet_currency: string;
    unread_notifications: number;
  };
  recent_orders: {
    order_number: string;
    status: string;
    total: number;
    currency: string;
    placed_at: string;
  }[];
};

/** GET /account/dashboard — account summary stats and recent orders */
export async function getAccountDashboard(): Promise<AccountDashboard> {
  const envelope = await fetchInstance<ApiEnvelope<AccountDashboard>>(
    "/account/dashboard",
  );
  return envelope.data;
}

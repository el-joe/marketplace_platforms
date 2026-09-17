import { fetchInstance } from "@/src/lib/utils";

export interface DisplayName {
  en: string;
  ar: string;
}

export interface IPaymentGateway {
  id: string;
  gateway_code: string;
  type: string;
  display_name: DisplayName;
  image: string;
  is_redirect: boolean;
  supports_cod: boolean;
  fee_pct: number;
  fee_fixed: number;
}

/** Shared: every payment gateway available in the current country. GET /payment-gateways */
export async function getPaymentGateways(): Promise<IPaymentGateway[]> {
  const { data } = await fetchInstance<{
    data: { gateways: IPaymentGateway[] };
  }>("/payment-gateways");
  return data.gateways;
}

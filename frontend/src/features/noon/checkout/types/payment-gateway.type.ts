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

export interface DisplayName {
  en: string;
  ar: string;
}

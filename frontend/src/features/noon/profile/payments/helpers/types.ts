export type ApiEnvelope<T> = {
  success: boolean;
  message?: string;
  data: T;
};

export type PaymentMethodType = "card" | "wallet" | "bank";

export type PaymentMethodBillingAddress = {
  id: number;
  label: string;
  recipient_name: string;
  recipient_phone: string;
  street_address: string;
  area: string;
  full_address: string;
};

export type PaymentMethod = {
  id: string;
  type: PaymentMethodType;
  gateway: string;
  card_brand: string | null;
  card_last4: string | null;
  card_exp_month: number | null;
  card_exp_year: number | null;
  is_default: boolean;
  card_display: string;
  billing_address: PaymentMethodBillingAddress | null;
};

export type AddPaymentMethodPayload = {
  type: PaymentMethodType;
  gateway: string;
  gateway_token: string;
  card_brand?: string;
  card_last4?: string;
  card_exp_month?: number;
  card_exp_year?: number;
  billing_address_id?: number;
  is_default?: boolean;
};

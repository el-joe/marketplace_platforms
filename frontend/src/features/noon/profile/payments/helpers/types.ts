export type ApiEnvelope<T> = {
  success: boolean;
  message?: string;
  data: T;
};

export type PaymentTransactionType =
  | "authorization"
  | "capture"
  | "sale"
  | "refund"
  | "void"
  | "chargeback";

export type PaymentTransactionStatus =
  | "pending"
  | "succeeded"
  | "failed"
  | "cancelled";

export type PaymentTransaction = {
  id: string;
  order_number: string | null;
  type: PaymentTransactionType;
  gateway: string;
  amount: number; // base-currency units — display directly, no division
  currency: string;
  status: PaymentTransactionStatus;
  processed_at: string | null;
  created_at: string;
};

export type PaymentHistoryMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type PaymentHistoryResponse = {
  items: PaymentTransaction[];
  meta: PaymentHistoryMeta;
};

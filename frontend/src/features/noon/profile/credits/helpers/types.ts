export type ApiEnvelope<T> = {
  success: boolean;
  message?: string;
  data: T;
};

export type Wallet = {
  id: string;
  balance: number;
  pending_balance: number;
  currency: string;
  formatted_balance: string;
  is_frozen: boolean;
};

export type WalletTransactionType = "credit" | "debit";

export type WalletTransaction = {
  id: string;
  type: WalletTransactionType;
  amount: number;
  balance_after: number;
  source_type: string;
  description: string;
  created_at: string;
};

export type WalletTransactionsMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type WalletTransactionsResponse = {
  items: WalletTransaction[];
  meta: WalletTransactionsMeta;
};

export type RequestWithdrawalPayload = {
  amount: number;
  bank_name: string;
  bank_iban: string;
};

export type WithdrawalRequest = {
  id: string;
  amount: number;
  status: string;
  created_at: string;
};

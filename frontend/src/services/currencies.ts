import { fetchInstance } from "../lib/utils";

export type CurrencySymbolType = "text" | "image";

export interface ICurrency {
  code: string;
  name: string;
  symbol_type: CurrencySymbolType;
  symbol: string;
  symbol_image_url: string | null;
  decimal_places: number;
}

export interface ICurrenciesResponseBody {
  success: boolean;
  data: ICurrency[];
}

export const getCurrenciesService = () =>
  fetchInstance<ICurrenciesResponseBody>("/currencies", { method: "GET" });

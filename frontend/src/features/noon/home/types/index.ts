import { PageBuilder } from "@/src/components/shared/page-builder/types";
import { CurrencyCode } from "@/src/helpers/get-currency-symbol";

export interface IHome {
  /** `null` when the country has no published home page. */
  page_builder: PageBuilder | null;
  has_page_builder: boolean;
  meta: Meta;
}

export interface Meta {
  country_code: string;
  currency: CurrencyCode;
  locale: string;
}

import { PageBuilder } from "@/src/components/shared/page-builder/types";
import { CurrencyCode } from "@/src/helpers/get-currency-symbol";

export interface IHome {
  page_builder: PageBuilder;
  has_page_builder: boolean;
  meta: Meta;
}

export interface Meta {
  country_code: string;
  currency: CurrencyCode;
  locale: string;
}

import { Product } from "@/types/globals";
import { PageBuilder } from "@/src/components/shared/page-builder/types";

export interface ShopResponse {
  data: {
    page_builder: PageBuilder;
    facets: Facets;
    items: Product[];
    meta: {
      current_page: number;
      last_page: number;
      total: number;
    };
  };
}

export interface LocalizedValue {
  ar: string;
  en: string;
}

export interface FacetValue {
  id: string;
  value: LocalizedValue;
  color_hex: string | null;
  count: number;
}

type FacetAttributeType = "color" | "select" | "number";

export interface FacetAttribute {
  id: string;
  code: string;
  name: LocalizedValue;
  type: FacetAttributeType;
  unit: string | null;
  values: FacetValue[];
}

export interface PriceRange {
  min: number;
  max: number;
}

export interface Facets {
  price_range: PriceRange;
  attributes: FacetAttribute[];
}

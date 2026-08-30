export interface ICategoryNavTree {
  id: string;
  type: Type;
  name: {
    ar: string;
    en: string;
  };
  slug: string;
  parent_id: null | string;
  image_url?: null | string;
  product_count?: number;
  brands?: Brand[];
  children: ICategoryNavTree[];
  icon?: string;
}

export interface Brand {
  id: string;
  name: {
    ar: string;
    en: string;
  };
  slug: string;
  logo_url: null | string;
}

export enum Type {
  Product = "product",
  Travel = "travel",
  ClassiFied = "classified",
}

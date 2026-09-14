import { Country } from "@/types";
import { fetchGlobalInstance } from "../lib/utils";

// export interface ICountry {
//   id: string;
//   iso_code_2: string;
//   iso_code_3: string;
//   name: string;
//   flag_emoji: null | string;
//   phone_prefix: string;
//   currency_code: string;
//   site_code: string;
//   cod_available: boolean;
// }

export const getCountriesService = () =>
  fetchGlobalInstance<{ data: Country[] }>("/countries");

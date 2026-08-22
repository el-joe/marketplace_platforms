import { useLocale as useClientLocale } from "next-intl";
import { getLanguage } from "../helpers/handleRegionAndLocal";

export default function useLocale() {
  return getLanguage(useClientLocale()) as "en" | "ar";
}

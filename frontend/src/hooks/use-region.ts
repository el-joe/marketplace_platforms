import { useLocale } from "next-intl";
import { getRegion } from "../helpers/handleRegionAndLocal";

export default function useRegion() {
  return getRegion(useLocale());
}

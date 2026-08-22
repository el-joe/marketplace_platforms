import { getLocale } from "next-intl/server";
import { getRegion as handleRegion } from "./handleRegionAndLocal";

export default async function getRegion() {
  return handleRegion(await getLocale());
}

import { getLocale as getServerLocale } from "next-intl/server";
import { getLanguage } from "./handleRegionAndLocal";

export default async function getLocale(): Promise<"en" | "ar"> {
  return getLanguage(await getServerLocale()) as "en" | "ar";
}

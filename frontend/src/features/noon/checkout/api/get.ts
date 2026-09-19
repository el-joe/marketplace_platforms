import resolveCookie from "@/src/helpers/resolveCookie";
import { fetchInstance } from "@/src/lib/utils";
import { IMarketerContractResponse } from "../types/marketer-contract.type";

export const getMarketerContract = (marketerId: string) =>
  fetchInstance<IMarketerContractResponse>(`/marketers/${marketerId}/contract`);

/** Fetches the contract PDF with the bearer token (the route is auth-only). */
export const getMarketerContractPdfBlob = async (fileUrl: string) => {
  const token = await resolveCookie("access_token");
  const res = await fetch(fileUrl, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
    cache: "no-store",
  });
  if (!res.ok) throw new Error(res.statusText);
  return res.blob();
};

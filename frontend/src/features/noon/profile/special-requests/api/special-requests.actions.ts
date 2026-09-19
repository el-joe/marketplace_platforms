import { fetchInstance, fetchGlobalInstance } from "@/src/lib/utils";
import resolveCookie from "@/src/helpers/resolveCookie";

export type SpecialRequestStatus = "open" | "in_progress" | "closed";

type NamePair = { name_en: string; name_ar: string | null };

export type SpecialRequest = {
  id: string;
  status: SpecialRequestStatus;
  title_en: string;
  title_ar: string | null;
  description_en: string;
  description_ar: string | null;
  budget: number | null;
  budget_currency: string | null;
  brokers_notified: number;
  created_at: string;
  category: (NamePair & { id: string }) | null;
  city: (NamePair & { id: string }) | null;
};

export type SpecialRequestPayload = {
  category_id: string;
  city_id: string | null;
  title_en: string;
  description_en: string;
  budget?: number | null;
  budget_currency?: string | null;
};

export type CityOption = { id: string; name: string };

export async function getSpecialRequests(status?: SpecialRequestStatus) {
  const qs = status ? `?status=${status}` : "";
  const { data } = await fetchInstance<{
    data: { data: SpecialRequest[]; last_page: number };
  }>(`/special-requests${qs}`);
  return data;
}

export async function getSpecialRequest(id: string) {
  const { data } = await fetchInstance<{ data: SpecialRequest }>(
    `/special-requests/${id}`,
  );
  return data;
}

export async function createSpecialRequest(payload: SpecialRequestPayload) {
  const { data } = await fetchInstance<{
    data: { request_id: string; brokers_notified: number };
  }>("/special-requests", { method: "POST", body: JSON.stringify(payload) });
  return data;
}

export async function closeSpecialRequest(id: string) {
  const { data } = await fetchInstance<{ data: SpecialRequest }>(
    `/special-requests/${id}/close`,
    { method: "PATCH" },
  );
  return data;
}

export async function getCities(): Promise<CityOption[]> {
  const country = await resolveCookie("country");
  const { data } = await fetchGlobalInstance<{ data: CityOption[] }>(
    `/countries/${country}/cities`,
    { method: "GET" },
    true,
  );
  return data;
}

import resolveCookie from "@/src/helpers/resolveCookie";
import { fetchInstance } from "@/src/lib/utils";

/**
 * Customer purchase contracts: the contract of a classified advertisement a customer reviews and accepts before
 * sending an inquiry, and the customer's accepted contracts ("My contracts").
 */

export type CustomerContractFile = {
  name: string;
  mime: string;
  size: number;
  url: string;
  /** True when a PDF preview exists, so the document can be shown inline. */
  inline: boolean;
};

/** The contract to review, as presented by the API. */
export type CustomerContractPayload = {
  template_id: string;
  name: string;
  version: number;
  content_mode: "editor" | "file";
  /** Server-rendered HTML in the active language. */
  content: string;
  content_en: string;
  content_ar: string;
  files: Partial<Record<"ar" | "en", CustomerContractFile>>;
  listing: { number: string; title: string };
  country: { id: string; code: string; name: string } | null;
};

export type ListingContractStatus = {
  required: boolean;
  accepted: boolean;
  can_proceed: boolean;
  contract: CustomerContractPayload | null;
  acceptance: {
    id: string;
    signed_at: string;
    signer_name: string;
    template_version: number;
  } | null;
};

export type SignContractBody = {
  signer_name: string;
  template_id: string;
  version: number;
  language?: "en" | "ar";
};

export type MyContractStatus = "active" | "superseded" | "cancelled";

export type MyContract = {
  id: string;
  status: MyContractStatus;
  purpose: "purchase" | "rental";
  name: string | null;
  template_version: number;
  listing: { id: string | null; number: string | null; title: string | null; slug: string | null };
  category: { id: string; name: string } | null;
  country: { id: string; code: string; name: string } | null;
  signer_name: string;
  signed_at: string;
  language: "en" | "ar";
  content_mode: "editor" | "file";
  has_file: boolean;
  file_name: string | null;
  file_url: string | null;
  /** Authenticated URL of a copy to keep: the signed document, or a printable record of the accepted text. */
  download_url: string | null;
};

export type MyContractDetail = MyContract & {
  content: string;
  content_hash: string;
  signed_ip: string | null;
  cancelled_at: string | null;
};

export type MyContractsPage = {
  items: MyContract[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
};

/** GET /listings/classified/:slug/customer-contract — the contract this advertisement requires, if any. */
export async function getListingCustomerContract(
  slug: string,
): Promise<ListingContractStatus> {
  const { data } = await fetchInstance<{ data: ListingContractStatus }>(
    `/listings/classified/${slug}/customer-contract`,
  );
  return data;
}

/** POST /listings/classified/:slug/customer-contract/sign — records the customer's acceptance. */
export async function signListingCustomerContract(
  slug: string,
  body: SignContractBody,
): Promise<ListingContractStatus> {
  const { data } = await fetchInstance<{ data: ListingContractStatus }>(
    `/listings/classified/${slug}/customer-contract/sign`,
    { method: "POST", body: JSON.stringify({ ...body, accept: true }) },
  );
  return data;
}

/** GET /account/contracts — the customer's accepted contracts, newest first. */
export async function getMyContracts(page = 1): Promise<MyContractsPage> {
  const { data } = await fetchInstance<{ data: MyContractsPage }>(
    `/account/contracts?page=${page}`,
  );
  return data;
}

/** GET /account/contracts/:id — one accepted contract with its frozen text. Undefined when not found. */
export async function getMyContract(
  id: string,
): Promise<MyContractDetail | undefined> {
  try {
    const { data } = await fetchInstance<{ data: MyContractDetail }>(
      `/account/contracts/${id}`,
    );
    return data;
  } catch (error) {
    if (error instanceof Error && "status" in error && error.status === 404) {
      return undefined;
    }
    throw error;
  }
}

/** Fetches a contract document with the bearer token (the routes are auth-only, so a plain link cannot open them). */
export async function getContractFileBlob(fileUrl: string): Promise<Blob> {
  const token = await resolveCookie("access_token");
  const res = await fetch(fileUrl, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
    cache: "no-store",
  });
  if (!res.ok) throw new Error(res.statusText);
  return res.blob();
}

/** Downloads an accepted contract (auth-only route) and saves it under the server's file name. */
export async function downloadMyContract(downloadUrl: string, fallbackName: string): Promise<void> {
  const token = await resolveCookie("access_token");
  const res = await fetch(downloadUrl, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
    cache: "no-store",
  });
  if (!res.ok) throw new Error(res.statusText);

  const disposition = res.headers.get("Content-Disposition") ?? "";
  const name = /filename="?([^";]+)"?/i.exec(disposition)?.[1] ?? fallbackName;
  const objectUrl = URL.createObjectURL(await res.blob());

  const link = document.createElement("a");
  link.href = objectUrl;
  link.download = name;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
}

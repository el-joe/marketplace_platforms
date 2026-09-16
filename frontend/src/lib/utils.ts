import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";
import resolveCookie from "@/src/helpers/resolveCookie";
import { CART_TOKEN_KEY } from "../hooks/use-cart";
import { refreshAccessToken } from "../helpers/refresh-token";
import { getOrCreateSessionId } from "./session-id";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

/** Base URL for the country-agnostic `/v1/...` endpoints (no region segment) — e.g. `/v1/countries`. */
// const country = "uae";
// const country = await resolveCookie("country");

export const apiBaseUrlGlobal = `${process.env.NEXT_PUBLIC_BASE_API_URL}`;
// export const apiBaseUrl = `${apiBaseUrlGlobal}/${country}`;

/**
 * Base URL for the unauthenticated `/api/public/v1/...` endpoints (marketers,
 * marketer profiles, sellers, live streams). Same host as `apiBaseUrlGlobal`,
 * just the `/customer/` segment swapped for `/public/` — falls back to an
 * explicit env var only if that swap ever stops holding.
 */
export const apiPublicBaseUrlGlobal = apiBaseUrlGlobal.replace(
  "/customer/",
  "/public/",
);

export class ApiRequestError extends Error {
  status: number;

  constructor(message: string, status: number) {
    super(message);
    this.status = status;
  }
}

async function fetchWithAuth<T>(
  baseUrl: string,
  path: string,
  init?: RequestInit,
  _isRetry = false,
): Promise<T> {
  const [token, refreshToken, cartToken] = await Promise.all([
    resolveCookie("access_token"),
    resolveCookie("refresh_token"),
    resolveCookie(CART_TOKEN_KEY),
  ]);

  const headers = new Headers(init?.headers);
  if (!(init?.body instanceof FormData)) {
    headers.set("Content-Type", "application/json");
  }
  headers.set("Accept", "application/json");
  if (!!token) {
    headers.set("Authorization", `Bearer ${token}`);
  } else if (refreshToken) {
    const newAccessToken = await refreshAccessToken();
    if (newAccessToken) {
      headers.set("Authorization", `Bearer ${newAccessToken}`);
    }
  }
  if (cartToken) headers.set("X-Cart-Token", cartToken);

  const sessionId = getOrCreateSessionId();
  if (sessionId) headers.set("X-Session-Id", sessionId);

  const res = await fetch(`${baseUrl}${path}`, {
    cache: "no-store",
    ...init,
    headers,
  });

  if (res.status === 401 && !_isRetry) {
    const newAccessToken = await refreshAccessToken();
    if (!newAccessToken) {
      throw new ApiRequestError("Session expired", 401);
    }
    return fetchWithAuth<T>(baseUrl, path, init, true); // retry exactly once
  }

  const body = await res.json().catch(() => null);

  if (!res.ok) {
    throw new ApiRequestError(body?.message ?? res.statusText, res.status);
  }

  return body as T;
}

/**
 * Fetch-based API client, usable from both Server Components (RSC reads)
 * and client-triggered actions alike — resolveCookie resolves the auth
 * token isomorphically the same way axiosInstance.ts does. Mirrors
 * axiosInstance's baseURL/auth resolution but via Next.js's native fetch.
 */
export async function fetchInstance<T>(
  path: string,
  init?: RequestInit,
): Promise<T> {
  const country = await resolveCookie("country");
  return fetchWithAuth<T>(`${apiBaseUrlGlobal}/${country}`, path, init);
}

/** Same as {@link fetchInstance}, but targets the country-agnostic `/v1/...` routes (see `apiBaseUrlGlobal`). */
export function fetchGlobalInstance<T>(
  path: string,
  init?: RequestInit,
  isPublic?: boolean,
): Promise<T> {
  return fetchWithAuth<T>(
    isPublic ? apiPublicBaseUrlGlobal : apiBaseUrlGlobal,
    path,
    init,
  );
}

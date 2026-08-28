import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";
import { region } from "@/src/utils/region";
import resolveCookie from "@/src/helpers/resolveCookie";
import { CART_TOKEN_KEY } from "../hooks/use-cart";
import { refreshAccessToken } from "../helpers/refresh-token";
import { getOrCreateSessionId } from "./session-id";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}


export const apiBaseUrl = `${process.env.NEXT_PUBLIC_BASE_API_URL}/${region}`;
/** Base URL for the country-agnostic `/v1/...` endpoints (no region segment) — e.g. `/v1/countries`. */
export const apiBaseUrlGlobal = `${process.env.NEXT_PUBLIC_BASE_API_URL}`;

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
  headers.set("Content-Type", "application/json");
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
export function fetchInstance<T>(path: string, init?: RequestInit): Promise<T> {
  return fetchWithAuth<T>(apiBaseUrl, path, init);
}

/** Same as {@link fetchInstance}, but targets the country-agnostic `/v1/...` routes (see `apiBaseUrlGlobal`). */
export function fetchGlobalInstance<T>(
  path: string,
  init?: RequestInit,
): Promise<T> {
  return fetchWithAuth<T>(apiBaseUrlGlobal, path, init);
}

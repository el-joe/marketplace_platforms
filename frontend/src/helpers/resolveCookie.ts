import {
  getCookie,
  setCookie as setCookieClient,
  deleteCookie as deleteCookieClient,
} from "cookies-next";
import {
  deleteCookieServer,
  getCookieServer,
  setCookieServer,
} from "./cookieServer";

const isServer = typeof window === "undefined";

export default async function resolveCookie(
  cookieName: string,
): Promise<string> {
  if (isServer) {
    return (await getCookieServer(cookieName)) as string;
  }
  return getCookie(cookieName) as string;
}

type CookieOptions = {
  httpOnly?: boolean;
  secure?: boolean;
  sameSite?: "lax" | "strict" | "none";
  maxAge?: number;
  path?: string;
  domain?: string;
};

export async function setCookie(
  name: string,
  value: string,
  options?: CookieOptions,
): Promise<void> {
  if (isServer) {
    await setCookieServer(name, value, options);
  } else {
    setCookieClient(name, value, options);
  }
}

export async function deleteCookie(
  name: string,
  options?: Pick<CookieOptions, "path" | "domain">,
): Promise<void> {
  if (isServer) {
    await deleteCookieServer(name);
  } else {
    await deleteCookieClient(name, options);
  }
}

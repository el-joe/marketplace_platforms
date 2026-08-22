"use server";
import { ResponseCookie } from "next/dist/compiled/@edge-runtime/cookies";
import { cookies } from "next/headers";
export const getCookieServer = async (
  cookieName: string,
): Promise<string | undefined> => {
  const storedCookie = await cookies();
  const cookie = storedCookie.get(cookieName)?.value;
  return cookie;
};

export const setCookieServer = async (
  name: string,
  value: string,
  options: Partial<ResponseCookie> | undefined,
): Promise<void> => {
  const storedCookie = await cookies();
  storedCookie.set(name, value, options);
};

export const deleteCookieServer = async (name: string): Promise<void> => {
  const storedCookie = await cookies();
  storedCookie.delete(name);
};

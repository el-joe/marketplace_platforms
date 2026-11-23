import { ICustomerProfile } from "@/types";
import { loginFormValues } from "../components/shared/auth/login/schema";
import { apiBaseUrlGlobal, fetchInstance } from "../lib/utils";
import { registerFormValues } from "../components/shared/auth/register/schema";
import resolveCookie from "../helpers/resolveCookie";

interface IAuthResponseBody {
  success: boolean;
  message: string;
  data: {
    customer: ICustomerProfile;
    access_token: string;
    refresh_token: string;
    token_type: string;
    expires_in: number;
  };
}

export const loginService = (body: loginFormValues) =>
  fetchInstance<IAuthResponseBody>("/auth/login", {
    method: "POST",
    body: JSON.stringify(body),
  });
export const registerService = (body: registerFormValues) => {
  const referralCode =
    typeof window !== "undefined" ? localStorage.getItem("_mkt_ref") : null;

  return fetchInstance<IAuthResponseBody>("/auth/register", {
    method: "POST",
    body: JSON.stringify({
      ...body,
      ...(referralCode ? { referral_code: referralCode } : {}),
    }),
  });
};

export const refreshTokenService = async (refreshToken: string) => {
  const country = await resolveCookie("country");

  return fetch(`${apiBaseUrlGlobal}/${country || "uae"}/auth/refresh-token`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ refresh_token: refreshToken }),
    cache: "no-store",
  });
};

export type ForgotPasswordBody = {
  email_or_phone: string;
  /** Storefront language — the email and its reset link use it. */
  locale: "ar" | "en";
};
export type ResetPasswordBody = {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
};

/** Always resolves the same way whether or not the account exists (no enumeration). */
export const forgotPasswordService = (body: ForgotPasswordBody) =>
  fetchInstance<{ success: boolean; message: string }>(
    "/auth/forgot-password",
    { method: "POST", body: JSON.stringify(body) },
  );

export const resetPasswordService = (body: ResetPasswordBody) =>
  fetchInstance<{ success: boolean; message: string }>("/auth/reset-password", {
    method: "POST",
    body: JSON.stringify(body),
  });

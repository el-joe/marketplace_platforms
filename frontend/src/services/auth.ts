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

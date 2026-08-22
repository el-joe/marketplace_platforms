import { ICustomerProfile } from "@/types";
import { loginFormValues } from "../components/shared/auth/login/schema";
import { apiBaseUrl, fetchInstance } from "../lib/utils";
import { registerFormValues } from "../components/shared/auth/register/schema";

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
export const registerService = (body: registerFormValues) =>
  fetchInstance<IAuthResponseBody>("/auth/register", {
    method: "POST",
    body: JSON.stringify(body),
  });

export const refreshTokenService = (refreshToken: string) =>
  fetch(`${apiBaseUrl}/auth/refresh-token`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ refresh_token: refreshToken }),
    cache: "no-store",
  });

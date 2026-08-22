import { CART_TOKEN_KEY } from "../hooks/use-cart";
import { refreshTokenService } from "../services/auth";
import resolveCookie, { deleteCookie, setCookie } from "./resolveCookie";

let refreshPromise: Promise<string | null> | null = null;

export async function refreshAccessToken(): Promise<string | null> {
  if (!refreshPromise) {
    refreshPromise = (async () => {
      const refreshToken = await resolveCookie("refresh_token");

      if (!refreshToken) return null;

      const res = await refreshTokenService(refreshToken);

      if (!res.ok) {
        await deleteCookie("refresh_token");
        return null;
      }

      const { access_token, refresh_token, expires_in } = await res
        .json()
        .then((r) => r.data);

      await setCookie("access_token", access_token, {
        maxAge: expires_in,
      });
      if (refresh_token) {
        await setCookie("refresh_token", refresh_token, {
          maxAge: 30 * 24 * 60 * 60,
        });
      }
      await deleteCookie(CART_TOKEN_KEY);
      return access_token as string;
    })()
      .catch(async () => {
        // await deleteCookie("refresh_token");
        return null;
      })
      .finally(() => {
        refreshPromise = null;
      });
  }

  return refreshPromise;
}

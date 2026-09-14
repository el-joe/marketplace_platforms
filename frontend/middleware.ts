import createMiddleware from "next-intl/middleware";
import { getRouting } from "./i18n/routing";
import { NextRequest, NextResponse } from "next/server";
import { refreshAccessToken } from "./src/helpers/refresh-token";
import { getCountriesService } from "./src/services/countries";

const PROTECTED_ROUTES = [
  "/profile",
  "/wishlist",
  "/checkout",
  "/returns",
  "/orders",
  "/my-bookings",
  "/addresses",
  "/payments",
  "/notifications",
  "/security-settings",
  "/qr-code",
];

function isAuthenticated(request: NextRequest): boolean {
  const token = request.cookies.get("access_token");
  return !!token?.value;
}

function isProtectedRoute(pathname: string): boolean {
  // Remove locale prefix to check the base path
  const pathnameArr = pathname.split("/");
  pathnameArr.splice(0, 2);
  const pathWithoutLocale = "/" + pathnameArr.join("/");

  // Check if it's a public route
  return PROTECTED_ROUTES.some(
    (route) =>
      pathWithoutLocale === route || pathWithoutLocale.startsWith(`${route}/`),
  );
}
export async function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;
  const authenticated = isAuthenticated(request);
  const protectedRoute = isProtectedRoute(pathname);
  const accessToken = request.cookies.get("access_token")?.value;
  const refreshToken = request.cookies.get("refresh_token")?.value;
  const pathnameCountry = pathname.split("/")[1].split("-")[0];
  const { data } = await getCountriesService();
  const isSupportedCountry = data.find(
    (c) => c.site_code.toLowerCase() === pathnameCountry.toLocaleLowerCase(),
  );

  let country =
    isSupportedCountry?.site_code || request.cookies.get("country")?.value;

  // Only fetch + set cookie if not already set
  if (!country) {
    try {
      const geoRes = await fetch("https://ipapi.co/json/");
      const geoData = await geoRes.json();
      country = geoData.country_code_iso3.lowercase() ?? "uae";
    } catch {
      country = "uae";
    }
  }
  if (!accessToken && !!refreshToken) {
    await refreshAccessToken();
  }

  if (!authenticated && protectedRoute) {
    return NextResponse.redirect(new URL("/?authDialog=on", request.url));
  }
  const routing = await getRouting();

  const i18nMiddleware = createMiddleware(routing);
  const response = i18nMiddleware(request); // this is the response that actually gets returned
  response.headers.set("x-params", request.nextUrl.searchParams.toString());

  response.cookies.set("country", country as string, {
    path: "/",
    maxAge: 60 * 60 * 24 * 30,
  });

  return response;
}
export const config = {
  // Match all pathnames except for
  // - … if they start with `/api`, `/trpc`, `/_next` or `/_vercel`
  // - … the ones containing a dot (e.g. `favicon.ico`)
  matcher: "/((?!api|trpc|_next|_vercel|.*\\..*).*)",
};

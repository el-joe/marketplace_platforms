import createMiddleware from "next-intl/middleware";
import { getRouting } from "./i18n/routing";
import { NextRequest, NextResponse } from "next/server";
import { refreshAccessToken } from "./src/helpers/refresh-token";
import { getCountriesService } from "./src/services/countries";

const IPINFO_TOKEN = process.env.IPINFO_TOKEN;

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
  "/disputes",
  "/security-settings",
  "/qr-code",
  "/noon-credits",
  "/my-warranties",
  "/warranty-claims",
  "/special-requests",
  "/noon-credits",
  "/gift-cards",
  "/notifications",
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
const UNAVAILABLE_COPY = {
  en: {
    title: "We'll be right back",
    body: "The store is temporarily unavailable while we make some improvements. Please try again in a few minutes.",
  },
  ar: {
    title: "سنعود قريبًا",
    body: "المتجر غير متاح مؤقتًا بسبب أعمال تحسين. يرجى المحاولة مرة أخرى بعد دقائق.",
  },
} as const;

/**
 * Standalone 503 page. Served straight from the proxy because every render
 * (layout, next-intl config) depends on the countries API, so when the backend
 * is down (e.g. `artisan down`) no React page can render at all.
 */
function serviceUnavailableResponse(pathname: string) {
  const lang = pathname.split("/")[1]?.endsWith("-ar") ? "ar" : "en";
  const { title, body } = UNAVAILABLE_COPY[lang];
  const html = `<!doctype html>
<html lang="${lang}" dir="${lang === "ar" ? "rtl" : "ltr"}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>${title}</title>
<style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,sans-serif;background:#f7f7fa;color:#111;text-align:center;padding:24px}
main{max-width:420px}
h1{font-size:1.75rem;margin:0 0 12px}
p{margin:0 0 24px;color:#555;line-height:1.6}
button{background:#111;color:#fff;border:0;border-radius:8px;padding:10px 24px;font-size:1rem;cursor:pointer}
</style>
</head>
<body>
<main>
<h1>${title}</h1>
<p>${body}</p>
<button onclick="location.reload()">${lang === "ar" ? "إعادة المحاولة" : "Try again"}</button>
</main>
</body>
</html>`;

  return new NextResponse(html, {
    status: 503,
    headers: {
      "Content-Type": "text/html; charset=utf-8",
      "Retry-After": "30",
      "Cache-Control": "no-store",
    },
  });
}

export async function proxy(request: NextRequest) {
  const forwardedFor = request.headers.get("x-forwarded-for");
  const clientIp = forwardedFor?.split(",")[0].trim();
  const { pathname } = request.nextUrl;
  const authenticated = isAuthenticated(request);
  const protectedRoute = isProtectedRoute(pathname);
  const accessToken = request.cookies.get("access_token")?.value;
  const refreshToken = request.cookies.get("refresh_token")?.value;
  const pathnameCountry = pathname.split("/")[1].split("-")[0];
  let data: Awaited<ReturnType<typeof getCountriesService>>["data"];
  try {
    ({ data } = await getCountriesService());
  } catch (error) {
    console.error("[proxy] countries API unavailable", error);
    return serviceUnavailableResponse(pathname);
  }
  const isSupportedCountry = data.find(
    (c) => c.site_code.toLowerCase() === pathnameCountry.toLocaleLowerCase(),
  );

  let country =
    isSupportedCountry?.site_code || request.cookies.get("country")?.value;

  // Only fetch + set cookie if not already set
  if (!country) {
    try {
      const geoRes = await fetch(
        `https://api.ipinfo.io/lite/${!!clientIp && clientIp !== "::1" ? clientIp : "me"}?token=${IPINFO_TOKEN}`,
      );
      const geoData = await geoRes.json();
      country =
        data
          .find(
            (c) =>
              c.iso_code_2.toLowerCase() === geoData.country_code.toLowerCase(),
          )
          ?.site_code?.toLowerCase() || "uae";
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
  let routing: Awaited<ReturnType<typeof getRouting>>;
  try {
    routing = await getRouting(country);
  } catch (error) {
    console.error("[proxy] routing config unavailable", error);
    return serviceUnavailableResponse(pathname);
  }

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

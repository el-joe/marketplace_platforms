import { NextIntlClientProvider } from "next-intl";
import { DirectionProvider } from "@base-ui/react/direction-provider";
import ReactQueryProvider from "@/src/providers/ReactQueryProvider";
import { TooltipProvider } from "@/src/components/ui/tooltip";
import ThemeProvider from "@/src/providers/Theme-provider";
import { AuthProvider } from "./auth-provider";
import { NuqsAdapter } from "nuqs/adapters/next/app";
import { CartProvider } from "./cart-provider";
import { WishlistProvider } from "./wishlist-provider";

export default function RootProviders({
  locale,
  children,
}: Readonly<{
  locale: string;
  children: React.ReactNode;
}>) {
  // Without this, every Base UI primitive (Accordion, Dialog, Select, ...)
  // defaults to LTR and stamps `dir="ltr"` on its own root, overriding the
  // `dir="rtl"` set on <html> for Arabic and breaking its layout regardless
  // of the page's locale.
  const direction = locale === "ar" ? "rtl" : "ltr";

  return (
    <NuqsAdapter>
      <NextIntlClientProvider>
        <DirectionProvider direction={direction}>
          <ReactQueryProvider>
            <TooltipProvider>
              <ThemeProvider>
                <CartProvider>
                  <AuthProvider>
                    <WishlistProvider>{children}</WishlistProvider>
                  </AuthProvider>
                </CartProvider>
              </ThemeProvider>
            </TooltipProvider>
          </ReactQueryProvider>
        </DirectionProvider>
      </NextIntlClientProvider>
    </NuqsAdapter>
  );
}

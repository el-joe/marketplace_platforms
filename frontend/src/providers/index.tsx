import { NextIntlClientProvider } from "next-intl";
import ReactQueryProvider from "@/src/providers/ReactQueryProvider";
import { TooltipProvider } from "@/src/components/ui/tooltip";
import ThemeProvider from "@/src/providers/Theme-provider";
import { AuthProvider } from "./auth-provider";
import { NuqsAdapter } from "nuqs/adapters/next/app";
import { CartProvider } from "./cart-provider";
import { WishlistProvider } from "./wishlist-provider";

export default function RootProviders({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <NuqsAdapter>
      <NextIntlClientProvider>
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
      </NextIntlClientProvider>
    </NuqsAdapter>
  );
}

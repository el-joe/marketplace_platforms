"use client";

import { Link } from "@/i18n/navigation";
import Price from "@/src/components/shared/Price";
import { Button } from "@/src/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from "@/src/components/ui/sheet";
import { Spinner } from "@/src/components/ui/spinner";
import useLocale from "@/src/hooks/use-locale";
import { useCartContext } from "@/src/providers/cart-provider";
import {
  ArrowBigRight,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
} from "lucide-react";
import { useTranslations } from "next-intl";
import Image from "next/image";
import { IProductDetails } from "./types";
import { Warranty } from "./types/product-details";
import { useWarrantySelection } from "./warranty-selection-context";
import BoughtTogether from "./BoughtTogether";

type Props = {
  productData: IProductDetails;
};

export default function AddedToCartSheet({ productData }: Props) {
  const t = useTranslations("productView");
  const locale = useLocale();
  const {
    isSheetOpen,
    setIsSheetOpen,
    hadSelectedWarrantyOnAdd,
    setHadSelectedWarrantyAfterAdd,
  } = useWarrantySelection();

  const { updateItemWarranty, isMutating, targetItemMutating } =
    useCartContext();

  const hasWarrantyPlans = (productData?.warranty_plans?.length ?? 0) > 0;
  const showWarrantySection = hasWarrantyPlans && !hadSelectedWarrantyOnAdd;

  const hasBoughtTogether =
    (productData?.frequently_bought_together?.items?.length ?? 0) > 1;

  const productName = productData?.variant.variant_name?.[locale];
  const productImage =
    productData?.product?.images?.[0]?.url ||
    "/images/no-image-available-icon.jpg";

  return (
    <Sheet
      open={isSheetOpen.state}
      onOpenChange={(state) => setIsSheetOpen({ cartItemId: null, state })}
    >
      <SheetContent
        className="w-full sm:max-w-120! p-0 flex flex-col h-full overflow-hidden bg-white gap-0"
        initialFocus={false}
      >
        <SheetHeader className="p-4 pb-3 border-b border-border ">
          <SheetTitle className="sr-only">{t("addedToCart")}</SheetTitle>
          <div className="flex items-center gap-3">
            <Image
              src={productImage}
              alt={productName as string}
              width={76}
              height={76}
              className="object-contain max-h-18"
            />
            <div className="flex-1 min-w-0">
              <h3 className="font-semibold text-gray-900 line-clamp-2 leading-tight">
                {productName}
              </h3>
              <div className="flex items-center gap-1.5 font-semibold mt-1">
                <span>{t("addedToCart")}</span>
                <CheckCircle2 className="size-4  fill-blue text-white shrink-0" />
              </div>
            </div>
          </div>
        </SheetHeader>

        {/* Scrollable body */}
        <div className="flex-1 overflow-y-auto px-4 py-4 space-y-6">
          {/* Warranty Section */}
          {showWarrantySection && (
            <section className="space-y-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <h4 className="text-xs font-bold text-gray-600 uppercase tracking-wider">
                    {t("getAdditionalProtection")}
                  </h4>
                  <span className="bg-red text-white text-[10px] font-extrabold px-1.5 py-0.5 rounded-full uppercase leading-none">
                    {t("newBadge")}
                  </span>
                </div>
                <span className="text-[11px] text-gray-500">
                  {t("quickClaimSupport")}
                </span>
              </div>

              <div className="space-y-3">
                {productData.warranty_plans.map(
                  (warranty: Warranty, idx: number) => (
                    <div
                      key={idx}
                      className={`p-3 rounded-md border cursor-pointer transition-all hover:border-black h-full flex flex-col`}
                    >
                      <div className="flex gap-2 items-center">
                        <Image
                          src={
                            warranty.image_url ||
                            "/images/no-image-available-icon.jpg"
                          }
                          alt="warranty icon"
                          width={47}
                          height={47}
                          className="rounded-md"
                        />
                        <div className="flex-1">
                          <p className="px-2 bg-light-blue text-blue text-sm w-fit mb-1">
                            {warranty.duration_label[locale]}
                          </p>
                          <h4 className="font-semibold text:base lg:text-lg flex items-center">
                            {warranty.name[locale]}
                            {locale === "ar" ? (
                              <ChevronLeft />
                            ) : (
                              <ChevronRight />
                            )}
                          </h4>
                        </div>
                      </div>
                      <ul className="mt-3 gap-1 flex flex-col">
                        {(warranty.features[locale] ?? []).map((benefit, i) => (
                          <li
                            key={i}
                            className="text-xs lg:text-sm text-gray flex items-start"
                          >
                            <ArrowBigRight />
                            {benefit}
                          </li>
                        ))}
                      </ul>
                      <div className="flex justify-between items-center mt-auto">
                        <Price
                          currentPrice={warranty.price}
                          currency={warranty.currency}
                        />
                        <Button
                          variant={"outline"}
                          onClick={async () => {
                            console.log(
                              "isSheetOpen.cartItemId",
                              isSheetOpen.cartItemId,
                            );
                            updateItemWarranty({
                              cartItemId: isSheetOpen.cartItemId || "",
                              warrantyPlanId: warranty.id,
                            }).then(() => setHadSelectedWarrantyAfterAdd(true));
                          }}
                          className={
                            "bg-transparent! px-12 py-2 text-lg text-blue font-bold border-blue"
                          }
                          disabled={isMutating}
                        >
                          {isMutating && targetItemMutating === warranty.id ? (
                            <Spinner />
                          ) : (
                            t("select")
                          )}
                        </Button>
                      </div>
                    </div>
                  ),
                )}
              </div>
            </section>
          )}

          {/* Frequently Bought Together Section */}
          {hasBoughtTogether && (
            <BoughtTogether
              boughtTogetherData={productData.frequently_bought_together}
            />
          )}
        </div>

        {/* Sticky Footer */}
        <div className="border-t border-border bg-white p-4 pt-3 flex flex-col gap-2.5 shrink-0 shadow-lg">
          <Link
            href="/cart"
            className="w-full bg-blue hover:bg-blue-600 text-white font-bold h-11 rounded-xl uppercase text-sm tracking-wider flex items-center justify-center transition-colors"
          >
            {t("viewCart")}
          </Link>
          <Button
            variant="outline"
            size="lg"
            onClick={() => setIsSheetOpen({ state: false, cartItemId: null })}
            className="w-full border-blue text-blue hover:bg-blue-50 font-bold h-11 rounded-xl uppercase text-sm tracking-wider justify-center"
          >
            {t("continueShopping")}
          </Button>
        </div>
      </SheetContent>
    </Sheet>
  );
}

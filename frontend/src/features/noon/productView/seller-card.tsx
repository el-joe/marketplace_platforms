"use client";
import { Separator } from "@/src/components/ui/separator";
import {
  ChevronLeft,
  ChevronRight,
  Redo2Icon,
  ShieldCheckIcon,
  StarIcon,
  StoreIcon,
} from "lucide-react";
import React from "react";
import { IProductDetails } from "./types";
import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";
import CartButton from "./cart-button";
import MoreOffersSheet from "./more-offers-sheet";
import { Button } from "@/src/components/ui/button";
import StanderWarrantyDialog from "./dialogs/stander-warranty-dialog";
import EasyReturnsDialog from "./dialogs/easy-returns-dialog";

type Props = {
  productData: IProductDetails;
};

const SellerCard = ({ productData }: Props) => {
  const t = useTranslations("productView");
  const locale = useLocale();
  const { seller } = productData;

  return (
    <div className="border border-border rounded-md mb-6">
      {/* "sold by" info */}
      <div className="p-4">
        <Link href={`/seller/${seller.id}`}>
          <div className="flex items-center gap-2">
            <div className="flex pace-items-center bg-gray-2 rounded-lg p-2">
              <StoreIcon />
            </div>
            <div className="text-sm">
              <p className="flex hover:text-blue-2 transition">
                {t("soldBy")}
                <span className="font-bold ps-1">{seller.store_name}</span>
                {locale === "ar" ? (
                  <ChevronLeft className="w-5 h-5" />
                ) : (
                  <ChevronRight className="w-5 h-5" />
                )}
              </p>
              {!!seller?.rating_avg ? (
                <div className="flex gap-1 items-center text-green">
                  <StarIcon className="fill-green w-4" />
                  <p>{seller.rating_avg.toFixed(1)}</p>
                  <Separator orientation="vertical" className={"mx-1"} />
                  <p className="text-gray">
                    {seller?.vendor_details?.positive_rating_pct}%{" "}
                    {t("positive")}
                  </p>
                </div>
              ) : (
                <p className="text-gray">{t("noEnoughRatingsToShow")}</p>
              )}
            </div>
          </div>
          <div className="flex flex-col gap-2 items-stretch mt-4">
            <div className="flex items-center justify-between bg-gray-2 py-1 px-2 text-gray text-sm rounded-md">
              <p>{t("itemAsShown")}</p>
              <p className="text-green font-bold">
                {seller.vendor_details?.item_as_shown_pct}%
              </p>
            </div>
            {seller.vendor_details.partner_since_years && (
              <div className="flex items-center justify-between bg-gray-2 py-1 px-2 text-gray text-sm rounded-md">
                <p>{t("partnerSince")}</p>
                <p className="text-green font-bold">
                  {/* {seller.vendor_details.partner_since_years}+ Y */}
                  {seller?.vendor_details?.partner_since_years}+ Y
                </p>
              </div>
            )}
            <div className="flex items-center justify-between bg-gray-2 py-1 px-2 text-gray text-sm rounded-md">
              <p>{t("greatRecentRating")}</p>
            </div>
          </div>{" "}
        </Link>
        {/* more offers */}
        {productData.other_sellers.length && (
          <MoreOffersSheet
            trigger={
              <Button className="py-1 px-2 border border-border flex justify-between items-center rounded-md mt-4 w-full">
                <p className="text-sm text-gray">
                  {t("moreOffersFromOtherSellers")}
                </p>
                {locale === "ar" ? <ChevronLeft /> : <ChevronRight />}
              </Button>
            }
            productData={productData}
          />
        )}
      </div>
      {seller?.vendor_details.warranty_months &&
        seller.vendor_details.easy_returns_enabled &&
        seller.vendor_details.secure_payments_enabled && (
          <>
            <Separator />
            <div className="p-4 text-sm">
              {seller?.vendor_details.warranty_months && (
                <StanderWarrantyDialog
                  brandName={
                    productData.product.brand?.name?.[locale] as string
                  }
                  warrantyYears={
                    seller.vendor_details.warranty_months
                      ? Math.max(
                          1,
                          Math.round(
                            Number(seller.vendor_details.warranty_months) / 12,
                          ),
                        )
                      : 1
                  }
                  trigger={
                    <button
                      type="button"
                      className="flex items-center gap-2 mb-3 cursor-pointer hover:underline text-start"
                    >
                      <ShieldCheckIcon className="w-5 h-5 text-gray shrink-0" />
                      <p>
                        {Number(seller?.vendor_details.warranty_months) >= 12
                          ? t("yearManufacturerWarranty", {
                              years: Math.round(
                                Number(seller?.vendor_details.warranty_months) /
                                  12,
                              ),
                            })
                          : t("monthWarranty", {
                              value: seller.vendor_details.warranty_months,
                            })}
                      </p>
                    </button>
                  }
                />
              )}
              {seller.vendor_details.easy_returns_enabled && (
                <EasyReturnsDialog
                  trigger={
                    <button
                      type="button"
                      className="flex items-center gap-2 mb-3 cursor-pointer hover:underline text-start"
                    >
                      <Redo2Icon className="w-5 h-5 text-gray shrink-0" />
                      <p>{t("easyAndHassleFreeReturns")}</p>
                    </button>
                  }
                />
              )}
              {seller.vendor_details.secure_payments_enabled && (
                <div className="flex items-center gap-2">
                  <ShieldCheckIcon className="w-5 h-5 text-gray shrink-0" />
                  <p>{t("securePayments")}</p>
                </div>
              )}
            </div>
          </>
        )}
      <Separator />
      {/* add to cart button */}

      <div className="p-4 text-sm hidden lg:block">
        <CartButton
          listingId={productData.listing.listing_id}
          hasCustomAttributes={!!productData.product.has_custom_attributes}
          customAttributes={productData.product.custom_attributes ?? []}
        />
      </div>
    </div>
  );
};

export default SellerCard;

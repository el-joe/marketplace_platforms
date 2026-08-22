import { Button } from "@/src/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/src/components/ui/sheet";
import { ReactElement, useState } from "react";
import { IProductDetails } from "./types";
import { useTranslations } from "next-intl";
import Image from "next/image";
import useLocale from "@/src/hooks/use-locale";
import { cn } from "@/src/lib/utils";
import { Badge } from "@/src/components/ui/badge";
import Price from "@/src/components/shared/Price";
import { ChevronLeft, ChevronRight, StarIcon, StoreIcon } from "lucide-react";
import { useRouter } from "@/i18n/navigation";
import { OtherSeller } from "./types/product-details";
import CartButton from "./cart-button";

const MoreOffersSheet = ({
  trigger,
  productData,
}: {
  trigger: ReactElement;
  productData: IProductDetails;
}) => {
  const t = useTranslations("productView");
  const locale = useLocale();
  const [open, setOpen] = useState(false);
  return (
    <Sheet open={open} onOpenChange={(e) => setOpen(e)}>
      <SheetTrigger render={trigger}>Scrollable Content</SheetTrigger>
      <SheetContent initialFocus={false}>
        <SheetHeader>
          <SheetTitle>
            {t("$OffersAvailable", { count: productData.other_sellers.length })}
          </SheetTitle>
          <SheetDescription>
            <div className="flex gap-2">
              <Image
                src={productData?.product?.images[0].url}
                alt={productData?.product?.name[locale] as string}
                width={60}
                height={80}
              />
              <div>
                <p className="text-xs">
                  {productData.product.brand.name[locale]}
                </p>
                <p className="text-black ">
                  {productData.product.name[locale]}
                </p>
              </div>
            </div>
          </SheetDescription>
        </SheetHeader>
        <div className="flex flex-col gap-2 ps-2">
          {productData?.other_sellers.map((seller) => {
            const isSelected =
              productData?.listing?.listing_id === seller?.listing_id;
            return (
              <SheetSellerCard
                key={seller.listing_id}
                seller={seller}
                isSelected={isSelected}
                setOpen={setOpen}
              />
            );
          })}
        </div>
      </SheetContent>
    </Sheet>
  );
};

export default MoreOffersSheet;

const SheetSellerCard = ({
  seller,
  isSelected,
  setOpen,
}: {
  seller: OtherSeller;
  isSelected: boolean;
  setOpen: (state: boolean) => void;
}) => {
  const locale = useLocale();
  const t = useTranslations("productView");
  const router = useRouter();
  return (
    <div
      className={cn(
        "border rounded-lg cursor-pointer overflow-hidden",
        isSelected ? "border-blue" : "border-border",
      )}
      onClick={() => {
        router.push(`/products/${seller.listing_id}`);
        setOpen(false);
      }}
    >
      <div className="flex justify-between items-start mb-4 p-2">
        <div className="flex flex-col gap-1">
          {isSelected && <Badge variant={"blue"}>{t("selected")}</Badge>}
          <Price currentPrice={seller.price} currency={seller.currency} />
          {seller.shipping_badge && (
            <Badge
              style={{
                background: seller?.shipping_badge?.color_hex,
                color: seller?.shipping_badge?.text_color_hex,
              }}
            >
              {seller?.shipping_badge?.label[locale]}
            </Badge>
          )}
        </div>
        <Button>{locale === "ar" ? <ChevronLeft /> : <ChevronRight />}</Button>
      </div>
      <div className="bg-gray-2 corner-se-bevel rounded-se-[26px] w-fit ps-2 pe-6 text-base font-semibold uppercase text-light">
        {t("soldBy")}
      </div>
      <div className="flex justify-between items-center bg-gray-2 p-2">
        <div className="flex-1">
          <p className="flex items-center gap-3 mb-3">
            <StoreIcon className="size-4" />
            <span>{seller.seller_name}</span>
          </p>
          <div className="flex gap-1 items-center">
            <StarIcon className="size-4 fill-green text-green" />
            <p>{seller.seller_rating}</p>
          </div>
        </div>
        <div className="min-w-33" onClick={(e) => e.stopPropagation()}>
          <CartButton listingId={seller.listing_id} />
        </div>
      </div>
    </div>
  );
};

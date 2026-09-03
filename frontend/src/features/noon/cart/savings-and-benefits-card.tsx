import { Link } from "@/i18n/navigation";
import { Button } from "@/src/components/ui/button";
import useLocale from "@/src/hooks/use-locale";
import { useCartContext } from "@/src/providers/cart-provider";
import { ChevronLeft, ChevronRight } from "lucide-react";
import { useTranslations } from "next-intl";
import Image from "next/image";

export default function SavingsAdnBenefitsCard() {
  const { cart } = useCartContext();
  const t = useTranslations("cart");
  const locale = useLocale();
  return (
    <div className="p-4 rounded-[16px] bg-white">
      <h3 className="font-bold mb-3">{t("savingsAndBenefits")}</h3>
      <div className="flex gap-2 items-stretch">
        {cart?.savings_and_benefits?.installments.map((e) => (
          <div
            key={e.sort_order}
            className="bg-gray-2 rounded-lg p-2 w-1/3 flex-1"
          >
            <div className="flex justify-between items-center">
              <Image
                src={e?.logo_url || "/images/no-image-available-icon.jpg"}
                alt={e?.label_en}
                width={54}
                height={44}
                className="w-10 aspect-video"
              />
              {locale === "ar" ? (
                <ChevronLeft size={"16px"} />
              ) : (
                <ChevronRight size={"16px"} />
              )}
            </div>
            <p className="text-xs mt-2">{e?.display_name_en}</p>
          </div>
        ))}
      </div>
      {/* benefits list */}
      {cart?.savings_and_benefits?.card_offers.map((c) => (
        <Link
          href={c.apply_url || "#"}
          key={c.sort_order}
          className="bg-gray-2 rounded-lg p-2 mb-2 mt-3 flex items-center gap-2"
        >
          <Image
            src={c?.card_image_url || "/images/no-image-available-icon.jpg"}
            alt={c?.card_name_en}
            width={50}
            height={40}
            className="w-14 aspect-video h-9"
          />
          <p className="text-xs text-gray">
            {locale === "ar" ? c.label_ar : c.label_en}
          </p>
          <Button className={"ms-auto text-blue text-sm"} variant={"ghost"}>
            {/* {t("apply")} */}
            {locale === "ar" ? c?.apply_label_ar : c?.apply_label_en}
            {locale === "ar" ? <ChevronLeft /> : <ChevronRight />}
          </Button>
        </Link>
      ))}
    </div>
  );
}

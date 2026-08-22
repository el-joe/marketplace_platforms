import { Link } from "@/i18n/navigation";
import Image from "next/image";
import React from "react";
import CarouselProducts from "@/src/components/shared/CarouselProducts";
import { useTranslations } from "next-intl";
import useLocale from "@/src/hooks/use-locale";

export default function EmptyState() {
  const t = useTranslations("cart");
  const locale = useLocale();
  return (
    <div className="flex flex-col gap-8">
      <Link href={"#"}>
        <Image
          src={`/images/empty_cart_${locale}.avif`}
          alt="empty cart"
          width={1216}
          height={253}
        />
      </Link>
      <div className="rounded-[16px] bg-white">
        <CarouselProducts title={t("bestsellersForYou")} />
      </div>
    </div>
  );
}

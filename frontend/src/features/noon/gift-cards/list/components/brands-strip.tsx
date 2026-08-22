import Image from "next/image";
import { useTranslations } from "next-intl";
import { giftCardBrandLogos } from "../../data";

export default function BrandsStrip() {
  const t = useTranslations("giftCards");

  return (
    <section className="my-12 text-center">
      <h2 className="text-3xl font-bold text-light">
        {t("useBalanceAcrossBrands")}
      </h2>
      <div className="mt-6 flex items-center justify-center gap-20 flex-wrap">
        {giftCardBrandLogos.map((logo) => (
          <Image
            key={logo}
            src="/images/brand-logo.avif"
            alt={logo}
            width={76}
            height={54}
          />
        ))}
      </div>
    </section>
  );
}

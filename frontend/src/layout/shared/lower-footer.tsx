import { Link } from "@/i18n/navigation";
import { getTranslations } from "next-intl/server";
import Image from "next/image";
import type { FooterLink } from "@/src/services/footer";

interface Props {
  className?: string;
  paymentMethods?: FooterLink[];
  bottomNavLinks?: FooterLink[];
  locale?: "en" | "ar";
}

const LowerFooter = async ({
  paymentMethods,
  bottomNavLinks,
  locale = "en",
  className,
}: Props) => {
  const t = await getTranslations("footer");
  return (
    <section className={className}>
      <div className="container flex flex-col md:flex-row justify-between gap-2 items-center">
        <p className="text-[13px] text-light">{t("rights")}</p>
        {/* pay methods */}
        {paymentMethods && paymentMethods.length > 0 && (
          <div className="flex items-center gap-4">
            {paymentMethods.map((method) =>
              method.icon ? (
                <Image
                  src={method.icon}
                  key={method.id}
                  alt={method.label[locale] ?? method.label.en ?? ""}
                  width={31}
                  height={20}
                  className=""
                />
              ) : null,
            )}
          </div>
        )}

        <div className="flex items-center justify-center gap-4 flex-wrap">
          {(bottomNavLinks ?? []).map((item) => (
            <Link href={item.url ?? "/"} key={item.id} className="text-sm text-light">
              {item.label[locale] ?? item.label.en ?? ""}
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
};

export default LowerFooter;

import { Link } from "@/i18n/navigation";
import { getTranslations } from "next-intl/server";
import Image from "next/image";

interface Props {
  className?: string;
  paymentMethods?: string[];
}

const navItems = [
  { title: "Careers", href: "/" },
  { title: "Warranty Policy", href: "/" },
  { title: "Sell with us", href: "/" },
  { title: "Terms of Use", href: "/" },
  { title: "Terms of Sale", href: "/" },
  { title: "Privacy Policy", href: "/" },
  { title: "Consumer Rights", href: "/" },
];

const LowerFooter = async ({ paymentMethods, className }: Props) => {
  const t = await getTranslations("footer");
  return (
    <section className={className}>
      <div className="container flex flex-col md:flex-row justify-between gap-2 items-center">
        <p className="text-[13px] text-light">{t("rights")}</p>
        {/* pay methods */}
        {paymentMethods && (
          <div className="flex items-center gap-4">
            {paymentMethods?.map((image, i) => (
              <Image
                src={image}
                key={i}
                alt=""
                width={31}
                height={20}
                className=""
              />
            ))}
          </div>
        )}

        <div className="flex items-center justify-center gap-4 flex-wrap">
          {navItems.map((item, i) => (
            <Link href={item.href} key={i} className="text-sm text-light">
              {item.title}
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
};

export default LowerFooter;

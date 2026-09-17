"use client";
import { Link } from "@/i18n/navigation";
import useLocale from "@/src/hooks/use-locale";
import { useTranslations } from "next-intl";
import Image from "next/image";

const Logo = () => {
  const locale = useLocale();
  const t = useTranslations("pageBuilder");
  return (
    <Link href={"/"} className="w-14 lg:w-20 relative">
      <Image
        src={`/images/noon-logo-${locale}.svg`}
        alt={t("logo")}
        fill
        sizes="100%"
        className="relative!"
      />
    </Link>
  );
};

export default Logo;

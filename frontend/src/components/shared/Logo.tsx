"use client";
import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
import Image from "next/image";

type LogoProps = {
  attribute?: "short" | "full";
};

const logoSrc = {
  short: "/images/logos/logo.webp",
  full: "/images/logos/logo-text.webp",
};

const Logo = ({ attribute = "short" }: LogoProps) => {
  const t = useTranslations("pageBuilder");
  return (
    <Link href={"/"} className="w-14 lg:w-20 relative h-full ">
      <Image
        src={logoSrc[attribute]}
        alt={t("logo")}
        width="80"
        height="80"
        className="object-contain w-full h-full"
      />
    </Link>
  );
};

export default Logo;

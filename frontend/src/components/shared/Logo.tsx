import { Link } from "@/i18n/navigation";
import Image from "next/image";
import React from "react";

type LogoProps = {
  platform?: "noon" | "openSooq";
};

const LOGO_SRC: Record<NonNullable<LogoProps["platform"]>, string> = {
  noon: "https://f.nooncdn.com/s/app/com/noon/design-system/logos/revamp-logo-en-smaller.svg",
  openSooq: "https://f.nooncdn.com/s/app/com/noon/design-system/logos/revamp-logo-en-smaller.svg",
};

const Logo = ({ platform = "noon" }: LogoProps) => {
  return (
    <Link href={"/"} className="w-14 lg:w-20 relative">
      <Image
        src={LOGO_SRC[platform]}
        alt="Logo"
        fill
        sizes="100%"
        className="relative!"
      />
    </Link>
  );
};

export default Logo;

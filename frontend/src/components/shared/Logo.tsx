import { Link } from "@/i18n/navigation";
import Image from "next/image";
import React from "react";

const logos = {
  noon: "https://f.nooncdn.com/s/app/com/noon/design-system/logos/revamp-logo-en-smaller.svg",
  openSooq:
    "https://opensooqui2.os-cdn.com/prod/public/images/osLogo/osLogo.svg",
};

type Props = {
  platform?: keyof typeof logos;
};

const Logo = ({ platform = "noon" }: Props) => {
  return (
    <Link href={"/"} className="w-20 relative">
      <Image
        src={logos[platform]}
        alt="Logo"
        fill
        sizes="100%"
        className="relative!"
      />
    </Link>
  );
};

export default Logo;

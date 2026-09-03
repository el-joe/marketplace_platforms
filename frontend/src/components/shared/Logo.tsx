import { Link } from "@/i18n/navigation";
import Image from "next/image";
import React from "react";

const Logo = () => {
  return (
    <Link href={"/"} className="w-14 lg:w-20 relative">
      <Image
        src={
          "https://f.nooncdn.com/s/app/com/noon/design-system/logos/revamp-logo-en-smaller.svg"
        }
        alt="Logo"
        fill
        sizes="100%"
        className="relative!"
      />
    </Link>
  );
};

export default Logo;

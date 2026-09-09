"use client";
import { Link } from "@/i18n/navigation";
import useLocale from "@/src/hooks/use-locale";
import Image from "next/image";

const Logo = () => {
  const locale = useLocale();
  return (
    <Link href={"/"} className="w-14 lg:w-20 relative">
      <Image
        src={`/images/noon-logo-${locale}.svg`}
        alt="Logo"
        fill
        sizes="100%"
        className="relative!"
      />
    </Link>
  );
};

export default Logo;

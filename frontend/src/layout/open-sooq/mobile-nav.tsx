import { Link } from "@/i18n/navigation";
import React from "react";
import { Button } from "@/src/components/ui/button";
import {
  HomeIcon,
  LayoutGridIcon,
  LucideProps,
  ShoppingCartIcon,
  UserCircleIcon,
} from "lucide-react";
import { getTranslations } from "next-intl/server";
import Image from "next/image";

const MobileNav = async () => {
  const t = await getTranslations("mobileNav");
  return (
    <nav className="fixed bottom-0 w-screen md:hidden z-10 flex items-center shadow-xl bg-white h-15 flex-wrap overflow-auto">
      <NavButton Icon={HomeIcon} text={t("home")} href="/" />
      <NavButton Icon={LayoutGridIcon} text={t("categories")} href="/" />
      <Link href={"/"} className="flex-1">
        <div className="w-5 h-5 mx-auto aspect-square relative">
          <Image
            src={
              "https://f.nooncdn.com/mpcms/EN0001/assets/ce4145f1-814e-437b-aea6-ac2985e47d4a.png"
            }
            alt="eid sale"
            fill
            sizes="100%"
          />
        </div>
      </Link>
      <NavButton Icon={UserCircleIcon} text={t("myAccount")} href="/" />
      <NavButton Icon={ShoppingCartIcon} text={t("cart")} href="/" />
    </nav>
  );
};

export default MobileNav;

type navButtonProps = {
  Icon: React.ForwardRefExoticComponent<
    Omit<LucideProps, "ref"> & React.RefAttributes<SVGSVGElement>
  >;
  text: string;
  href: string;
};

const NavButton = ({ Icon, text, href }: navButtonProps) => {
  return (
    <Link href={href} className="flex-1 flex justify-center">
      <Button variant={"ghost"} className={"flex-col gap-1 items-center"}>
        <Icon className="size-5" />
        <p>{text}</p>
      </Button>
    </Link>
  );
};

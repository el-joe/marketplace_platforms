"use client";
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
import Image from "next/image";
import { useAuthContext } from "@/src/providers/auth-provider";
import { useTranslations } from "next-intl";

const MobileNav = () => {
  const t = useTranslations("mobileNav");
  const { setAuthDialogIsOpen, isLogged } = useAuthContext();
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
      {isLogged ? (
        <NavButton
          Icon={UserCircleIcon}
          text={t("myAccount")}
          href="/profile"
        />
      ) : (
        <NavButton
          Icon={UserCircleIcon}
          text={t("myAccount")}
          onClick={() => setAuthDialogIsOpen(true)}
        />
      )}
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
  href?: string;
  onClick?: () => void;
};

const NavButton = ({ Icon, text, href, onClick }: navButtonProps) => {
  if (!!href) {
    return (
      <Link href={href} className="flex-1 flex justify-center">
        <Button variant={"ghost"} className={"flex-col gap-1 items-center"}>
          <Icon className="size-5" />
          <p>{text}</p>
        </Button>
      </Link>
    );
  } else {
    return (
      <Button
        variant={"ghost"}
        className={"flex-col gap-1 items-center"}
        onClick={onClick}
      >
        <Icon className="size-5" />
        <p>{text}</p>
      </Button>
    );
  }
};

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
import { useCartContext } from "@/src/providers/cart-provider";

const MobileNav = () => {
  const t = useTranslations("mobileNav");
  const { setAuthDialogIsOpen, isLogged } = useAuthContext();
  const { cart } = useCartContext();
  return (
    <nav className="fixed bottom-0 inset-x-0 px-2 md:hidden z-10 flex items-center justify-between shadow-xl bg-white h-15 flex-wrap overflow-auto">
      <NavButton Icon={HomeIcon} text={t("home")} href="/" />
      <NavButton
        Icon={LayoutGridIcon}
        text={t("categories")}
        href="/category"
      />
      <Link href={"/"} className="flex-1s">
        <Image
          src={
            "https://f.nooncdn.com/mpcms/EN0001/assets/ce4145f1-814e-437b-aea6-ac2985e47d4a.png"
          }
          alt="eid sale"
          width={30}
          height={30}
          className="object-contain"
        />
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
      <NavButton
        Icon={ShoppingCartIcon}
        text={t("cart")}
        href="/cart"
        label={cart?.cart.summary.item_count}
      />
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
  label?: string | number;
};

const NavButton = ({ Icon, text, href, onClick, label }: navButtonProps) => {
  if (!!href) {
    return (
      <Link href={href} className="flex-1a flex justify-center">
        <Button
          variant={"ghost"}
          className={"flex-col gap-1 items-center px-0 w-auto"}
        >
          <span className="relative">
            <Icon className="size-5" />
            {!!label && (
              <span className="absolute -top-2.5 -right-2 text-sm rounded-full w-4.5 h-4.5 bg-red text-white leading-[125%]">
                {label}
              </span>
            )}
          </span>
          <p className={"text-[9px]"}>{text}</p>
        </Button>
      </Link>
    );
  } else {
    return (
      <Button
        variant={"ghost"}
        className={"flex-col gap-1 items-center px-0 w-auto"}
        onClick={onClick}
      >
        <span className="relative">
          <Icon className="size-5" />
          {!!label && (
            <span className="absolute -top-2.5 -right-2 text-sm rounded-full w-4.5 h-4.5 bg-red text-white leading-[125%]">
              {label}
            </span>
          )}
        </span>
        <p className={"text-[9px]"}>{text}</p>
      </Button>
    );
  }
};

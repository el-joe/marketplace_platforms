"use client";
import Logo from "@/src/components/shared/Logo";
import { Button } from "@/src/components/ui/button";
import {
  ChevronDownIcon,
  HeartIcon,
  LanguagesIcon,
  LucideProps,
  MapPinIcon,
  Package2Icon,
  ShoppingCartIcon,
} from "lucide-react";
import SearchField from "@/src/components/shared/SearchField";
import { Link } from "@/i18n/navigation";
import useToggleLang from "@/src/hooks/useToggleLang";
import { useTranslations } from "next-intl";
import LocationDialog from "@/src/components/shared/dialogs/LocationDialog";
import CategoriesNav from "./CategoriesNav";
import SideCategoriesList from "./SideCategoriesList";

const Header = () => {
  const t = useTranslations("header");

  const toggleLang = useToggleLang();

  return (
    <header className="md:fixed top-0 w-screen z-20 bg-white">
      {/* main header */}
      <div className="flex items-center justify-center pt-2 md:pb-2 md:h-15 md:bg-main">
        <div className="flex items-center gap-x-2 container flex-wrap">
          {/* side categories list for small screens */}
          <SideCategoriesList />
          {/* logo */}
          <Logo />
          {/* location */}
          <LocationDialog
            triggerButton={
              <Button
                variant={"ghost"}
                className={
                  "font-semibold gap-0 py-2.5 hidden md:inline-flex justify-start"
                }
                title={t("locationButtonLabel")}
              >
                <MapPinIcon className="me-1" />
                {t("other")} .<span className="font-thin"> Dubai</span>
                <ChevronDownIcon />
              </Button>
            }
          />
          {/* search field */}
          <SearchField />
          {/* toggle lang */}
          <HeaderButton
            Icon={LanguagesIcon}
            text={t("otherLang")}
            title={t("switchLangLabel")}
            onClick={toggleLang}
            className="hidden md:inline-flex"
          />
          {/* login button */}
          {/* <AuthDialog
            triggerButton={
              <HeaderButton
                Icon={UserCircleIcon}
                text={t("login")}
                title={t("loginButtonLabel")}
                className="hidden md:inline-flex"
              />
            }
          /> */}

          {/* links */}
          {/* orders link */}
          <HeaderButton
            Icon={Package2Icon}
            text={t("orders")}
            title={t("orderLinkLabel")}
            href="/orders"
            className="hidden md:inline-flex"
          />
          {/* wishlist link */}
          <HeaderButton
            Icon={HeartIcon}
            text={t("wishlist")}
            title={t("wishlistLinkLabel")}
            href="/wishlist"
          />
          {/* cart link */}
          <HeaderButton
            Icon={ShoppingCartIcon}
            text={t("cart")}
            title={t("cartLinkLabel")}
            href="/cart"
            className="hidden md:inline-flex"
          />
          {/* end links */}
          {/* small screen location button */}
          <LocationDialog
            triggerButton={
              <Button
                variant={"ghost"}
                className={
                  "font-semibold gap-0 ps-0 py-2.5 md:hidden justify-start w-full"
                }
                title={t("locationButtonLabel")}
              >
                <MapPinIcon className="me-1" />
                {t("other")} .<span className="font-thin"> Dubai</span>
                <ChevronDownIcon />
              </Button>
            }
          />
        </div>
      </div>
      {/* end main header */}
      {/* categories nav for large screen */}
      <CategoriesNav />
    </header>
  );
};

export default Header;

type buttonProps = {
  Icon: React.ForwardRefExoticComponent<
    Omit<LucideProps, "ref"> & React.RefAttributes<SVGSVGElement>
  >;
  text: string;
  title: string;
  href?: string;
  onClick?: () => void;
  className?: string;
};
const HeaderButton = ({
  Icon,
  text,
  title,
  href,
  className,
  onClick,
}: buttonProps) => {
  if (href) {
    return (
      <Link href={href} className={className}>
        <Button
          variant={"ghost"}
          className={"font-semibold gap-0 py-2.5 px-1 md:px-1.5 lg:px-2.5"}
          title={title}
        >
          <Icon className="me-1 size-6" />
          <span className="hidden lg:block">{text}</span>
        </Button>
      </Link>
    );
  } else {
    return (
      <Button
        variant={"ghost"}
        className={
          "font-semibold gap-0 py-2.5 px-1 md:px-1.5 lg:px-2.5 " + className
        }
        title={title}
        onClick={onClick}
      >
        <Icon className="me-1 size-6" />
        <span className="hidden lg:block">{text}</span>
      </Button>
    );
  }
};

"use client";
import {
  ChevronDownIcon,
  ChevronLeft,
  ChevronRight,
  HeartIcon,
  LanguagesIcon,
  LogOutIcon,
  LucideProps,
  MapPinIcon,
  Package2Icon,
  ShoppingCartIcon,
  UserCircleIcon,
} from "lucide-react";
import { Link } from "@/i18n/navigation";
import { useTranslations } from "next-intl";
// import useToggleLang from "../hooks/useToggleLang";
// import SideCategoriesList from "./noon/SideCategoriesList";
// import Logo from "../components/shared/Logo";
// import LocationDialog from "../components/shared/dialogs/LocationDialog";
// import { Button } from "../components/ui/button";
// import SearchField from "../components/shared/SearchField";
// import CategoriesNav from "./noon/CategoriesNav";
// import { useAuthContext } from "../providers/auth-provider";
// import {
//   DropdownMenu,
//   DropdownMenuContent,
//   DropdownMenuItem,
//   DropdownMenuSeparator,
//   DropdownMenuTrigger,
// } from "../components/ui/dropdown-menu";
// import useLocale from "../hooks/use-locale";
import Image from "next/image";
import { useCartContext } from "@/src/providers/cart-provider";
import useLocale from "@/src/hooks/use-locale";
import useToggleLang from "@/src/hooks/useToggleLang";
import { useAuthContext } from "@/src/providers/auth-provider";
import SideCategoriesList from "./SideCategoriesList";
import Logo from "@/src/components/shared/Logo";
import LocationDialog from "@/src/components/shared/dialogs/LocationDialog";
import { Button } from "@/src/components/ui/button";
import SearchField from "./search-field";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/src/components/ui/dropdown-menu";
import CategoriesNav from "./categories-nav";

const profileDropdownLinks: {
  href: string;
  Icon: string;
  labelKey: string;
}[] = [
  { href: "/orders", Icon: "/images/orders_profile.svg", labelKey: "orders" },
  {
    href: "/returns",
    Icon: "/images/returns_profile.svg",
    labelKey: "returns",
  },
  {
    href: "/addresses",
    Icon: "/images/addresses_profile.svg",
    labelKey: "addresses",
  },
  {
    href: "/payments",
    Icon: "/images/payments_profile.svg",
    labelKey: "payments",
  },
  {
    href: "/noon-credits",
    Icon: "/images/wallet_profile.svg",
    labelKey: "noonCredits",
  },
  {
    href: "/warranty-claims",
    Icon: "/images/warranty-claims_profile.svg",
    labelKey: "warrantyClaims",
  },
  {
    href: "/help",
    Icon: "/images/need-help_profile.svg",
    labelKey: "needHelp",
  },
];

const Header = () => {
  const t = useTranslations("header");
  const locale = useLocale();
  const toggleLang = useToggleLang();
  const { setAuthDialogIsOpen, isLogged, profile, logout } = useAuthContext();

  const splittedName = profile?.name?.split(" ");

  const { cart } = useCartContext();
  return (
    <header className="md:fixed top-0 inset-x-0 z-20 bg-white">
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
          {/* login button if no auth */}
          {!isLogged && (
            <HeaderButton
              Icon={UserCircleIcon}
              text={t("login")}
              title={t("loginButtonLabel")}
              className="hidden md:inline-flex"
              onClick={() => setAuthDialogIsOpen(true)}
            />
          )}
          {/* profile dropdown menu if auth */}
          {isLogged && (
            <DropdownMenu>
              <DropdownMenuTrigger
                render={
                  <HeaderButton
                    Icon={UserCircleIcon}
                    text={t("hi", { name: profile?.name ?? "customer" })}
                    title={t("showProfileOptions")}
                    className="hidden md:inline-flex"
                  />
                }
              />
              <DropdownMenuContent className={"w-auto min-w-3xs"}>
                <Link href={"/profile"}>
                  <DropdownMenuItem className={"py-3 px-4"}>
                    <div className="size-9 rounded-full bg-[#101628]! flex items-center justify-center text-sm text-white font-semibold shrink-0 whitespace-nowrap uppercase py-3 px-4">
                      {splittedName && splittedName.length > 0 ? (
                        `${splittedName[0][0]}${splittedName[1][0]}`
                      ) : (
                        <UserCircleIcon className="size-6" />
                      )}
                    </div>
                    <div>
                      <p className="font-bold">{profile?.name ?? "Customer"}</p>
                      <p className="text-gray text-sm flex items-center">
                        {t("yourProfile")}{" "}
                        {locale === "ar" ? <ChevronLeft /> : <ChevronRight />}
                      </p>
                    </div>
                  </DropdownMenuItem>
                </Link>
                <DropdownMenuSeparator />

                {/* profile menu */}
                {profileDropdownLinks.map(({ href, Icon, labelKey }) => (
                  <Link href={href} key={href}>
                    <DropdownMenuItem className="flex items-center gap-3 py-2.5 px-4">
                      <Image
                        src={Icon}
                        alt="icon"
                        width={24}
                        height={24}
                        className="size-6"
                      />
                      <span className="font-semibold text-[15px]">
                        {" "}
                        {t(labelKey)}
                      </span>
                    </DropdownMenuItem>
                  </Link>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  variant="destructive"
                  onClick={logout}
                  className={"flex items-center gap-3 py-2.5 px-4"}
                >
                  <LogOutIcon />
                  <span className="font-semibold text-[15px]">
                    {" "}
                    {t("signOut")}
                  </span>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          )}

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
            label={cart?.cart?.summary?.item_count}
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
  label?: string | number;
};
const HeaderButton = ({
  Icon,
  text,
  title,
  href,
  className,
  label,
  onClick,
}: buttonProps) => {
  if (href) {
    return (
      <Link href={href} className={className}>
        <Button
          variant={"ghost"}
          className={"font-semibold gap-1 py-2.5 px-1 md:px-1.5 lg:px-2.5"}
          title={title}
        >
          <span className="relative">
            <Icon className="me-1 size-6" />
            {!!label && (
              <span className="absolute -top-2.5 -right-2 rounded-full w-5 h-5 bg-red text-white block">
                {label}
              </span>
            )}
          </span>
          <span className="hidden lg:block">{text}</span>
        </Button>
      </Link>
    );
  } else {
    return (
      <Button
        variant={"ghost"}
        className={
          "font-semibold gap-1 py-2.5 px-1 md:px-1.5 lg:px-2.5 " + className
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

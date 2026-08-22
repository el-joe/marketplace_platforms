"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { Link, usePathname } from "@/i18n/navigation";
import Card from "@/src/components/shared/Card";
import { cn } from "@/src/lib/utils";
import UserSummary from "./user-summary";
import {
  accountNavItems,
  myAccountNavItems,
  NavItem,
  othersNavItems,
} from "./nav-links";

const ProfileSidebar = () => {
  const t = useTranslations("profile");
  return (
    <aside className="space-y-4">
      {/* user summary card */}
      <UserSummary />

      {/* primary nav */}
      <Card className="overflow-hidden p-2">
        {accountNavItems.map((item) => (
          <NavLink key={item.href} item={item} />
        ))}
      </Card>

      {/* my account */}
      <div>
        <p className="text-xs font-bold text-gray uppercase px-1 mb-2">
          {t("myAccount")}
        </p>
        <Card className="overflow-hidden p-2">
          {myAccountNavItems.map((item) => (
            <NavLink key={item.href} item={item} />
          ))}
        </Card>
      </div>

      {/* others */}
      <div>
        <p className="text-xs font-bold text-gray uppercase px-1 mb-2">
          {t("others")}
        </p>
        <Card className="overflow-hidden p-2">
          {othersNavItems.map((item) => (
            <NavLink key={item.href} item={item} />
          ))}
        </Card>
      </div>

      {/* sign out */}
      <Card className="py-2">
        <button
          type="button"
          className="w-full flex items-center gap-3 px-4 py-2.5 cursor-pointer"
        >
          <Image
            src="/images/profile/account-sign-out.svg"
            alt=""
            width={20}
            height={20}
          />
          <span className="text-sm">{t("signOut")}</span>
        </button>
      </Card>
    </aside>
  );
};

export default ProfileSidebar;

const NavLink = ({ item }: { item: NavItem }) => {
  const t = useTranslations("profile");
  const pathname = usePathname();
  const isActive = pathname === item.href;
  return (
    <Link
      href={item.href}
      className={cn(
        "flex items-center gap-3 px-4 py-2.5 rounded-md",
        isActive ? "bg-main/40 font-bold" : "hover:bg-gray-2",
      )}
    >
      <Image src={item.icon} alt="" width={20} height={20} />
      <span className="text-sm">{t(item.labelKey)}</span>
    </Link>
  );
};

"use client";
import Image from "next/image";
import { Link } from "@/i18n/navigation";
import Card from "@/src/components/shared/Card";
import { useLocale, useTranslations } from "next-intl";
import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react";
import { useAuth } from "@/src/hooks/use-auth";

type Props = {};

export default function UserSummary({}: Props) {
  const t = useTranslations("profile");

  const { profile } = useAuth();

  const splitUserName = profile?.name?.split(" ") as string[];

  const completion = 80;

  return (
    <Card className="p-4">
      <div className="flex items-center gap-3">
        <div className="size-12 rounded-full bg-light flex items-center justify-center text-white font-semibold shrink-0 uppercase">
          {splitUserName?.[0][0]}
          {splitUserName?.[1][0]}
        </div>
        <div>
          <p className="font-bold">
            {t("greeting", { name: splitUserName?.[0] })}
          </p>
          <p className="text-sm text-gray">{profile?.email}</p>
        </div>
      </div>
      <div className="mt-4 flex items-center gap-2">
        <p className="text-sm">{t("profileCompletion")}</p>
        <span className="bg-main text-xs font-bold px-2 py-0.5 rounded-full">
          {completion}%
        </span>
      </div>
      <div className="mt-2 h-1.5 rounded-full bg-gray-2 overflow-hidden">
        <div
          className="h-full rounded-full bg-main"
          style={{ width: `${completion}%` }}
        />
      </div>
      <Link
        href="/noon-one"
        className="bg-main/10 mt-4 flex items-center justify-between rounded-md border border-red px-3 py-2.5"
      >
        <span className="text-sm">
          {t("tryNoonOne")}{" "}
          <Image
            src="/images/profile/noon-one-logo.svg"
            alt="noon one"
            width={69}
            height={20}
            className="inline-block align-middle"
          />{" "}
          {t("tryNoonOneForFree")}
        </span>
        <ChevronEndIcon />
      </Link>
    </Card>
  );
}

const ChevronEndIcon = () => {
  const locale = useLocale();

  return locale.endsWith("ar") ? (
    <ChevronLeftIcon className="size-4" />
  ) : (
    <ChevronRightIcon className="size-4" />
  );
};

"use client";
import Card from "@/src/components/shared/Card";
import { useTranslations } from "next-intl";
import { useAuth } from "@/src/hooks/use-auth";

export default function UserSummary() {
  const t = useTranslations("profile");

  const { profile } = useAuth();

  const splitUserName = profile?.name?.split(" ") as string[];

  const completion = profile?.profile_completion?.percentage;

  return (
    <Card className="p-4">
      <div className="flex items-center gap-3">
        <div className="size-12 rounded-full bg-light flex items-center justify-center text-white font-semibold shrink-0 uppercase">
          {splitUserName?.[0]?.[0]}
          {splitUserName?.[1]?.[0]}
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
    </Card>
  );
}


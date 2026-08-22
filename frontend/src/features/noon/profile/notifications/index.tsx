"use client";

import { useTranslations } from "next-intl";
import { MailIcon, MessageCircleIcon, MessageSquareIcon } from "lucide-react";
import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import toast from "react-hot-toast";
import Card from "@/src/components/shared/Card";
import { Switch } from "@/src/components/ui/base-inputs/switch";
import { Skeleton } from "@/src/components/ui/skeleton";
import {
  getNotificationPreferences,
  updateNotificationPreferences,
  type NotificationPreferences,
} from "./api/notifications.actions";

const PREFS_QUERY_KEY = ["notification-preferences"];

export default function Notifications() {
  const t = useTranslations("profile");
  const queryClient = useQueryClient();

  const { data: prefs, isLoading } = useQuery({
    queryKey: PREFS_QUERY_KEY,
    queryFn: getNotificationPreferences,
  });

  const updatePref = useMutation({
    mutationFn: (payload: Partial<NotificationPreferences>) =>
      updateNotificationPreferences(payload),
    onMutate: async (payload) => {
      await queryClient.cancelQueries({ queryKey: PREFS_QUERY_KEY });
      const previous =
        queryClient.getQueryData<NotificationPreferences>(PREFS_QUERY_KEY);
      queryClient.setQueryData<NotificationPreferences>(
        PREFS_QUERY_KEY,
        (old) => (old ? { ...old, ...payload } : old),
      );
      return { previous };
    },
    onError: (_err, _payload, context) => {
      if (context?.previous) {
        queryClient.setQueryData(PREFS_QUERY_KEY, context.previous);
      }
      toast.error(t("preferencesUpdateFailed"));
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: PREFS_QUERY_KEY });
    },
  });

  const toggle =
    (key: keyof NotificationPreferences) => (checked: boolean) => {
      updatePref.mutate({ [key]: checked });
    };

  if (isLoading) {
    return (
      <>
        <h1 className="text-[28px] font-bold  text-light">
          {t("notificationsTitle")}
        </h1>
        <Card className="mt-4 p-6">
          <Skeleton className="h-6 w-48 mb-4" />
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            {[1, 2, 3].map((i) => (
              <Skeleton key={i} className="h-14 rounded-lg" />
            ))}
          </div>
        </Card>
      </>
    );
  }

  return (
    <>
      <h1 className="text-[28px] font-bold  text-light">
        {t("notificationsTitle")}
      </h1>

      <Card className="mt-4 p-6">
        <h2 className="text-lg font-bold">{t("marketingPreferences")}</h2>

        <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
          <PreferenceToggle
            icon={<MailIcon className="size-5" />}
            label={t("email")}
            checked={prefs?.email ?? false}
            onCheckedChange={toggle("email")}
          />
          <PreferenceToggle
            icon={<MessageSquareIcon className="size-5" />}
            label={t("sms")}
            checked={prefs?.sms ?? false}
            onCheckedChange={toggle("sms")}
          />
          <PreferenceToggle
            icon={<MessageCircleIcon className="size-5" />}
            label={t("whatsapp")}
            checked={prefs?.whatsapp ?? false}
            onCheckedChange={toggle("whatsapp")}
          />
        </div>

        <p className="mt-4 text-sm text-gray">
          {t("marketingPreferencesHint")}
        </p>
      </Card>
    </>
  );
}

function PreferenceToggle({
  icon,
  label,
  checked,
  onCheckedChange,
}: {
  icon: React.ReactNode;
  label: string;
  checked: boolean;
  onCheckedChange: (checked: boolean) => void;
}) {
  return (
    <div className="flex h-14 items-center justify-between gap-2 rounded-lg border border-border px-4">
      <span className="flex items-center gap-2 font-medium">
        {icon}
        {label}
      </span>
      <Switch checked={checked} onCheckedChange={onCheckedChange} />
    </div>
  );
}

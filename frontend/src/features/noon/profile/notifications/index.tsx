"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { MailIcon, MessageCircleIcon, MessageSquareIcon } from "lucide-react";
import Card from "@/src/components/shared/Card";
import { Select } from "@/src/components/ui/base-inputs/select";
import { Switch } from "@/src/components/ui/base-inputs/switch";

const languages = [
  { label: "English", value: "en" },
  { label: "العربية", value: "ar" },
];

export default function Notifications() {
  const t = useTranslations("profile");

  const [language, setLanguage] = useState("ar");
  const [email, setEmail] = useState(true);
  const [sms, setSms] = useState(true);
  const [whatsapp, setWhatsapp] = useState(true);

  return (
    <>
      <h1 className="text-[28px] font-bold  text-light">
        {t("notificationsTitle")}
      </h1>

      <Card className="mt-4 p-6">
        <h2 className="text-lg font-bold">{t("receiveCommunicationsIn")}</h2>

        <div className="mt-4 max-w-sm">
          <Select
            label={t("language")}
            items={languages}
            value={language}
            onValueChange={(value) => value && setLanguage(value)}
            triggerClass="h-12! w-full justify-between rounded-lg px-3"
          />
        </div>
      </Card>

      <Card className="mt-4 p-6">
        <h2 className="text-lg font-bold">{t("marketingPreferences")}</h2>

        <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
          <PreferenceToggle
            icon={<MailIcon className="size-5" />}
            label={t("email")}
            checked={email}
            onCheckedChange={setEmail}
          />
          <PreferenceToggle
            icon={<MessageSquareIcon className="size-5" />}
            label={t("sms")}
            checked={sms}
            onCheckedChange={setSms}
          />
          <PreferenceToggle
            icon={<MessageCircleIcon className="size-5" />}
            label={t("whatsapp")}
            checked={whatsapp}
            onCheckedChange={setWhatsapp}
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

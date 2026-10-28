"use client";
import { MessageCircleIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { useStartChat } from "../helpers/use-start-chat";

export default function StartChatButton({
  marketerSlug,
}: {
  marketerSlug: string;
}) {
  const t = useTranslations("chat");
  const { start, isStarting, failed } = useStartChat(marketerSlug);

  return (
    <div className="flex flex-col items-end gap-1">
      <button
        onClick={start}
        disabled={isStarting}
        className="flex items-center gap-1.5 rounded-full bg-primary px-4 py-2 text-xs font-bold text-white transition hover:opacity-90 disabled:opacity-60"
      >
        <MessageCircleIcon className="h-4 w-4" />
        {t("chatWithMarketer")}
      </button>
      {failed && <p className="text-xs text-red">{t("startFailed")}</p>}
    </div>
  );
}

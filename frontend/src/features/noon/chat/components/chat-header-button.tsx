"use client";
import { MessageCircleIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { Button } from "@/src/components/ui/button";
import { useAuthContext } from "@/src/providers/auth-provider";
import { useChatUnread } from "../helpers/use-chat-unread";

export default function ChatHeaderButton() {
  const t = useTranslations("chat");
  const { isLogged } = useAuthContext();
  const unread = useChatUnread();

  if (!isLogged) return null;

  return (
    <Link href="/chats">
      <Button
        variant="ghost"
        className="font-bold gap-1 py-2.5 px-1 md:px-1.5 lg:px-2.5"
        title={t("linkLabel")}
      >
        <span className="relative">
          <MessageCircleIcon className="me-1 size-6" />
          {unread > 0 && (
            <span className="absolute -top-2.5 -right-2 text-sm rounded-full w-4.5 h-4.5 bg-red text-white leading-[125%]">
              {unread > 9 ? "9+" : unread}
            </span>
          )}
        </span>
        <span className="hidden xl:block">{t("title")}</span>
      </Button>
    </Link>
  );
}

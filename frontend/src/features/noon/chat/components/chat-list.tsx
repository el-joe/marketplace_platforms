"use client";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import { getImageURL } from "@/src/helpers/get-image-url";
import { cn } from "@/src/lib/utils";
import { useChatList } from "../helpers/use-chat-list";

export default function ChatList() {
  const t = useTranslations("chat");
  const { data, isLoading, isError } = useChatList();

  if (isLoading) return <p className="p-6 text-sm text-gray-500">{t("loading")}</p>;
  if (isError) return <p className="p-6 text-sm text-red">{t("loadFailed")}</p>;
  if (!data?.length) {
    return <p className="p-6 text-sm text-gray-500">{t("empty")}</p>;
  }

  return (
    <ul className="divide-y divide-border-color rounded-2xl border border-border-color bg-white">
      {data.map((chat) => (
        <li key={chat.id}>
          <Link
            href={`/chats/${chat.id}`}
            className="flex items-center gap-3 p-4 transition hover:bg-gray-2"
          >
            <span className="h-12 w-12 shrink-0 overflow-hidden rounded-full bg-gray-2">
              {chat.marketer.avatar_url && (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                  src={getImageURL(chat.marketer.avatar_url)}
                  alt=""
                  className="h-full w-full object-cover"
                />
              )}
            </span>
            <span className="min-w-0 flex-1">
              <span className="flex items-center justify-between gap-2">
                <span
                  className={cn(
                    "truncate text-sm",
                    chat.has_unread ? "font-bold" : "font-semibold",
                  )}
                >
                  {chat.marketer.name}
                </span>
                {chat.last_message_at && (
                  <span className="shrink-0 text-xs text-gray-500">
                    {new Date(chat.last_message_at).toLocaleDateString()}
                  </span>
                )}
              </span>
              <span
                className={cn(
                  "block truncate text-sm",
                  chat.has_unread ? "font-semibold" : "text-gray-500",
                )}
              >
                {chat.last_message?.body}
              </span>
            </span>
            {chat.has_unread && (
              <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-red" />
            )}
          </Link>
        </li>
      ))}
    </ul>
  );
}

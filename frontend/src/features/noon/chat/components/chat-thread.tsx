"use client";
import { FormEvent, useEffect, useRef, useState } from "react";
import { useTranslations } from "next-intl";
import { ArrowLeftIcon } from "lucide-react";
import { Link } from "@/i18n/navigation";
import { cn } from "@/src/lib/utils";
import { useChatThread } from "../helpers/use-chat-thread";

export default function ChatThread({ id }: { id: string }) {
  const t = useTranslations("chat");
  const { data, isLoading, isError, send, isSending, sendFailed, refreshBadges } =
    useChatThread(id);
  const [draft, setDraft] = useState("");
  const endRef = useRef<HTMLDivElement>(null);

  const messageCount = data?.messages.length;
  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messageCount]);

  // the thread request marks messages read server-side
  const loaded = !!data;
  useEffect(() => {
    if (loaded) refreshBadges();
  }, [loaded, refreshBadges]);

  if (isLoading) return <p className="p-6 text-sm text-gray-500">{t("loading")}</p>;
  if (isError || !data) {
    return <p className="p-6 text-sm text-red">{t("loadFailed")}</p>;
  }

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault();
    if (await send(draft)) setDraft("");
  };

  return (
    <div className="flex h-[70vh] flex-col overflow-hidden rounded-2xl border border-border-color bg-white">
      <div className="flex items-center gap-3 border-b border-border-color p-4">
        <Link href="/chats" aria-label={t("back")} className="rtl:rotate-180">
          <ArrowLeftIcon className="size-5" />
        </Link>
        {data.marketer.slug ? (
          <Link
            href={`/marketer/${data.marketer.slug}`}
            className="text-sm font-bold hover:underline"
          >
            {data.marketer.name}
          </Link>
        ) : (
          <span className="text-sm font-bold">{data.marketer.name}</span>
        )}
      </div>

      <div className="flex-1 space-y-2 overflow-y-auto p-4">
        {data.messages.length === 0 && (
          <p className="text-center text-sm text-gray-500">{t("noMessages")}</p>
        )}
        {data.messages.map((m) => {
          const mine = m.sender_type === "customer";
          return (
            <div
              key={m.id}
              className={cn("flex", mine ? "justify-end" : "justify-start")}
            >
              <div
                className={cn(
                  "max-w-[75%] whitespace-pre-wrap break-words rounded-2xl px-3 py-2 text-sm",
                  mine ? "bg-primary text-white" : "bg-gray-2",
                )}
              >
                {m.body}
                <div className="mt-1 text-[10px] opacity-70">
                  {new Date(m.created_at).toLocaleTimeString([], {
                    hour: "2-digit",
                    minute: "2-digit",
                  })}
                </div>
              </div>
            </div>
          );
        })}
        <div ref={endRef} />
      </div>

      <form
        onSubmit={onSubmit}
        className="flex items-center gap-2 border-t border-border-color p-3"
      >
        <input
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          maxLength={5000}
          placeholder={t("typeMessage")}
          className="flex-1 rounded-full border border-border-color px-4 py-2 text-sm outline-none focus:border-primary"
        />
        <button
          type="submit"
          disabled={isSending || !draft.trim()}
          className="rounded-full bg-primary px-5 py-2 text-sm font-bold text-white disabled:opacity-50"
        >
          {t("send")}
        </button>
      </form>
      {sendFailed && (
        <p className="px-4 pb-2 text-xs text-red">{t("sendFailed")}</p>
      )}
    </div>
  );
}

import { format } from "date-fns";
import { useTranslations } from "next-intl";
import { PaperclipIcon, HeadsetIcon } from "lucide-react";
import { cn } from "@/src/lib/utils";
import type { CaseMessage } from "../types";

type Props = {
  messages: CaseMessage[];
};

export default function MessageTimeline({ messages }: Props) {
  const t = useTranslations("support");

  if (messages.length === 0) {
    return <p className="text-sm text-gray py-8 text-center">{t("noMessages")}</p>;
  }

  return (
    <div className="flex flex-col gap-5">
      {messages.map((message) => {
        const isCustomer = message.sender_role === "customer";
        return (
          <div
            key={message.id}
            className={cn("flex flex-col gap-1 max-w-[85%]", isCustomer ? "self-end items-end" : "self-start items-start")}
          >
            <div className="flex items-center gap-1.5 text-xs text-gray">
              {!isCustomer && <HeadsetIcon className="size-3.5" />}
              <span className="font-semibold text-light">
                {t(isCustomer ? "you" : "supportTeam")}
              </span>
              <span>·</span>
              <span>{format(new Date(message.created_at), "MMM d, h:mm a")}</span>
            </div>

            <div
              className={cn(
                "rounded-2xl px-4 py-3 text-sm leading-relaxed",
                isCustomer
                  ? "bg-blue-3 text-white rounded-tr-sm"
                  : "bg-gray-2 text-primary rounded-tl-sm",
              )}
            >
              {message.message}
            </div>

            {message.attachments.length > 0 && (
              <div className="flex flex-wrap gap-2 mt-0.5">
                {message.attachments.map((attachment, i) => (
                  <a
                    key={`${attachment.url}-${i}`}
                    href={attachment.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1.5 bg-gray-2 hover:bg-gray-4 rounded-full ps-3 pe-3 py-1.5 text-xs text-light"
                  >
                    <PaperclipIcon className="size-3.5" />
                    {attachment.name}
                  </a>
                ))}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}

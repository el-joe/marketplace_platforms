"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import toast from "react-hot-toast";
import Card from "@/src/components/shared/Card";
import { Breadcrumb } from "@/src/components/ui/breadcrumb";
import CaseInfo from "./case-info";
import MessageTimeline from "./message-timeline";
import ReplyForm from "./reply-form";
import { sendDisputeMessage, sendTicketMessage } from "../api/support.actions";
import type { CaseDetails, CaseMessage } from "../types";
import { summarizeCase } from "../types";

type Props = {
  data: CaseDetails;
};

export default function CaseDetailPage({ data }: Props) {
  const t = useTranslations("support");
  const [messages, setMessages] = useState<CaseMessage[]>(data.messages);

  const summary = summarizeCase({ ...data, messages });

  const handleSend = async (message: string, attachment?: File) => {
    try {
      const result =
        data.mode === "ticket"
          ? await sendTicketMessage(data.ticket_number, { message, attachment })
          : await sendDisputeMessage(data.dispute_number, {
              message,
              attachment,
            });

      const newMessage: CaseMessage = {
        id: result.id,
        sender_role: "customer",
        message: result.message,
        created_at: result.created_at,
        attachments: attachment
          ? [{ url: URL.createObjectURL(attachment), name: attachment.name }]
          : [],
      };
      setMessages((prev) => [...prev, newMessage]);
      toast.success(t("messageSent"));
      return true;
    } catch {
      toast.error(t("messageSendFailed"));
      return false;
    }
  };

  return (
    <div>
      <Breadcrumb
        list={[
          {
            label: t(data.mode === "ticket" ? "ticketsTitle" : "disputesTitle"),
            href: data.mode === "ticket" ? "/tickets" : "/disputes",
          },
          { label: summary.number, href: "" },
        ]}
      />

      <h1 className="text-[28px] font-bold text-primary mt-3">
        {summary.title}
      </h1>

      <div className="mt-6 grid grid-cols-1 lg:grid-cols-[300px_1fr] gap-6 items-start">
        <CaseInfo data={{ ...data, messages }} />

        <Card className="border border-border p-6">
          <h2 className="text-lg font-bold text-primary mb-5">
            {t("conversation")}
          </h2>
          <MessageTimeline messages={messages} />
          <ReplyForm onSend={handleSend} />
        </Card>
      </div>
    </div>
  );
}

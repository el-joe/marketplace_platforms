"use client";

import { useState } from "react";
import { FormProvider, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { SendIcon } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import FormTextarea from "@/src/components/ui/form-inputs/form-textarea";
import { FileDropzone } from "@/src/components/ui/file-dropzone";
import { getReplySchema, type ReplyFormValues } from "./reply-schema";

type Props = {
  onSend: (message: string, attachment?: File) => Promise<boolean>;
};

export default function ReplyForm({ onSend }: Props) {
  const t = useTranslations("support");
  const [file, setFile] = useState<File | null>(null);
  const [isSending, setIsSending] = useState(false);

  const methods = useForm<ReplyFormValues>({
    resolver: zodResolver(getReplySchema(t)),
    defaultValues: { message: "" },
  });

  const onSubmit = async (data: ReplyFormValues) => {
    setIsSending(true);
    const sent = await onSend(data.message.trim(), file ?? undefined);
    setIsSending(false);

    if (sent) {
      methods.reset({ message: "" });
      setFile(null);
    }
  };

  return (
    <FormProvider {...methods}>
      <form
        onSubmit={methods.handleSubmit(onSubmit)}
        className="flex flex-col gap-3 border-t border-border pt-5 mt-5"
      >
        <FormTextarea
          name="message"
          rows={3}
          placeholder={t("replyPlaceholder")}
          disabled={isSending}
        />

        <div className="flex items-center justify-between gap-3">
          <FileDropzone
            files={file ? [file] : []}
            onFilesChange={(files) => setFile(files[files.length - 1] ?? null)}
            label={t("attachFiles")}
            multiple={false}
          />
          <Button
            type="submit"
            disabled={isSending}
            className="bg-blue-3 hover:bg-blue text-white border-transparent shrink-0"
          >
            <SendIcon /> {isSending ? t("sending") : t("send")}
          </Button>
        </div>
      </form>
    </FormProvider>
  );
}

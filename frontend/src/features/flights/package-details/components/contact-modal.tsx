"use client";

import { useState, type ReactElement } from "react";
import { useTranslations } from "next-intl";
import {
  MailIcon,
  PhoneIcon,
  SendIcon,
  CheckCircle2Icon,
  AlertCircleIcon,
} from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { Button, buttonVariants } from "@/src/components/ui/button";
import { Input } from "@/src/components/ui/base-inputs/input";
import { Textarea } from "@/src/components/ui/base-inputs/textarea";
import { Separator } from "@/src/components/ui/separator";
import { cn } from "@/src/lib/utils";
import { submitTravelInquiry } from "../../api/travel-packages.actions";

type Props = {
  packageSlug: string;
  packageName: string;
  email: string | null;
  phone: string | null;
  trigger?: ReactElement;
};

export default function ContactModal({
  packageSlug,
  packageName,
  email,
  phone,
  trigger,
}: Props) {
  const t = useTranslations("flights.packageDetails");

  const [open, setOpen] = useState(false);
  const [sent, setSent] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [name, setName] = useState("");
  const [userEmail, setUserEmail] = useState("");
  const [userPhone, setUserPhone] = useState("");
  const [message, setMessage] = useState("");

  function handleOpenChange(isOpen: boolean) {
    setOpen(isOpen);
    if (!isOpen) {
      setSent(false);
      setError(null);
      setLoading(false);
      setName("");
      setUserEmail("");
      setUserPhone("");
      setMessage("");
    }
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      await submitTravelInquiry(packageSlug, {
        name,
        email: userEmail,
        phone: userPhone,
        message,
      });
      setSent(true);
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : t("inquiryError");
      setError(msg);
    } finally {
      setLoading(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger
        render={
          trigger ?? (
            <button
              className={cn(
                buttonVariants({ size: "lg" }),
                "w-full bg-blue-3 hover:opacity-90 active:scale-[0.98] text-white border-transparent font-bold transition-all",
              )}
            />
          )
        }
      >
        {!trigger && t("showDetails")}
      </DialogTrigger>

      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{t("contactModalTitle")}</DialogTitle>
          <DialogDescription>{t("contactModalDescription")}</DialogDescription>
        </DialogHeader>

        {(email || phone) && (
          <>
            <div className="flex flex-col gap-2">
              {email && (
                <a
                  href={`mailto:${email}`}
                  className="flex items-center gap-2 text-sm text-light hover:text-blue-3"
                >
                  <MailIcon className="size-4 shrink-0" />
                  {email}
                </a>
              )}
              {phone && (
                <a
                  href={`tel:${phone}`}
                  className="flex items-center gap-2 text-sm text-light hover:text-blue-3"
                >
                  <PhoneIcon className="size-4 shrink-0" />
                  {phone}
                </a>
              )}
            </div>
            <Separator />
          </>
        )}

        {sent ? (
          <div className="flex flex-col items-center gap-2 py-4 text-center">
            <CheckCircle2Icon className="size-8 text-green" />
            <p className="text-sm font-semibold text-primary">
              {t("inquirySent")}
            </p>
            <p className="text-xs text-light">{t("inquirySentBody")}</p>
          </div>
        ) : (
          <form className="flex flex-col gap-3" onSubmit={handleSubmit}>
            <input type="hidden" value={packageName} readOnly />

            <Input
              required
              type="text"
              placeholder={t("inquiryNamePlaceholder")}
              value={name}
              onChange={(e) => setName(e.target.value)}
              disabled={loading}
            />
            <Input
              required
              type="email"
              placeholder={t("inquiryEmailPlaceholder")}
              value={userEmail}
              onChange={(e) => setUserEmail(e.target.value)}
              disabled={loading}
            />
            <Input
              required
              type="tel"
              placeholder={t("inquiryPhonePlaceholder")}
              value={userPhone}
              onChange={(e) => setUserPhone(e.target.value)}
              disabled={loading}
            />

            <Textarea
              required
              rows={3}
              placeholder={t("inquiryMessagePlaceholder")}
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              disabled={loading}
            />

            {error && (
              <div className="flex items-start gap-2 text-sm text-red bg-red/5 rounded-lg px-3 py-2">
                <AlertCircleIcon className="size-4 shrink-0 mt-0.5" />
                <span>{error}</span>
              </div>
            )}

            <Button
              type="submit"
              disabled={loading}
              className="bg-blue-3 hover:opacity-90 text-white border-transparent font-bold"
            >
              {loading ? (
                <span className="flex items-center gap-2">
                  <svg
                    className="size-4 animate-spin"
                    fill="none"
                    viewBox="0 0 24 24"
                  >
                    <circle
                      className="opacity-25"
                      cx="12"
                      cy="12"
                      r="10"
                      stroke="currentColor"
                      strokeWidth="4"
                    />
                    <path
                      className="opacity-75"
                      fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                    />
                  </svg>
                  {t("sendingInquiry")}
                </span>
              ) : (
                <>
                  <SendIcon /> {t("sendInquiry")}
                </>
              )}
            </Button>
          </form>
        )}
      </DialogContent>
    </Dialog>
  );
}

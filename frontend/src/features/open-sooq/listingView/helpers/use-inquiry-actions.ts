"use client";

import { useState } from "react";
import toast from "react-hot-toast";
import { useAuthContext } from "@/src/providers/auth-provider";
import { sendInquiry } from "../api/listing.actions";

export function useInquiryActions(slug: string) {
  const [open, setOpen] = useState(false);
  const [message, setMessage] = useState("");
  const [sending, setSending] = useState(false);
  const { isLogged, setAuthDialogIsOpen } = useAuthContext();

  const handleOpen = () => {
    if (!isLogged) {
      setAuthDialogIsOpen(true);
      return;
    }
    setOpen(true);
  };

  const handleSend = async () => {
    if (!message.trim()) return;
    setSending(true);
    try {
      await sendInquiry(slug, { message: message.trim() });
      toast.success("Inquiry sent successfully!");
      setOpen(false);
      setMessage("");
    } catch {
      toast.error("Failed to send inquiry. Please try again.");
    } finally {
      setSending(false);
    }
  };

  return {
    open,
    setOpen,
    message,
    setMessage,
    sending,
    handleOpen,
    handleSend,
  };
}

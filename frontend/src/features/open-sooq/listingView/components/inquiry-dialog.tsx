"use client";

import { MessageCircleIcon } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/src/components/shared/dialogs/confirm-dialog";
import { useInquiryActions } from "../helpers/use-inquiry-actions";

type Props = {
  slug: string;
  sellerName: string;
};

export default function InquiryDialog({ slug, sellerName }: Props) {
  const {
    open,
    setOpen,
    message,
    setMessage,
    sending,
    handleOpen,
    handleSend,
  } = useInquiryActions(slug);

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <Button
        className="w-full bg-blue text-white h-12 rounded-xl font-semibold gap-2"
        onClick={handleOpen}
      >
        <MessageCircleIcon className="size-4" />
        Contact Seller
      </Button>

      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Send a message to {sellerName}</DialogTitle>
        </DialogHeader>
        <div className="flex flex-col gap-4 p-4">
          <textarea
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            placeholder="Hi, I'm interested in this listing..."
            rows={4}
            className="w-full border border-border rounded-xl p-3 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue"
          />
          <Button
            onClick={handleSend}
            disabled={!message.trim() || sending}
            className="bg-blue text-white h-11 rounded-xl font-semibold"
          >
            {sending ? "Sending..." : "Send Message"}
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}

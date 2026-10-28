"use client";
import { useEffect, useRef } from "react";
import { initEcho } from "@/src/lib/echo";
import { useAuthContext } from "@/src/providers/auth-provider";
import { IChatEvent } from "./types";

const EVENT = ".message.sent";

/**
 * Subscribes to the signed-in customer's private chat channel. Listeners are
 * detached individually (never `Echo.leave`) because the header badge, the
 * list and an open thread all share the one channel.
 */
export function useChatChannel(onMessage: (event: IChatEvent) => void) {
  const { profile, isLogged } = useAuthContext();
  const handler = useRef(onMessage);

  useEffect(() => {
    handler.current = onMessage;
  });

  const customerId = isLogged ? profile?.id : undefined;

  useEffect(() => {
    if (!customerId) return;
    initEcho();
    const channel = window.Echo.private(`customer-chat.${customerId}`);
    const listener = (event: IChatEvent) => handler.current(event);
    channel.listen(EVENT, listener);
    return () => {
      channel.stopListening(EVENT, listener);
    };
  }, [customerId]);
}

"use client";
import { useCallback, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import {
  getChat,
  markChatRead,
  sendChatMessage,
} from "../api/chat.actions";
import { CHAT_LIST_KEY } from "./use-chat-list";
import { CHAT_UNREAD_KEY } from "./use-chat-unread";
import { useChatChannel } from "./use-chat-channel";
import { IChatMessage, IChatThread } from "./types";

export function useChatThread(id: string) {
  const queryClient = useQueryClient();
  const key = ["chat", id];
  const [isSending, setIsSending] = useState(false);
  const [sendFailed, setSendFailed] = useState(false);

  const query = useQuery({ queryKey: key, queryFn: () => getChat(id) });

  // Opening the thread marks it read server-side, so refresh the badges.
  const refreshBadges = useCallback(() => {
    queryClient.invalidateQueries({ queryKey: CHAT_UNREAD_KEY });
    queryClient.invalidateQueries({ queryKey: CHAT_LIST_KEY });
  }, [queryClient]);

  const append = useCallback(
    (message: IChatMessage) => {
      queryClient.setQueryData<IChatThread>(["chat", id], (thread) =>
        !thread || thread.messages.some((m) => m.id === message.id)
          ? thread
          : { ...thread, messages: [...thread.messages, message] },
      );
    },
    [queryClient, id],
  );

  useChatChannel((event) => {
    if (event.conversation_id !== id) return;
    append(event.message);
    if (event.message.sender_type === "marketer") {
      markChatRead(id).finally(refreshBadges);
    }
  });

  const send = async (body: string) => {
    const text = body.trim();
    if (!text || isSending) return false;
    setIsSending(true);
    setSendFailed(false);
    try {
      append(await sendChatMessage(id, text));
      refreshBadges();
      return true;
    } catch {
      setSendFailed(true);
      return false;
    } finally {
      setIsSending(false);
    }
  };

  return { ...query, send, isSending, sendFailed, refreshBadges };
}

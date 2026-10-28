"use client";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { playChatSound } from "@/src/lib/chat-sound";
import { useAuthContext } from "@/src/providers/auth-provider";
import { getChatUnreadCount } from "../api/chat.actions";
import { useChatChannel } from "./use-chat-channel";

export const CHAT_UNREAD_KEY = ["chat-unread"];

export function useChatUnread() {
  const { isLogged } = useAuthContext();
  const queryClient = useQueryClient();

  const { data } = useQuery({
    queryKey: CHAT_UNREAD_KEY,
    queryFn: getChatUnreadCount,
    enabled: isLogged,
  });

  useChatChannel((event) => {
    if (event.message.sender_type === "marketer") {
      queryClient.invalidateQueries({ queryKey: CHAT_UNREAD_KEY });
      playChatSound();
    }
  });

  return isLogged ? (data ?? 0) : 0;
}

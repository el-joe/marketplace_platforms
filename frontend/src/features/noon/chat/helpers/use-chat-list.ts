"use client";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { getChats } from "../api/chat.actions";
import { useChatChannel } from "./use-chat-channel";

export const CHAT_LIST_KEY = ["chats"];

export function useChatList() {
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: CHAT_LIST_KEY, queryFn: getChats });

  useChatChannel(() => {
    queryClient.invalidateQueries({ queryKey: CHAT_LIST_KEY });
  });

  return query;
}

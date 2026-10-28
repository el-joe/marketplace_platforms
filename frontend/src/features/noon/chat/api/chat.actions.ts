import { fetchInstance } from "@/src/lib/utils";
import {
  IChatConversation,
  IChatMessage,
  IChatThread,
} from "../helpers/types";

type Envelope<T> = { success: boolean; message: string; data: T };

const json = (body: unknown): RequestInit => ({
  method: "POST",
  body: JSON.stringify(body),
});

export const getChats = async () =>
  (await fetchInstance<Envelope<{ items: IChatConversation[] }>>("/chats")).data
    .items;

export const getChatUnreadCount = async () =>
  (await fetchInstance<Envelope<{ count: number }>>("/chats/unread-count")).data
    .count;

export const getChat = async (id: string) =>
  (await fetchInstance<Envelope<IChatThread>>(`/chats/${id}`)).data;

export const startChat = async (marketerSlug: string) =>
  (
    await fetchInstance<Envelope<IChatConversation>>(
      "/chats",
      json({ marketer_slug: marketerSlug }),
    )
  ).data;

export const sendChatMessage = async (id: string, body: string) =>
  (
    await fetchInstance<Envelope<IChatMessage>>(`/chats/${id}/messages`, {
      ...json({ body }),
      // lets the server skip echoing this message back to the sending tab
      headers: { "X-Socket-ID": window.Echo?.socketId() ?? "" },
    })
  ).data;

export const markChatRead = (id: string) =>
  fetchInstance(`/chats/${id}/read`, { method: "POST" });

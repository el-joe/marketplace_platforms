"use client";
import { useState } from "react";
import { useRouter } from "@/i18n/navigation";
import { useAuthContext } from "@/src/providers/auth-provider";
import { startChat } from "../api/chat.actions";

/** Opens (or creates) the chat with a marketer; prompts login first when logged out. */
export function useStartChat(marketerSlug: string) {
  const router = useRouter();
  const { protectedWithAuth } = useAuthContext();
  const [isStarting, setIsStarting] = useState(false);
  const [failed, setFailed] = useState(false);

  const start = () =>
    protectedWithAuth(async () => {
      setIsStarting(true);
      setFailed(false);
      try {
        const chat = await startChat(marketerSlug);
        router.push(`/chats/${chat.id}`);
      } catch {
        setFailed(true);
        setIsStarting(false);
      }
    });

  return { start, isStarting, failed };
}

import { getTranslations } from "next-intl/server";
import ChatList from "@/src/features/noon/chat/components/chat-list";

export default async function ChatsPage() {
  const t = await getTranslations("chat");

  return (
    <div>
      <h1 className="mb-4 text-xl font-bold">{t("title")}</h1>
      <ChatList />
    </div>
  );
}

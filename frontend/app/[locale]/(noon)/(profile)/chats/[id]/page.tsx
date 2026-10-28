import ChatThread from "@/src/features/noon/chat/components/chat-thread";

type Props = {
  params: Promise<{ id: string }>;
};

export default async function ChatPage({ params }: Props) {
  const { id } = await params;

  return <ChatThread id={id} />;
}

export interface IChatMessage {
  id: string;
  conversation_id: string;
  sender_type: "marketer" | "customer";
  body: string;
  read_at: string | null;
  created_at: string;
}

export interface IChatConversation {
  id: string;
  marketer: {
    id: string;
    name: string | null;
    slug: string | null;
    avatar_url: string | null;
  };
  last_message: IChatMessage | null;
  last_message_at: string | null;
  has_unread: boolean;
}

export interface IChatThread extends IChatConversation {
  messages: IChatMessage[];
}

export interface IChatEvent {
  conversation_id: string;
  message: IChatMessage;
}

import { fetchInstance } from "@/src/lib/utils";

export type NotificationPreferences = {
  email: boolean;
  sms: boolean;
  whatsapp: boolean;
  push: boolean;
};

type ApiEnvelope<T> = {
  success: boolean;
  message?: string;
  data: T;
};

/** Feature-only: GET /notifications/preferences */
export async function getNotificationPreferences(): Promise<NotificationPreferences> {
  const envelope = await fetchInstance<ApiEnvelope<NotificationPreferences>>(
    "/notifications/preferences",
  );
  return envelope.data;
}

/** Feature-only: PUT /notifications/preferences — send only the fields being updated */
export async function updateNotificationPreferences(
  payload: Partial<NotificationPreferences>,
): Promise<NotificationPreferences> {
  const envelope = await fetchInstance<ApiEnvelope<NotificationPreferences>>(
    "/notifications/preferences",
    {
      method: "PUT",
      body: JSON.stringify(payload),
    },
  );
  return envelope.data;
}

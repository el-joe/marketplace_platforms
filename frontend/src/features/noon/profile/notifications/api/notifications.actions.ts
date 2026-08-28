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

export type Notification = {
  id: string;
  type: string;
  data: {
    title?: string;
    title_en?: string;
    title_ar?: string;
    message?: string;
    body_en?: string;
    body_ar?: string;
    action_type?: "order" | "product" | "return" | "dispute" | "wallet" | "general";
    action_id?: string | null;
    icon?: "order" | "wallet" | "bell" | "gift" | "shield";
  };
  is_read: boolean;
  created_at: string;
};

export type NotificationsResponse = {
  items: Notification[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
  unread_count: number;
};

/** GET /notifications — paginated notification list with unread count */
export async function getNotifications(page = 1): Promise<NotificationsResponse> {
  const envelope = await fetchInstance<ApiEnvelope<NotificationsResponse>>(
    `/notifications?page=${page}`,
  );
  return envelope.data;
}

/** GET /notifications/unread-count */
export async function getUnreadCount(): Promise<number> {
  const envelope = await fetchInstance<ApiEnvelope<{ unread_count: number }>>(
    "/notifications/unread-count",
  );
  return envelope.data.unread_count;
}

/** PATCH /notifications/:id/read */
export async function markNotificationRead(id: string): Promise<void> {
  await fetchInstance(`/notifications/${id}/read`, { method: "PATCH" });
}

/** PATCH /notifications/read-all */
export async function markAllNotificationsRead(): Promise<void> {
  await fetchInstance("/notifications/read-all", { method: "PATCH" });
}

/** POST /notifications/device-token — register FCM push token */
export async function registerDeviceToken(payload: {
  device_token: string;
  platform: "ios" | "android" | "web";
  device_name?: string;
}): Promise<void> {
  await fetchInstance("/notifications/device-token", {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

/** DELETE /notifications/device-token */
export async function removeDeviceToken(payload: {
  device_token: string;
}): Promise<void> {
  await fetchInstance("/notifications/device-token", {
    method: "DELETE",
    body: JSON.stringify(payload),
  });
}

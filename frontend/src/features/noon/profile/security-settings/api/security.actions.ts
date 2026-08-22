import { fetchInstance } from "@/src/lib/utils";
import resolveCookie from "@/src/helpers/resolveCookie";
import type { ApiEnvelope, ActiveSessionsResponse } from "../helpers/types";

/** Feature-only: permanently deletes the authenticated customer's account. DELETE /profile */
export async function deleteProfile(): Promise<void> {
  await fetchInstance<unknown>("/profile", { method: "DELETE" });
}

/** Feature-only: GET /security/active-sessions — devices with an active session for this customer. */
export async function getActiveSessions(): Promise<ActiveSessionsResponse> {
  const envelope = await fetchInstance<ApiEnvelope<ActiveSessionsResponse>>(
    "/security/active-sessions",
  );
  return envelope.data;
}

/** Feature-only: DELETE /security/sessions/:id — revokes a single device's session. */
export async function revokeDevice(id: string): Promise<void> {
  await fetchInstance<unknown>(`/security/sessions/${id}`, {
    method: "DELETE",
  });
}

/**
 * Feature-only: DELETE /security/sessions/all — revokes every session except the current
 * device's. The API identifies "current" by the token in the request body, not the URL,
 * so this reads the caller's own access token rather than requiring it as a parameter.
 */
export async function revokeAllDevices(): Promise<void> {
  const currentDeviceToken = await resolveCookie("access_token");
  await fetchInstance<unknown>("/security/sessions/all", {
    method: "DELETE",
    body: JSON.stringify({ current_device_token: currentDeviceToken }),
  });
}

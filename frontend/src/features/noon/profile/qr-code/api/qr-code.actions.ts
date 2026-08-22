import { fetchInstance } from "@/src/lib/utils";
import type { QrCodeInfo } from "../helpers/types";

/** Feature-only: GET /profile/qr-code — NOT wrapped in the standard success/data envelope. */
export async function getQrCode(): Promise<QrCodeInfo> {
  return fetchInstance<QrCodeInfo>("/profile/qr-code");
}

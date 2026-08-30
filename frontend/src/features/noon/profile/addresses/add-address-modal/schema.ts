import { z } from "zod";

export function getAddressDetailsSchema(t: (key: string) => string) {
  return z.object({
    addressType: z.enum(["home", "work", "other"]),
    label: z.string().trim().max(100),
    firstName: z.string().trim().min(1, t("invalidFirstName")),
    lastName: z.string().trim().min(1, t("invalidLastName")),
    phoneNumber: z.string().trim().min(6, t("invalidPhoneNumber")).max(20),
    streetAddress: z.string().trim().min(1, t("invalidStreetAddress")).max(500),
    apartment: z.string().trim().max(50).optional(),
    building: z.string().trim().max(100).optional(),
    landmark: z.string().trim().max(255).optional(),
  });
}

export type AddressDetailsValues = z.infer<
  ReturnType<typeof getAddressDetailsSchema>
>;

import type { ICustomerProfile } from "@/types";
import type { ProfileFormValues } from "./profile.schema";

export function toFormValues(profile: ICustomerProfile): ProfileFormValues {
  const [firstName = "", ...rest] = profile?.name?.trim().split(" ");
  return {
    email: profile.email,
    phone: profile.phone ?? "",
    firstName,
    lastName: rest.join(" "),
    date_of_birth: profile.date_of_birth ?? "",
    gender: undefined,
    nationality: undefined,
  };
}

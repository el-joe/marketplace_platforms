import type { Address } from "@/src/services/address";
import type { LatLng } from "@/src/components/shared/maps/use-location-map";
import type { AddressDetailsValues } from "../add-address-modal/schema";

export function toAddressCenter(address: Address): LatLng {
  return {
    lat: address.latitude ?? 25.1972,
    lng: address.longitude ?? 55.2744,
  };
}

export function toAddressDetails(
  address: Address,
): Partial<AddressDetailsValues> {
  const [firstName = "", ...rest] = address.recipient_name.trim().split(" ");

  return {
    addressType: address.address_type,
    label: address.label ?? "",
    streetAddress: address.street_address,
    apartment: address.apartment ?? "",
    building: address.building ?? "",
    landmark: address.landmark ?? "",
    firstName,
    lastName: rest.join(" "),
    phoneNumber: address.recipient_phone,
  };
}

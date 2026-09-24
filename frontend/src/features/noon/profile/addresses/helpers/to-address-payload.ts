import type { AddressPayload } from "@/src/services/address";
import type {
  LatLng,
  ResolvedAddress,
} from "@/src/components/shared/maps/use-location-map";
import type { AddressDetailsValues } from "../add-address-modal/schema";

export type AddressWizardData = {
  center: LatLng;
  address: ResolvedAddress | null;
  details: AddressDetailsValues;
};

export function toAddressPayload({
  center,
  address,
  details,
}: AddressWizardData): AddressPayload {
  return {
    label: details.label?.trim() || null,
    recipient_name: `${details.firstName} ${details.lastName}`.trim(),
    recipient_phone: details.phoneNumber,
    country_code: address?.countryCode ?? null,
    city_name: address?.city ?? null,
    area: address?.area ?? null,
    street_address: details.streetAddress.trim(),
    building: details.building?.trim() || null,
    apartment: details.apartment?.trim() || null,
    landmark: details.landmark?.trim() || null,
    latitude: center.lat,
    longitude: center.lng,
    address_type: details.addressType,
  };
}

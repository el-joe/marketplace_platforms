import type { AddressPayload } from "@/src/services/address";
import type {
  LatLng,
  ResolvedAddress,
} from "@/src/components/shared/maps/use-location-map";
import type { AddressDetailsValues } from "../add-address-modal/schema";
import { DEFAULT_CITY_ID } from "./constants";

export type AddressWizardData = {
  center: LatLng;
  address: ResolvedAddress | null;
  details: AddressDetailsValues;
};

export function toAddressPayload({
  center,
  details,
}: AddressWizardData): AddressPayload {
  return {
    label: details.label?.trim() || null,
    recipient_name: `${details.firstName} ${details.lastName}`.trim(),
    recipient_phone: details.phoneNumber,
    city_id: DEFAULT_CITY_ID,
    street_address: details.streetAddress.trim(),
    building: details.building?.trim() || null,
    apartment: details.apartment?.trim() || null,
    landmark: details.landmark?.trim() || null,
    latitude: center.lat,
    longitude: center.lng,
    address_type: details.addressType,
  };
}

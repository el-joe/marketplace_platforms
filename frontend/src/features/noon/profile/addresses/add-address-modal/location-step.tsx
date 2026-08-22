"use client";

import { useTranslations } from "next-intl";
import { LocateFixedIcon, MapPinIcon, SearchIcon } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import { Input } from "@/src/components/ui/base-inputs/input";
import AddressLocationMap from "@/src/components/shared/dialogs/address-location-map";
import type {
  LatLng,
  ResolvedAddress,
} from "@/src/components/shared/maps/use-location-map";

type Props = {
  center: LatLng;
  address: ResolvedAddress | null;
  isLocating: boolean;
  onCenterChanged: (center: LatLng) => void;
  onUseCurrentLocation: () => void;
  onConfirm: () => void;
};

export default function LocationStep({
  center,
  address,
  isLocating,
  onCenterChanged,
  onUseCurrentLocation,
  onConfirm,
}: Props) {
  const t = useTranslations("profile");

  return (
    <div className="relative flex flex-col">
      <div className="absolute inset-s-1/2 top-2 z-10 flex w-[97%] -translate-x-1/2 items-center gap-2">
        <div className="flex-1">
          <Input
            startIcon={<SearchIcon />}
            placeholder={t("searchForAddress")}
            className="h-12 bg-white!"
          />
        </div>

        <Button
          type="button"
          onClick={onUseCurrentLocation}
          disabled={isLocating}
          className="h-12 text-blue-3 font-semibold"
        >
          <LocateFixedIcon className="size-4" />
          {t("useCurrentLocation")}
        </Button>
      </div>

      <AddressLocationMap
        center={center}
        onCenterChanged={onCenterChanged}
        pinLabel={t("deliveredHere")}
        className="h-[444px]!"
      />

      <div className="flex items-center justify-between gap-4 border-t border-border bg-muted/40 px-6 py-4">
        <div className="flex items-center gap-3">
          <MapPinIcon className="size-5 shrink-0 text-foreground" />
          <div>
            <p className="text-xs font-semibold uppercase text-gray">
              {t("currentLocation")}
            </p>
            <p className="font-medium">
              {address?.mainText ?? t("locatingYou")}
            </p>
            {address?.secondaryText && (
              <p className="text-sm text-gray">{address.secondaryText}</p>
            )}
          </div>
        </div>

        <Button
          type="button"
          onClick={onConfirm}
          className="h-11 shrink-0 rounded-md bg-blue-3 px-8 font-semibold text-white uppercase"
        >
          {t("confirmLocation")}
        </Button>
      </div>
    </div>
  );
}

"use client";

import { JSXElementConstructor, useState } from "react";
import { useTranslations } from "next-intl";
import { ArrowLeftIcon } from "lucide-react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import AddressLocationMapProvider from "@/src/components/shared/maps/map-provider";
import {
  useLocationMap,
  type LatLng,
  type ResolvedAddress,
} from "@/src/components/shared/maps/use-location-map";
import LocationStep from "./location-step";
import DetailsStep from "./details-step";
import type { AddressDetailsValues } from "./schema";

type Step = "location" | "details";

type Props = {
  trigger: React.ReactElement<unknown, string | JSXElementConstructor<unknown>>;
  initialCenter?: LatLng;
  initialDetails?: Partial<AddressDetailsValues>;
  onSave: (data: {
    center: LatLng;
    address: ResolvedAddress | null;
    details: AddressDetailsValues;
  }) => void | Promise<void>;
};

export default function AddAddressModal({
  trigger,
  initialCenter,
  initialDetails,
  onSave,
}: Props) {
  const t = useTranslations("profile");
  const [open, setOpen] = useState(false);
  const [step, setStep] = useState<Step>("location");
  const [isSaving, setIsSaving] = useState(false);

  const handleOpenChange = (nextOpen: boolean) => {
    setOpen(nextOpen);
    if (!nextOpen) setStep("location");
  };

  const handleSave = async (
    details: AddressDetailsValues,
    center: LatLng,
    address: ResolvedAddress | null,
  ) => {
    setIsSaving(true);
    try {
      await onSave({ center, address, details });
      setOpen(false);
    } catch {
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger render={trigger} />
      <DialogContent
        className=" max-w-2xl gap-0 overflow-y-auto p-0"
        showCloseButton
      >
        <DialogHeader className="flex-row items-center gap-2 border-b border-border px-6 py-5">
          {step === "details" && (
            <button
              type="button"
              onClick={() => setStep("location")}
              className="cursor-pointer"
            >
              <ArrowLeftIcon className="size-5" />
            </button>
          )}
          <DialogTitle className="text-xl font-bold">
            {step === "location" ? t("addNewAddressTitle") : t("deliverTo")}
          </DialogTitle>
        </DialogHeader>

        <AddressLocationMapProvider>
          <AddAddressModalBody
            step={step}
            initialCenter={initialCenter}
            initialDetails={initialDetails}
            isSaving={isSaving}
            onLocationConfirmed={() => setStep("details")}
            onChangeLocation={() => setStep("location")}
            onSave={handleSave}
          />
        </AddressLocationMapProvider>
      </DialogContent>
    </Dialog>
  );
}

function AddAddressModalBody({
  step,
  initialCenter,
  initialDetails,
  isSaving,
  onLocationConfirmed,
  onChangeLocation,
  onSave,
}: {
  step: Step;
  initialCenter?: LatLng;
  initialDetails?: Partial<AddressDetailsValues>;
  isSaving: boolean;
  onLocationConfirmed: () => void;
  onChangeLocation: () => void;
  onSave: (
    details: AddressDetailsValues,
    center: LatLng,
    address: ResolvedAddress | null,
  ) => void;
}) {
  const {
    center,
    address,
    isLocating,
    handleCenterChanged,
    useCurrentLocation,
  } = useLocationMap(initialCenter);

  if (step === "location") {
    return (
      <LocationStep
        center={center}
        address={address}
        isLocating={isLocating}
        onCenterChanged={handleCenterChanged}
        onUseCurrentLocation={useCurrentLocation}
        onConfirm={onLocationConfirmed}
      />
    );
  }

  return (
    <DetailsStep
      center={center}
      address={address}
      defaultValues={initialDetails}
      isSaving={isSaving}
      onChangeLocation={onChangeLocation}
      onSave={(details) => onSave(details, center, address)}
    />
  );
}

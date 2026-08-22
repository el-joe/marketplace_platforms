"use client";

import { Controller, FormProvider, useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useTranslations } from "next-intl";
import { Building2Icon, HomeIcon, MapPinIcon } from "lucide-react";
import { Button } from "@/src/components/ui/button";
import { Tabs, TabsList, TabsTrigger } from "@/src/components/ui/tabs";
import FormInput from "@/src/components/ui/form-inputs/form-input";
import type {
  LatLng,
  ResolvedAddress,
} from "@/src/components/shared/maps/use-location-map";
import { getAddressDetailsSchema, type AddressDetailsValues } from "./schema";

const defaultDetails: AddressDetailsValues = {
  addressType: "home",
  label: "",
  streetAddress: "",
  apartment: "",
  building: "",
  landmark: "",
  firstName: "",
  lastName: "",
  phoneNumber: "",
};

type Props = {
  center: LatLng;
  address: ResolvedAddress | null;
  defaultValues?: Partial<AddressDetailsValues>;
  isSaving?: boolean;
  onChangeLocation: () => void;
  onSave: (details: AddressDetailsValues) => void;
};

export default function DetailsStep({
  center,
  address,
  defaultValues,
  isSaving,
  onChangeLocation,
  onSave,
}: Props) {
  const t = useTranslations("profile");

  const form = useForm<AddressDetailsValues>({
    resolver: zodResolver(getAddressDetailsSchema(t)),
    defaultValues: {
      ...defaultDetails,
      streetAddress: address?.mainText ?? "",
      ...defaultValues,
    },
    mode: "onChange",
  });

  const {
    control,
    handleSubmit,
    formState: { isValid },
  } = form;

  const staticMapUrl = `https://maps.googleapis.com/maps/api/staticmap?center=${center.lat},${center.lng}&zoom=15&size=160x112&markers=${center.lat},${center.lng}&key=${process.env.NEXT_PUBLIC_GOOGLE_MAPS_API_KEY ?? ""}`;

  return (
    <FormProvider {...form}>
      <form onSubmit={handleSubmit(onSave)} className="flex flex-col gap-4 p-4">
        <section className="h-[70vh] overflow-y-auto">
          <div className="flex items-center gap-3 rounded-lg border border-border p-3">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={staticMapUrl}
              alt=""
              className="h-14 w-20 shrink-0 rounded-md bg-gray-2 object-cover"
            />
            <div className="flex-1">
              <p className="font-bold">{address?.mainText ?? "—"}</p>
              {address?.secondaryText && (
                <p className="text-sm text-gray">{address.secondaryText}</p>
              )}
            </div>
            <Button
              type="button"
              variant="outline"
              onClick={onChangeLocation}
              className="shrink-0 border-blue-3 font-semibold text-blue-3"
            >
              {t("change")}
            </Button>
          </div>

          <div>
            <p className="mb-2 text-xs font-bold uppercase text-gray">
              {t("addressDetails")}
            </p>

            <Controller
              control={control}
              name="addressType"
              render={({ field }) => (
                <Tabs value={field.value} onValueChange={field.onChange}>
                  <TabsList className="w-full justify-start bg-gray-2">
                    <TabsTrigger value="home" className="flex-none gap-1.5">
                      <HomeIcon className="size-4" />
                      {t("homeTab")}
                    </TabsTrigger>
                    <TabsTrigger value="work" className="flex-none gap-1.5">
                      <Building2Icon className="size-4" />
                      {t("workTab")}
                    </TabsTrigger>
                    <TabsTrigger value="other" className="flex-none gap-1.5">
                      <MapPinIcon className="size-4" />
                      {t("otherTab")}
                    </TabsTrigger>
                  </TabsList>
                </Tabs>
              )}
            />

            <div className="-mt-px rounded-lg border border-border bg-white p-4">
              <div className="mt-4 grid grid-cols-2 gap-4">
                <FormInput
                  name="apartment"
                  label={t("aptFloorVilla")}
                  className="h-12"
                />
                <FormInput
                  name="building"
                  label={t("buildingCluster")}
                  className="h-12"
                />
              </div>
              <div className="mt-4">
                <FormInput
                  name="streetAddress"
                  label={t("streetAddress")}
                  className="h-12"
                />
              </div>
              <div className="mt-4">
                <FormInput
                  name="label"
                  label={t("addressNicknameOptional")}
                  className="h-12"
                />
              </div>
            </div>
          </div>

          <div>
            <p className="mb-2 text-xs font-bold uppercase text-gray">
              {t("receiverDetails")}
            </p>

            <div className="rounded-lg border border-border bg-white p-4">
              <div className="grid grid-cols-2 gap-4">
                <FormInput
                  name="firstName"
                  label={t("firstName")}
                  className="h-12"
                />
                <FormInput
                  name="lastName"
                  label={t("lastName")}
                  className="h-12"
                />
              </div>

              <FormInput
                name="phoneNumber"
                label={t("phoneNumber")}
                placeholder="e.g 1234567"
                className="h-12 flex-1"
              />
            </div>
          </div>
        </section>
        <div className="flex justify-end">
          <Button
            type="submit"
            disabled={!isValid || isSaving}
            className="h-12 rounded-md bg-blue-3 px-10 font-semibold text-white uppercase disabled:opacity-50"
            text={isSaving ? t("saving") : t("saveAddress")}
            isLoading={isSaving}
          />
        </div>
      </form>
    </FormProvider>
  );
}

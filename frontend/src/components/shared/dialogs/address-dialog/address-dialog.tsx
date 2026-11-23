"use client";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/src/components/shared/dialogs/confirm-dialog";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/src/components/ui/tabs";
import { useTranslations } from "next-intl";
import { Button } from "@/src/components/ui/button";
import { JSXElementConstructor, useState } from "react";
import { LogInIcon, PlusIcon, SearchIcon } from "lucide-react";
import Image from "next/image";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from "@/src/components/ui/input-group";
import AddAddressModal from "@/src/features/noon/profile/addresses/add-address-modal";
import AddressesList from "./addresses-list";
import CountrySwitcher from "./country-switcher";
import { useAddressFormActions } from "@/src/features/noon/profile/addresses/helpers/use-address-form-actions";
import { useAuthContext } from "@/src/providers/auth-provider";
type props = {
  triggerButton?: React.ReactElement<
    unknown,
    string | JSXElementConstructor<unknown>
  >;
  open?: boolean;
  onClose?: () => void;
};
const AddressDialog = ({ triggerButton, open, onClose }: props) => {
  const [isOpen, setIsOpen] = useState<boolean>(false);
  const t = useTranslations("header.locationDialog");
  const { saveNewAddress } = useAddressFormActions();
  const { isLogged, setAuthDialogIsOpen } = useAuthContext();
  const handleOpenState = (state: boolean) => {
    setIsOpen(state);
    onClose?.();
  };
  return (
    <Dialog open={open || isOpen} onOpenChange={handleOpenState}>
      <DialogTrigger render={triggerButton} />
      <DialogContent
        className={
          "lg:min-w-3xl! lg:w-[50vw]  max-w-7xl! lg:h-[70vh] max-h-[95vh] overflow-auto flex flex-col"
        }
      >
        {/* title + country selector: shown on every breakpoint so guests can
            change country on mobile exactly as on desktop (no login needed) */}
        <DialogHeader className="flex flex-row items-center justify-between gap-2 pe-10 lg:pe-9">
          <DialogTitle className={"text-lg lg:text-xl font-bold"}>
            {t("title")}
          </DialogTitle>
          <CountrySwitcher />
        </DialogHeader>
        <Tabs defaultValue="address" className={"flex-1"}>
          <TabsList
            variant="line"
            className={
              "border-b border-b-[#e2e5f1] justify-start -mx-4 px-4 w-auto"
            }
          >
            <TabsTrigger value="address" className={"flex-none"}>
              {t("address")}
            </TabsTrigger>
            {/* <TabsTrigger value="pickupPoint" className={"flex-none"}>
              {t("pickupPoint")}
            </TabsTrigger> */}
          </TabsList>
          {/* address tap */}
          <TabsContent
            value="address"
            className={"-mx-4 -mt-2 -mb-4 rounded-b-lg p-3 bg-gray-3"}
          >
            <div className="flex flex-col gap-3">
              {/* search field */}
              {/* <InputGroup className="h-12 text-base! bg-white!">
                <InputGroupInput placeholder={t("searchPlaceholder")} />
                <InputGroupAddon align="inline-start">
                  <SearchIcon className="text-primary size-5" />
                </InputGroupAddon>
              </InputGroup> */}
              {/* map button */}
              {isLogged ? (
                <AddAddressModal
                  onSave={saveNewAddress}
                  trigger={
                    <Button
                      className={"h-12 justify-start text-blue-2! text-base!"}
                    >
                      <PlusIcon className="size-5 text-blue-2" />
                      {t("addAddress")}
                    </Button>
                  }
                />
              ) : (
                // Guests: adding an address needs an account, but changing
                // country (header above) does not — keep the two clearly apart.
                <div className="rounded-lg bg-white p-3 flex flex-col gap-2">
                  <p className="text-secondary text-sm">
                    {t("guestAddressHint")}
                  </p>
                  <Button
                    className={"h-11 justify-start text-blue-2! text-base!"}
                    onClick={() => {
                      handleOpenState(false);
                      setAuthDialogIsOpen(true);
                    }}
                  >
                    <LogInIcon className="size-5 text-blue-2 rtl:-scale-x-100" />
                    {t("loginToAddAddress")}
                  </Button>
                </div>
              )}
              {/* search result */}

              <AddressesList
                handleCloseDialog={() => {
                  handleOpenState(false);
                }}
              />
            </div>
          </TabsContent>
          {/* pickup point tap */}
          <TabsContent
            value="pickupPoint"
            className={"-mx-4 -mt-2 -mb-4 rounded-b-lg p-3 bg-gray-3"}
          >
            <div className="flex flex-col gap-3">
              {/* search field */}
              <InputGroup className="h-12 text-base! bg-white!">
                <InputGroupInput placeholder={t("searchPlaceholder")} />
                <InputGroupAddon align="inline-start">
                  <SearchIcon className="text-primary size-5" />
                </InputGroupAddon>
              </InputGroup>
              {/* map button */}
              <Button className={"h-12 justify-start text-blue-2! text-base!"}>
                <PlusIcon className="size-5 text-blue-2" />
                {t("addPickupPoint")}
              </Button>
              {/* search result */}
              <div className="flex flex-col items-center h-80 overflow-auto">
                <div className="relative">
                  <Image
                    src={
                      "https://f.nooncdn.com/s/app/com/noon/design-system/empty-states/collectionsV2-new.svg"
                    }
                    width={266}
                    height={266}
                    alt="empty result"
                  />
                </div>
                <p className="mb-2 font-bold text-center max-w-60">
                  {t("getPickupPoint")}
                </p>
                <p className="text-secondary max-w-72 text-center">
                  {t("getPickupPointMessage")}
                </p>
              </div>
            </div>
          </TabsContent>
        </Tabs>
      </DialogContent>
    </Dialog>
  );
};

export default AddressDialog;

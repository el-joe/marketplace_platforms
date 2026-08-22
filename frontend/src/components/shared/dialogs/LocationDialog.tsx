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
import Dropdown from "@/src/components/shared/Dropdown";
import { Button } from "@/src/components/ui/button";
import { JSXElementConstructor } from "react";
import { ChevronDownIcon, PlusIcon, SearchIcon } from "lucide-react";
import Image from "next/image";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from "@/src/components/ui/input-group";
type props = {
  triggerButton?: React.ReactElement<
    unknown,
    string | JSXElementConstructor<unknown>
  >;
  open?: boolean;
  onClose?: () => void;
};
const LocationDialog = ({ triggerButton, open, onClose }: props) => {
  const t = useTranslations("header.locationDialog");
  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogTrigger render={triggerButton} />
      <DialogContent className={"min-w-3xl! w-[50vw]  max-w-none!"}>
        <DialogHeader className="flex flex-row items-center justify-between pe-9">
          <DialogTitle className={"text-xl font-bold"}>
            {t("title")}
          </DialogTitle>
          {/* countries dropdown */}
          <Dropdown
            triggerButton={
              <Button
                className={
                  "bg-gray-2 rounded-md text-primary font-semibold text-base"
                }
              >
                <div className="relative">
                  <Image
                    src={
                      "https://f.nooncdn.com/s/app/com/common/images/flags/ae-icon.svg"
                    }
                    fill
                    sizes="100%"
                    alt="flag"
                    className="relative!"
                  />
                </div>
                UAE <ChevronDownIcon />
              </Button>
            }
            // countries list
            items={[{ itemLabel: "egypt", value: "EG" }]}
            listTitle="countries"
            onSelect={(item) => console.log(item)}
          />
        </DialogHeader>
        <Tabs defaultValue="address">
          <TabsList
            variant="line"
            className={
              "border-b border-b-[#e2e5f1] justify-start -mx-4 px-4 w-auto"
            }
          >
            <TabsTrigger value="address" className={"flex-none"}>
              {t("address")}
            </TabsTrigger>
            <TabsTrigger value="pickupPoint" className={"flex-none"}>
              {t("pickupPoint")}
            </TabsTrigger>
          </TabsList>
          {/* address tap */}
          <TabsContent
            value="address"
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
                {t("addAddress")}
              </Button>
              {/* search result */}
              <div className="flex flex-col items-center h-80 overflow-auto">
                <div className="relative">
                  <Image
                    src={
                      "https://f.nooncdn.com/s/app/com/noon/design-system/empty-states/addressesV2-new.svg"
                    }
                    width={266}
                    height={266}
                    alt="empty result"
                  />
                </div>
                <p className="mb-2 font-bold">{t("noSavedAddresses")}</p>
                <p className="text-secondary max-w-56 text-center">
                  {t("noSavedAddressesMessage")}
                </p>
              </div>
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

export default LocationDialog;

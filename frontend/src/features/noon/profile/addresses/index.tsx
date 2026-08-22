import { getTranslations } from "next-intl/server";
import Card from "@/src/components/shared/Card";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/src/components/ui/tabs";
import { getAddresses } from "@/src/services/address";
import AddressesList from "./list";
import AddressesEmptyState from "./empty-state";

export default async function Addresses() {
  const t = await getTranslations("profile");

  const addresses = await getAddresses();

  return (
    <>
      <h1 className="text-[28px] font-bold  text-light">
        {t("addressesTitle")}
      </h1>
      <p className="text-sm text-gray mt-1">{t("addressesSubtitle")}</p>

      <Card className="mt-3 p-4">
        <Tabs defaultValue="address">
          <TabsList
            variant="line"
            className="border-b border-border w-full justify-start"
          >
            <TabsTrigger value="address" className="flex-none">
              {t("addressTab")}
            </TabsTrigger>
            {/* <TabsTrigger value="pickupPoint" className="flex-none">
              {t("lockerPickupTab")}
            </TabsTrigger> */}
          </TabsList>

          <TabsContent value="address" className="mt-4">
            {addresses.length === 0 ? (
              <AddressesEmptyState />
            ) : (
              <AddressesList addresses={addresses} />
            )}
          </TabsContent>

          <TabsContent value="pickupPoint" className="mt-4">
            <AddressesEmptyState />
          </TabsContent>
        </Tabs>
      </Card>
    </>
  );
}

import { getTranslations } from "next-intl/server";
import { getUnifiedBookings } from "./api/my-bookings.actions";
import BookingsTabs from "./components/tabs";

export default async function MyBookings() {
  const t = await getTranslations("myBookings");
  const data = await getUnifiedBookings();

  return (
    <div>
      <h1 className="text-[28px] font-bold text-primary">{t("title")}</h1>
      <p className="text-sm text-gray mt-1">{t("subtitle")}</p>

      <div className="mt-6">
        <BookingsTabs data={data} />
      </div>
    </div>
  );
}

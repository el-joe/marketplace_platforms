import { getTranslations } from "next-intl/server";
import Form from "./form";
import ProfileFormFields from "./form/fields";
import type { ICustomerProfile } from "@/types";


export default async function Profile({ profile }: { profile: ICustomerProfile }) {
  const t = await getTranslations("profile");



  
  return (
    <>
      <div>
        <h1 className="text-[28px] font-bold  text-light">
          {t("profileTitle")}
        </h1>
        <p className="text-sm text-gray mt-1">{t("profileSubtitle")}</p>
      </div>

      <Form profile={profile}>
        <ProfileFormFields />
      </Form>
    </>
  );
}

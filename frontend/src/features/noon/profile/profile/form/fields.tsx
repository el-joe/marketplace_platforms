import { getTranslations } from "next-intl/server";
import Image from "next/image";
import Card from "@/src/components/shared/Card";
import FormInput from "@/src/components/ui/form-inputs/form-input";
import FormSelect from "@/src/components/ui/form-inputs/form-select";
import { getCountries } from "@/src/services/get";
import GenderField from "./gender-field";

export default async function ProfileFormFields() {
  const [t, countries] = await Promise.all([
    getTranslations("profile"),
    getCountries(),
  ]);

  const nationalityItems = countries.map((country) => ({
    label: country.name,
    value: country.id,
  }));

  return (
    <>
      <Card className="mt-6 p-6">
        <h2 className="font-bold text-lg">{t("contactInformation")}</h2>

        <div className="grid grid-cols-3 gap-4 mt-4">
          <FormInput
            name="email"
            label={t("email")}
            className="h-12 bg-gray-2!"
            readOnly
          />

          <div>
            <FormInput name="phone" label={t("phoneNumber")} className="h-12" />
            <p className="text-xs text-gray mt-1.5">{t("addPhoneHint")}</p>
          </div>
        </div>
      </Card>

      <Card className="mt-4 p-6">
        <h2 className="font-bold text-lg mb-4">{t("personalInformation")}</h2>

        <div className="grid grid-cols-3 gap-6">
          <FormInput name="firstName" label={t("firstName")} className="h-12" />

          <FormInput name="lastName" label={t("lastName")} className="h-12" />
        </div>

        <div className="grid grid-cols-3 gap-6 my-8">
          <div>
            <FormInput
              label={t("birthday")}
              type="date"
              name="date_of_birth"
              className="h-12"
            />
            <div className="text-xs text-green mt-1.5 flex items-center gap-2">
              <Image
                src={"/images/profile/bd-discount-icon.svg"}
                alt=""
                width={20}
                height={20}
              />
              {t("birthdayOffersHint")}
            </div>
          </div>

          <GenderField
            label={t("gender")}
            maleLabel={t("male")}
            femaleLabel={t("female")}
          />
        </div>

        <div className="grid grid-cols-3 gap-6 items-end">
          <FormSelect
            name="nationality"
            label={t("nationality")}
            items={nationalityItems}
            triggerClass="w-full h-12! rounded-lg justify-between px-3"
          />
        </div>
      </Card>
    </>
  );
}

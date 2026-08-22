import Image from "next/image";
import { useTranslations } from "next-intl";

type Props = {
  image: string;
};

export default function SelectedThemePreview({ image }: Props) {
  const t = useTranslations("giftCards");

  return (
    <div>
      <h2 className="font-bold text-lg mb-3">{t("selectedTheme")}</h2>
      <div className="relative w-[282px] h-[170px] overflow-hidden rounded-xl shadow">
        <Image src={image} alt="" fill sizes="282px" className="object-cover" />
      </div>
    </div>
  );
}

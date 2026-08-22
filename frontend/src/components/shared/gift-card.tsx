import Image from "next/image";
import { useTranslations } from "next-intl";
import { Link } from "@/i18n/navigation";
import Card from "@/src/components/shared/Card";
import type { GiftCardCategory } from "../../features/noon/gift-cards/data";

type Props = {
  category: GiftCardCategory;
};

export default function CategoryCard({ category }: Props) {
  const t = useTranslations("giftCards");
  const [backImage, frontImage] = category.images;

  return (
    <Link href={`/gift-cards/${category.slug}`}>
      <Card className="h-[220px] bg-gray-2 flex flex-col items-center gap-6 transition-shadow hover:shadow-md p-4">
        <div className="relative grid place-items-center flex-1 w-full">
          <Image
            src={backImage}
            alt=""
            width={143}
            height={88}
            className="absolute shadow-[0_4px_15px_#00000040]"
            style={{ transform: "rotate(-25deg) translate(-12%, -8%)" }}
          />
          <Image
            src={frontImage}
            alt=""
            width={143}
            height={88}
            className="absolute shadow-[0_4px_15px_#00000040]"
            style={{ transform: "translate(10px, 50%)" }}
          />
        </div>

        <p className="mt-6 text-sm font-bold text-center">
          {t(category.labelKey)}
        </p>
      </Card>
    </Link>
  );
}

import Image from "next/image";
import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { getImageURL } from "@/src/helpers/get-image-url";
import { TYPE_BADGE_KEYS } from "../helpers/constants";
import type { MarketerCardData } from "../helpers/types";

interface Props {
  marketer: MarketerCardData;
}

/** Boutiqaat-style portrait card: ~283x324 image, name underneath, subtle type badge. */
export default async function MarketerPortraitCard({ marketer }: Props) {
  const t = await getTranslations("marketers");
  const badgeKey = TYPE_BADGE_KEYS[marketer.type as keyof typeof TYPE_BADGE_KEYS];
  const badge = marketer.type_label ?? (badgeKey ? t(badgeKey) : null);

  return (
    <Link
      href={`/marketer/${marketer.slug}`}
      className="group block rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
    >
      <div className="relative aspect-[283/324] w-full overflow-hidden rounded-xl bg-gray-2">
        {marketer.avatar_url ? (
          <Image
            src={getImageURL(marketer.avatar_url)}
            alt=""
            fill
            sizes="(min-width:1280px) 16vw, (min-width:1024px) 20vw, (min-width:768px) 25vw, (min-width:640px) 31vw, 45vw"
            className="object-cover transition-transform duration-500 group-hover:scale-105"
          />
        ) : (
          <div
            aria-hidden
            className="flex h-full w-full items-center justify-center bg-gradient-to-br from-amber-200 via-amber-300 to-amber-500 text-5xl font-black text-white"
          >
            {marketer.name.trim().charAt(0).toUpperCase()}
          </div>
        )}
        {badge && (
          <span className="absolute top-2 ltr:left-2 rtl:right-2 rounded-full bg-black/45 px-2 py-0.5 text-[10px] font-semibold text-white backdrop-blur-sm">
            {badge}
          </span>
        )}
      </div>
      <h3 className="mt-3 line-clamp-1 text-center text-sm font-bold text-primary group-hover:underline sm:text-base">
        {marketer.name}
      </h3>
    </Link>
  );
}

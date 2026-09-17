import { useTranslations } from "next-intl";
import { ChevronLeft, XIcon } from "lucide-react";

interface HelpSheetHeaderProps {
  title: string;
  description?: string;
  showBack: boolean;
  greeting?: string;
  onBack: () => void;
  onClose: () => void;
  centered?: boolean;
}

export default function HelpSheetHeader({
  title,
  description,
  showBack,
  greeting,
  onBack,
  onClose,
  centered = false,
}: HelpSheetHeaderProps) {
  const t = useTranslations("helpSheet");
  return (
    <div className="flex items-start justify-between gap-2 p-6 pb-4">
      <div className={centered ? "flex-1 text-center" : undefined}>
        {showBack && (
          <button
            type="button"
            onClick={onBack}
            aria-label={t("back")}
            className={
              "mb-3 flex size-8 cursor-pointer items-center justify-center rounded-full bg-white/60" +
              (centered ? " absolute" : "")
            }
          >
            <ChevronLeft className="size-5" />
          </button>
        )}
        {!showBack && greeting && (
          <p className="mb-1 text-lg text-light">{greeting}</p>
        )}
        <h2 className="text-2xl font-bold text-light">{title}</h2>
        {showBack && description && (
          <p className="mt-1 text-sm text-gray">{description}</p>
        )}
      </div>
      <button
        type="button"
        onClick={onClose}
        aria-label={t("close")}
        className="flex size-8 shrink-0 cursor-pointer items-center justify-center"
      >
        <XIcon className="size-5" />
      </button>
    </div>
  );
}

import { ChevronRightIcon } from "lucide-react";
import type { ReactNode } from "react";

type Props = {
  icon: ReactNode;
  title: string;
  subtitle: string;
};

export default function SettingsRow({ icon, title, subtitle }: Props) {
  return (
    <div className="flex w-full items-center gap-4 px-6 py-5 text-left">
      <div className="flex size-9 shrink-0 items-center justify-center">{icon}</div>
      <div className="flex-1">
        <h2 className="font-bold">{title}</h2>
        <p className="mt-0.5 text-sm text-gray">{subtitle}</p>
      </div>
      <ChevronRightIcon className="size-5 shrink-0 text-gray" />
    </div>
  );
}

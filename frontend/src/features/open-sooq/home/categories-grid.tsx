import {
  CarIcon,
  HomeIcon,
  SmartphoneIcon,
  SofaIcon,
  BriefcaseIcon,
  ShirtIcon,
  DumbbellIcon,
  PawPrintIcon,
} from "lucide-react";

import { Link } from "@/i18n/navigation";

const categories = [
  { icon: CarIcon, label: "Vehicles" },
  { icon: HomeIcon, label: "Real Estate" },
  { icon: SmartphoneIcon, label: "Mobiles & Tablets" },
  { icon: SofaIcon, label: "Home & Furniture" },
  { icon: BriefcaseIcon, label: "Jobs" },
  { icon: ShirtIcon, label: "Fashion" },
  { icon: DumbbellIcon, label: "Sports & Fitness" },
  { icon: PawPrintIcon, label: "Pets" },
];

export default function CategoriesGrid() {
  return (
    <div className="container grid grid-cols-4 md:grid-cols-8 gap-3 py-4">
      {categories.map(({ icon: Icon, label }) => (
        <Link
          key={label}
          href={"/"}
          className="flex flex-col items-center gap-2 text-center"
        >
          <span className="flex items-center justify-center w-14 h-14 rounded-full bg-section-bg">
            <Icon className="w-6 h-6" />
          </span>
          <span className="text-xs font-medium">{label}</span>
        </Link>
      ))}
    </div>
  );
}

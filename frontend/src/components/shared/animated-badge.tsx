import { cn } from "@/src/lib/utils";
import { CarIcon, icons, LucideProps } from "lucide-react";
import React from "react";
import { Autoplay } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";

type Props = {
  badges: {
    label: string;
    icon: React.ForwardRefExoticComponent<
      Omit<LucideProps, "ref"> & React.RefAttributes<SVGSVGElement>
    >;
    iconColor?: string;
  }[];
  size?: "lg" | "md" | "sm";
  containerClasses?: string;
};

enum containerSizes {
  lg = "",
  md = "h-8!",
  sm = "h-4!",
}
enum slideSizes {
  lg = "",
  md = "h-8!",
  sm = "h-4!",
}
enum iconSizes {
  lg = "",
  md = "size-6",
  sm = "size-4",
}
enum labelSizes {
  lg = "",
  md = "text-base",
  sm = "text-sm",
}

const AnimatedBadge = ({ badges, size = "md", containerClasses }: Props) => {
  return (
    <Swiper
      className={cn(
        `h-8! w-fit! mx-0!`,
        containerSizes[size],
        containerClasses,
      )}
      modules={[Autoplay]}
      direction="vertical"
      slidesPerView={1}
      spaceBetween={6}
      loop
      speed={1300}
      autoplay={{ delay: 2000, disableOnInteraction: false }}
      allowTouchMove={false}
      simulateTouch={false}
      grabCursor={false}
    >
      {badges.map((badge, i) => (
        <SwiperSlide className={cn("w-fit!", slideSizes[size])} key={i}>
          <div className="flex items-center gap-2 h-full">
            <badge.icon
              style={{ color: badge.iconColor }}
              className={cn(iconSizes[size])}
            />
            <p className={cn(labelSizes[size])}>{badge.label}</p>
          </div>
        </SwiperSlide>
      ))}
    </Swiper>
  );
};

export default AnimatedBadge;

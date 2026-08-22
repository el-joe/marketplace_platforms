"use client";
import { Link } from "@/i18n/navigation";
import { uptoOffersData } from "@/public/dummyData";
import Image from "next/image";
import { Swiper, SwiperSlide } from "swiper/react";

const UptoSection = () => {
  return (
    <div className="lg:container bg-section-bg py-4">
      {uptoOffersData.map((offers, i) => (
        <div key={i}>
          {/* header */}
          <Link href={"/"} className="block mb-4 mt-8 px-3 lg:px-0 ">
            <Image src={offers.header} alt="" width={2000} height={200} />
          </Link>
          <Swiper
            slidesPerView={3.2}
            spaceBetween={18}
            breakpoints={{
              768: { slidesPerView: 5.2 },
              1024: { slidesPerView: 7 },
            }}
            className="px-3! lg:px-0!"
          >
            {offers.images.map((image, i) => (
              <SwiperSlide key={i}>
                <Image src={image} alt="" width={600} height={200} />
              </SwiperSlide>
            ))}
          </Swiper>
        </div>
      ))}
    </div>
  );
};

export default UptoSection;

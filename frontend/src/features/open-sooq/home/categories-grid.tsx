"use client";
import AdBanner from "@/src/components/shared/ad-badge";
import { Link } from "@/i18n/navigation";
import Image from "next/image";
import React from "react";
import { Navigation, Pagination } from "swiper/modules";
import { Swiper, SwiperSlide } from "swiper/react";

const categoriesList = [
  "https://a.nooncdn.com/mpcms/EN0001/assets/9fa783b7-5c2a-434a-a8f7-0d01ea17670a.png?width=2400",
  "https://a.nooncdn.com/mpcms/EN0001/assets/31ab1f9e-75af-423b-b252-1572b95bfd66.png?width=2400",
  "https://a.nooncdn.com/mpcms/EN0001/assets/e8e48a6b-9a86-4317-b07b-b90ab388a232.png?width=2400",
  "https://a.nooncdn.com/mpcms/EN0001/assets/6e73f8df-982f-4196-83aa-03d937803dd8.png?width=2400",
  "https://a.nooncdn.com/mpcms/EN0001/assets/b9ddb583-aa17-4508-8a67-84a84e818d34.png?width=2400",
  "https://a.nooncdn.com/mpcms/EN0001/assets/f5b7fba4-7ceb-4a31-ad79-12481b1bf9e8.png?width=2400",
  "https://a.nooncdn.com/mpcms/EN0001/assets/0f4dceb2-e069-4595-8f69-973d13e9d477.png?width=2400",
  "https://a.nooncdn.com/mpcms/EN0001/assets/99cf94eb-b5db-4df1-866d-5cdb9cfc7573.png?width=2400",
  "https://a.nooncdn.com/mpcms/EN0001/assets/5d6e2b77-23a3-453b-80f4-d04c11ab344b.png?width=2400",
  "https://a.nooncdn.com/mpcms/EN0001/assets/868a08c5-b872-4425-8db2-a13753f25163.png?width=2400",
  "https://a.nooncdn.com/mpcms/EN0001/assets/ab1770d6-124f-46eb-ba65-84a4fa31fe8b.png?width=2400",
  "https://a.nooncdn.com/mpcms/EN0001/assets/fb674c51-8108-428c-8c23-f8931df8642e.png?width=2400",
  "https://a.nooncdn.com/mpcms/EN0001/assets/f3c0c5a5-bb17-46c9-9d20-e4a06bfbba8f.png?width=2400",
  "https://a.nooncdn.com/mpcms/EN0001/assets/8a68e805-cf9d-4aa0-a8ae-a69c27cfa768.png?width=2400",
  "https://a.nooncdn.com/mpcms/EN0001/assets/67a8cf53-8952-4c1c-8458-f6e49f03cb7b.png?width=2400",
];

const CategoriesGrid = () => {
  return (
    <div className="container px-0 md:px-4 flex items-center gap-8 flex-wrap py-8 mx-auto">
      {categoriesList.map((category, i) => (
        <Link
          key={i}
          href={"/"}
          className="block relative w-23.75 h-33 md:w-15.25 md:h-21.25 xl:w-32 xl:h-44"
        >
          <Image src={category} alt="category" fill sizes="100%" />
        </Link>
      ))}
    </div>
  );
};

export default CategoriesGrid;

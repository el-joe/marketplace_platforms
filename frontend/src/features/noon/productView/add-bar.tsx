import Price from "@/src/components/shared/Price";
import Image from "next/image";
import React from "react";

const AddBar = () => {
  return (
    <div className="bg-[#fafafa]">
      <div className="container">
        <div className="flex items-center justify-center gap-2 relative">
          <Image
            src={
              "https://f.nooncdn.com/p/pzsku/ZEB7BB9183AEED601D59AZ/45/1758376496/1e06964c-0782-4548-8ca1-bc018e30aa70.jpg"
            }
            alt=""
            width={40}
            height={40}
          />
          <p className="max-w-8/12 line-clamp-1">
            iPhone 17 Pro Clear Case Compatible with MagSafe Anti-Scratch Drop
            Protection Cover For iPhone 17 Pro Shockproof Slim Phone Case for
            iPhone 17 Pro 6.3-inch
          </p>
          <Price currentPrice={200} currency="AED" size="lg" />
          <span className="px-2 py-1 bg-main font-bold rounded-lg">
            express
          </span>
          <span className="absolute bottom-0 inset-e-4 text-lg bg-[#e7e7e7] rounded-tl-lg px-3 text-light">
            Ad
          </span>
        </div>
      </div>
    </div>
  );
};

export default AddBar;

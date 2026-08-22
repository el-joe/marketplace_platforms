import { CircleCheckIcon } from "lucide-react";
import Image from "next/image";

interface EntryPointWidgetProps {
  title: string;
  features: string[];
  imageSrc: string;
  imageAlt: string;
  buttonLabel: string;
  buttonVariant: "main" | "green";
  onButtonClick?: () => void;
}

function EntryPointWidget({
  title,
  features,
  imageSrc,
  imageAlt,
  buttonLabel,
  buttonVariant,
  onButtonClick,
}: EntryPointWidgetProps) {
  const buttonColorClasses =
    buttonVariant === "main" ? "bg-main text-light" : "bg-green text-white";

  return (
    <section className="flex flex-col gap-4 border border-[#EFEFEF] rounded-[8px] flex-1 justify-between p-4">
      <div className="flex justify-between">
        <div className="flex flex-col gap-2 max-w-[70%]">
          <strong className="font-bold text-lg">{title}</strong>
          {features.map((feature) => (
            <span
              key={feature}
              className="flex items-center gap-2 text-sm font-normal"
            >
              <CircleCheckIcon strokeWidth={"3px"} color="#2CA95E" />
              {feature}
            </span>
          ))}
        </div>
        <Image
          src={imageSrc}
          alt={imageAlt}
          width={184}
          height={138}
          loading="lazy"
          className="h-auto"
          style={{ color: "transparent" }}
        />
      </div>
      <button
        id={buttonVariant}
        onClick={onButtonClick}
        className={`text-center cursor-pointer rounded-[8px] w-auto min-w-fit p-3 text-xl font-bold h-12.5 ${buttonColorClasses}`}
      >
        {buttonLabel}
      </button>
    </section>
  );
}

export default function HomeBannerThree() {
  return (
    <div className="flex flex-col md:flex-row gap-5 container">
      <EntryPointWidget
        title="Earn Money by Selling Anything"
        features={[
          "Reach millions of people",
          "Post your listing and sell anything",
          "Sell whatever you want at the best price",
        ]}
        imageSrc="https://opensooqui2.os-cdn.com/prod/public/images/entryPoints/addListing.webp"
        imageAlt="Add New Listing"
        buttonLabel="Add New Listing"
        buttonVariant="main"
      />
      <EntryPointWidget
        title="Get More Listings & Sell More Fast"
        features={[
          "Get discounts for bulk listings",
          "Sell more items",
          "Earn more money",
        ]}
        imageSrc="https://opensooqui2.os-cdn.com/prod/public/images/entryPoints/getNewListing.webp"
        imageAlt="Get New Listing Image"
        buttonLabel="Get More Listings"
        buttonVariant="green"
      />
    </div>
  );
}

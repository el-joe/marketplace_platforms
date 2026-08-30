"use client";
import SectionTitle from "./page-builder/sections/section-title";
import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation } from "swiper/modules";
import ListingCard, { IDemoListing } from "./listing-card";

export const listingItems: IDemoListing[] = [
  {
    id: 1,
    images: [
      "https://opensooq-images.os-cdn.com/previews/300x0/98/a1/98a1ac741edfb627d9b5b750cd2b7b52d218e907c738d08d4b437a21ed4177f8.jpg.webp",
      "https://opensooq-images.os-cdn.com/previews/300x0/98/a1/98a1ac741edfb627d9b5b750cd2b7b52d218e907c738d08d4b437a21ed4177f8.jpg.webp",
    ],
    location: "",
    phone: "01111111",
    status: "used",
    price: 999,
    title: "2 Bedrooms",
    shortDescription:
      "Lorem ipsum dolor sit amet consectetur, adipisicing elit.",
  },
  {
    id: 2,
    images: [
      "https://opensooq-images.os-cdn.com/previews/300x0/8e/75/8e75231bea36d90cbb30ce45867c32412652350ec8367b1e21d78fac45a689c0.webp.webp",
      "https://opensooq-images.os-cdn.com/previews/300x0/8e/75/8e75231bea36d90cbb30ce45867c32412652350ec8367b1e21d78fac45a689c0.webp.webp",
    ],
    location: "",
    phone: "01111111",
    status: "used",
    price: 999,
    title: "2 Bedrooms",
    shortDescription:
      "Lorem ipsum dolor sit amet consectetur, adipisicing elit.",
  },
  {
    id: 3,
    images: [
      "https://opensooq-images.os-cdn.com/previews/300x0/24/1b/241b2d4f56f5bc7504d5c5828f1fca83b46e92d21a3e2b740762700acff1eb26.jpg.webp",
      "https://opensooq-images.os-cdn.com/previews/300x0/24/1b/241b2d4f56f5bc7504d5c5828f1fca83b46e92d21a3e2b740762700acff1eb26.jpg.webp",
    ],
    location: "",
    phone: "01111111",
    status: "used",
    price: 999,
    title: "2 Bedrooms",
    shortDescription:
      "Lorem ipsum dolor sit amet consectetur, adipisicing elit.",
  },
  {
    id: 4,
    images: [
      "https://opensooq-images.os-cdn.com/previews/300x0/48/2b/482bc5659ef07533f3e6dab45122aa5e3af14accc86ecab9eb2ec9ee488d0e53.jpg.webp",
      "https://opensooq-images.os-cdn.com/previews/300x0/48/2b/482bc5659ef07533f3e6dab45122aa5e3af14accc86ecab9eb2ec9ee488d0e53.jpg.webp",
    ],
    location: "",
    phone: "01111111",
    status: "used",
    price: 999,
    title: "2 Bedrooms",
    shortDescription:
      "Lorem ipsum dolor sit amet consectetur, adipisicing elit.",
  },
  {
    id: 5,
    images: [
      "https://opensooq-images.os-cdn.com/previews/300x0/ff/0c/ff0c3291c7174d8d5d2a70bfe79a45c03295521e404774d119e67ec8cbbca8f7.jpg.webp",
      "https://opensooq-images.os-cdn.com/previews/300x0/ff/0c/ff0c3291c7174d8d5d2a70bfe79a45c03295521e404774d119e67ec8cbbca8f7.jpg.webp",
    ],
    location: "",
    phone: "01111111",
    status: "used",
    price: 999,
    title: "2 Bedrooms",
    shortDescription:
      "Lorem ipsum dolor sit amet consectetur, adipisicing elit.",
  },
  {
    id: 6,
    images: [
      "https://opensooq-images.os-cdn.com/previews/300x0/ff/0c/ff0c3291c7174d8d5d2a70bfe79a45c03295521e404774d119e67ec8cbbca8f7.jpg.webp",
      "https://opensooq-images.os-cdn.com/previews/300x0/ff/0c/ff0c3291c7174d8d5d2a70bfe79a45c03295521e404774d119e67ec8cbbca8f7.jpg.webp",
    ],
    location: "",
    phone: "01111111",
    status: "used",
    price: 999,
    title: "2 Bedrooms",
    shortDescription:
      "Lorem ipsum dolor sit amet consectetur, adipisicing elit.",
  },
  {
    id: 7,
    images: [
      "https://opensooq-images.os-cdn.com/previews/300x0/ff/0c/ff0c3291c7174d8d5d2a70bfe79a45c03295521e404774d119e67ec8cbbca8f7.jpg.webp",
      "https://opensooq-images.os-cdn.com/previews/300x0/ff/0c/ff0c3291c7174d8d5d2a70bfe79a45c03295521e404774d119e67ec8cbbca8f7.jpg.webp",
    ],
    location: "",
    phone: "01111111",
    status: "used",
    price: 999,
    title: "2 Bedrooms",
    shortDescription:
      "Lorem ipsum dolor sit amet consectetur, adipisicing elit.",
  },
];

type props = {
  title: string;
  showViewAllButton?: boolean;
  items?: IDemoListing[];
};

const CarouselListings = ({
  title,
  showViewAllButton,
  items = listingItems,
}: props) => {
  if (!items.length) return null;

  return (
    <div className="container py-6">
      <SectionTitle title={title} showVewAllButton={showViewAllButton} />
      <Swiper
        modules={[Navigation]}
        navigation
        slidesPerView={"auto"}
        spaceBetween={8}
        breakpoints={{
          768: {
            spaceBetween: 12,
          },
          1024: {
            spaceBetween: 18,
          },
        }}
      >
        {items.map((listing) => (
          <SwiperSlide key={listing.id} className="w-fit! h-auto!">
            <ListingCard listingData={listing} />
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  );
};

export default CarouselListings;

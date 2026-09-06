import { ClassifiedDetail } from "./types";

export const MOCK_CLASSIFIED_DETAIL: ClassifiedDetail = {
  id: "listing-266147908",
  listingId: "266147908",
  slug: "kia-sportage-2026-long-chassis-kuwait",
  titleAr: "سبورتاج لونج شاسيه 2026 - وارد الكويت",
  titleEn: "2026 Kia Sportage EX",
  price: 2050000,
  currency: "EGP",
  isFavorite: false,
  favoritesCount: 4,
  rating: 4.3,
  reviewsCount: 1,
  promotedBadge: "Promoted Turbo",
  quickSpecs: [
    { type: "tag", label: "New" },
    { type: "speedometer", label: "2 km" },
    { type: "gas", label: "Gasoline" },
    { type: "dealership", label: "Dealership" },
  ],
  images: [
    {
      id: "img-1",
      url: "https://images.unsplash.com/photo-1583121274602-3e2820c69888?w=1200&auto=format&fit=crop&q=80",
      alt: "Kia Sportage 2026 Front Quarter View",
      isPrimary: true,
    },
    {
      id: "img-2",
      url: "https://images.unsplash.com/photo-1617814076367-b759c7d7e738?w=800&auto=format&fit=crop&q=80",
      alt: "Kia Sportage 2026 Side Profile View",
    },
    {
      id: "img-3",
      url: "https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=800&auto=format&fit=crop&q=80",
      alt: "Kia Sportage 2026 Rear View",
    },
    {
      id: "img-4",
      url: "https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&auto=format&fit=crop&q=80",
      alt: "Kia Sportage 2026 Details View",
    },
    {
      id: "img-5",
      url: "https://images.unsplash.com/photo-1549399542-7e3f8b79c341?w=800&auto=format&fit=crop&q=80",
      alt: "Kia Sportage 2026 Interior View",
    },
  ],
  specs: {
    column1: [
      { label: "Body Type", value: "SUV" },
      { label: "Number of Seats", value: "5" },
      { label: "Transmission", value: "Automatic" },
      { label: "Engine Size (cc)", value: "1,000 - 1,599 cc" },
      { label: "Exterior Color", value: "Nardo Grey" },
      { label: "Interior Color", value: "Nardo Grey" },
      { label: "Payment Method", value: "Cash" },
      { label: "Car Make", value: "Kia" },
    ],
    column2: [
      {
        label: "Location on Map",
        value: "Ask for Exact Location",
        isLink: true,
        href: "#map",
      },
      { label: "Neighborhood", value: "Other" },
      { label: "City", value: "Minya" },
      { label: "Sub Category", value: "Cars for Sale" },
      { label: "Category", value: "Autos" },
      { label: "Listing Id", value: "266147908" },
      { label: "Published Date", value: "04-09-2026" },
    ],
  },
  description: `سبورتاج لونج شاسيه 2026 - وارد الكويت زيرو
عليها فيلم بروتكشن حماية كامل اصلي امريكي 8 ملي
عازل حراري للزجاج الامامي والجوانب
رخصة 3 سنوات`,
  features: [
    {
      name: "Interior",
      count: 15,
      items: [
        "Center Lock",
        "Air Condition",
        "Heated Seats",
        "Alarm System",
        "CD player",
      ],
    },
    {
      name: "Exterior",
      count: 11,
      items: [
        "Electric Mirrors",
        "Xenon Lights",
        "Daytime Running Lights",
        "LED Lights",
        "Spare Tyre",
      ],
    },
    {
      name: "Technology",
      count: 18,
      items: [
        "Blind Spot Alert",
        "Traction Control",
        "Tyre Pressure Monitoring",
        "Cruise Control",
        "Touch Screen",
      ],
    },
  ],
  seller: {
    id: "seller-abu-mina",
    name: "ابو مينا",
    avatar: "",
    rating: 0,
    reviewsCount: 0,
    memberSince: "06-06-2022",
    totalListings: 1,
    phone: "01202808799",
    phoneMasked: "012028087XX",
    isVerified: true,
  },
  recommended: [
    {
      id: "rec-1",
      title: "Used Hyundai Tucson In Cairo",
      specs: "Hyundai, Tucson, 2025, Used",
      location: "Ain Shams, Cairo",
      image:
        "https://images.unsplash.com/photo-1549399542-7e3f8b79c341?w=600&auto=format&fit=crop&q=80",
      slug: "used-hyundai-tucson-cairo",
      isFavorite: false,
    },
    {
      id: "rec-2",
      title: "Kia grand cerato",
      specs: "Kia, Cerato, 2020, Used",
      location: "Nasr City, Cairo",
      image:
        "https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=600&auto=format&fit=crop&q=80",
      slug: "kia-grand-cerato-nasr-city",
      isFavorite: false,
    },
    {
      id: "rec-3",
      title: "Used Kia Cerato In Cairo",
      specs: "Kia, Cerato, 2023, Used",
      image:
        "https://images.unsplash.com/photo-1617814076367-b759c7d7e738?w=600&auto=format&fit=crop&q=80",
      slug: "used-kia-cerato-cairo",
      isFavorite: false,
    },
    {
      id: "rec-4",
      title: "Toyota corolla model 2026 zero ...",
      specs: "Toyota, Corolla, 2026, New",
      image:
        "https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=600&auto=format&fit=crop&q=80",
      slug: "toyota-corolla-2026-zero",
      isFavorite: false,
    },
  ],
};

export const MOCK_CATEGORY_TAGS = [
  { ar: "سيارات للبيع", en: "Cars For Sale" },
  { ar: "دراجات نارية ومستلزمات", en: "Motorcycles & Accessories" },
  { ar: "قطع غيار المركبات", en: "Vehicle Spare Parts" },
  { ar: "إكسسوارات المركبات", en: "Vehicle Accessories" },
  { ar: "مركبات للإيجار", en: "Autos for Rent" },
  { ar: "اطارات و جنطات", en: "Tires & Rims" },
];

export const MOCK_SIDEBAR_CATEGORIES = [
  {
    id: "cars-for-sale",
    title: { ar: "سيارات للبيع", en: "Cars For Sale" },
    count: 1046,
    active: true,
  },
  {
    id: "motorcycles",
    title: { ar: "دراجات نارية ومستلزمات", en: "Motorcycles & Accessories" },
    count: 247,
  },
  {
    id: "spare-parts",
    title: { ar: "قطع غيار المركبات", en: "Vehicle Spare Parts" },
    count: 170,
  },
  {
    id: "accessories",
    title: { ar: "إكسسوارات المركبات", en: "Vehicle Accessories" },
    count: 87,
  },
];

export const MOCK_SEE_ALSO_LINKS = [
  { ar: "سيارات للبيع في مصر", en: "Cars For Sale In Egypt" },
  {
    ar: "دراجات نارية ومستلزمات في مصر",
    en: "Motorcycles & Accessories In Egypt",
  },
  { ar: "قطع غيار المركبات في مصر", en: "Vehicle Spare Parts In Egypt" },
  { ar: "إكسسوارات المركبات في مصر", en: "Vehicle Accessories In Egypt" },
  { ar: "مركبات للإيجار في مصر", en: "Autos for Rent In Egypt" },
  { ar: "اطارات و جنطات في مصر", en: "Tires, Wheels & Rims In Egypt" },
  {
    ar: "سكراب - سيارات ومركبات أخرى في مصر",
    en: "Junk Cars - Other Vehicles In Egypt",
  },
];

export const MOCK_LISTINGS = [
  {
    id: "listing-1",
    title: "سبورتاج لونج شاسيه 2026 - وارد الكويت",
    price: 2050000,
    currency: { ar: "جنيه", en: "EGP" },
    images: [
      "https://images.unsplash.com/photo-1583121274602-3e2820c69888?w=800&auto=format&fit=crop&q=80",
      "https://images.unsplash.com/photo-1617814076367-b759c7d7e738?w=800&auto=format&fit=crop&q=80",
      "https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=800&auto=format&fit=crop&q=80",
    ],
    time_ago: { ar: "قبل 8 ساعات", en: "8 hours ago" },
    location: { ar: "أخرى, المنيا", en: "Other, Minya" },
    category: { ar: "سيارات للبيع", en: "Cars for Sale" },
    phone: "012028087XX",
    badge: "rocket",
    specs: [
      { label: "2026" },
      { label: { ar: "كيا", en: "Kia" } },
      { label: { ar: "سبورتاج", en: "Sportage" } },
      { label: "EX" },
      { label: { ar: "جديد", en: "New" } },
      { label: { ar: "2 كم", en: "2 km" } },
      { label: { ar: "بنزين", en: "Gasoline" } },
      { label: { ar: "وارد وكاله", en: "Dealership" } },
    ],
  },
  {
    id: "listing-2",
    title: "سيارة بيجو 5008 العائلية",
    price: 730000,
    currency: { ar: "جنيه", en: "EGP" },
    images: [
      "https://images.unsplash.com/photo-1549399542-7e3f8b79c341?w=800&auto=format&fit=crop&q=80",
      "https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=800&auto=format&fit=crop&q=80",
    ],
    time_ago: "31-08-2026",
    location: { ar: "التجمع الخامس, القاهرة", en: "Fifth Settlement, Cairo" },
    category: { ar: "سيارات للبيع", en: "Cars for Sale" },
    phone: "011165344XX",
    is_verified_user: true,
    badge: "award",
    specs: [
      { label: "2015" },
      { label: { ar: "بيجو", en: "Peugeot" } },
      { label: "5008" },
      { label: "Premium" },
      { label: { ar: "مستعمل", en: "Used" } },
      { label: { ar: "100,000 كم", en: "100,000 km" } },
      { label: { ar: "بنزين", en: "Gasoline" } },
      { label: { ar: "مواصفات خليجية", en: "GCC Specs" } },
    ],
  },
  {
    id: "listing-3",
    title: "ايطالي تقفيل صيني Shark شارك 250 سي سي 2026",
    price: 140000,
    currency: { ar: "جنيه", en: "EGP" },
    images: [
      "https://images.unsplash.com/photo-1558981403-c5f9899a28bc?w=800&auto=format&fit=crop&q=80",
      "https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800&auto=format&fit=crop&q=80",
    ],
    time_ago: { ar: "قبل ساعتين", en: "2 hours ago" },
    location: { ar: "الكوثر, الغردقة", en: "El Kothar, Hurghada" },
    category: {
      ar: "دراجات نارية ومستلزمات",
      en: "Quad Bikes, Buggies And ATV",
    },
    phone: "010098765XX",
    specs: [{ label: { ar: "جديد", en: "New" } }],
  },
];

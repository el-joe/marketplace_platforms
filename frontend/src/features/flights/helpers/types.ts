export type ApiEnvelope<T> = {
  success: boolean;
  message?: string;
  data: T;
};

export type BookingStatus =
  | "pending_documents"
  | "confirmed"
  | "cancelled"
  | "completed";

export type BookingPackageSummary = {
  id: string;
  title: {
    ar: string;
    en: string;
  };
  price: number;
  currency: string;
  agency: { id: string; name: string };
  cover_image: string;
};

export type TravelBooking = {
  id: string;
  booking_number: string;
  status: BookingStatus;
  travelers_count: number;
  total_price: number;
  passport_uploaded: boolean;
  contract_signed_at: string | null;
  created_at: string;
  package: BookingPackageSummary;
};

export type TravelBookingDetail = TravelBooking;

export type BookingsListMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type BookingsListResponse = {
  items: TravelBooking[];
  meta: BookingsListMeta;
};

export type ListBookingsFilters = {
  status?: BookingStatus;
  page?: number;
};

export type CancelBookingResult = {
  status: BookingStatus;
};

export type CreateBookingResult = {
  id: string;
  booking_number: string;
  status: BookingStatus;
  travelers_count: number;
  total_price: number;
  currency: string;
  created_at: string;
  message: string | null;
};

export type TravelPackageCategorySummary = {
  name_en: string;
  slug: string;
};

export type TravelPackageDestination = {
  country_id: string | null;
  country_en: string | null;
  country_ar: string | null;
  city_id: string | null;
  city_en: string | null;
  city_ar: string | null;
};

export type TravelPackageSummary = {
  package_id: string;
  title_en: string;
  title_ar: string;
  slug: string;
  thumbnail: string;
  destination_country: string;
  destination_city: string;
  destination?: TravelPackageDestination;
  departure_date: string;
  return_date: string;
  duration_days: number;
  duration_nights: number;
  price: number;
  price_formatted: string;
  currency: string;
  available_seats: number;
  seats_remaining: number;
  agency_name: string;
  categories: TravelPackageCategorySummary[];
  link: string;
};

export type TravelPackagesListMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type TravelCategoryInfo = {
  id: string | null;
  name_en: string;
  name_ar: string | null;
  slug: string | null;
};

export type TravelAvailableCategory = {
  id: string;
  name: { ar: string; en: string };
  slug: string;
  icon: string | null;
  package_count: number;
};

export type TravelPackagesListResponse = {
  category: TravelCategoryInfo;
  available_categories: TravelAvailableCategory[];
  listings: {
    items: TravelPackageSummary[];
    meta: TravelPackagesListMeta;
  };
};

export type ListTravelPackagesFilters = {
  categoryId?: string;
  page?: number;
  perPage?: number;
  countryId?: string;
  cityId?: string;
  dateFrom?: string;
  dateTo?: string;
};

export type TravelPackageImage = {
  id: string;
  url: string;
  position: number;
};

export type TravelPackageDetail = {
  id: string;
  slug: string;
  title: { en: string; ar: string | null };
  description: { en: string | null; ar: string | null };
  destination_country: string;
  destination_city: string;
  price: number;
  price_formatted: string;
  currency: string;
  available_seats: number | null;
  seats_remaining: number | null;
  seats_booked: number;
  price_tiers: { travelers_count: number; price: number }[] | null;
  duration_days: number;
  duration_nights: number;
  departure_date: string;
  return_date: string;
  inclusions: { id: string; name: string; icon: string }[];
  images: TravelPackageImage[];
  categories: {
    id: string;
    name: { en: string; ar: string | null };
    slug: string;
  }[];
  agency: {
    id: string;
    name: string;
    logo_url: string | null;
    license_number: string;
    contact_email: string | null;
    contact_phone: string | null;
  } | null;
  bookable_units: BookableUnitSummary[];
  status: string;
};

export type BookableUnitSummary = {
  id: string;
  name: string;
  type: "chalet" | "hotel_room" | "other";
  capacity: number;
  description: string | null;
};

export type BookableUnitDay = {
  date: string;
  is_available: boolean;
  capacity: number;
  price_day_only: number | null;
  price_with_overnight: number | null;
};

export type BookableUnitTimeSlot = {
  id: string;
  slot_type: "morning" | "evening" | "custom";
  starts_at: string;
  ends_at: string;
  price: number;
};

export type BookableUnitCalendar = {
  unit: { id: string; name: string; type: string; capacity: number };
  month: string;
  days: BookableUnitDay[];
  time_slots: BookableUnitTimeSlot[];
};

export type BookableUnitReservation = {
  id: string;
  total_price: number;
  status: string;
};

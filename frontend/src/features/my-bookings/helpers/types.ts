export type UnifiedBookingType = "travel_package" | "bookable_unit" | "flight";

export type UnifiedBookingRawStatus =
  | "pending_documents"
  | "pending"
  | "confirmed"
  | "cancelled"
  | "completed";

export type UnifiedBooking = {
  id: string;
  type: UnifiedBookingType;
  booking_number: string;
  title: string;
  date_from: string;
  date_to: string;
  total_price: number;
  currency: string;
  status: UnifiedBookingRawStatus;
  agency_name: string | null;
  thumbnail_url: string | null;
};

export interface ICustomerProfile {
  id: string;
  name: string;
  email: string;
  phone: string;
  status: string;
  date_of_birth: null | string;
  total_orders: number;
  total_spent: number;
  loyalty_points: number;
  referral_code: string;
  email_verified: boolean;
  phone_verified: boolean;
  member_since: Date;
}

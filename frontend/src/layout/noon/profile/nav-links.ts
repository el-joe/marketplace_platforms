export interface NavItem {
  labelKey: string;
  href: string;
  icon: string;
}

export const accountNavItems: NavItem[] = [
  {
    labelKey: "orders",
    href: "/orders",
    icon: "/images/profile/orders-icon.svg",
  },
  {
    labelKey: "returns",
    href: "/returns",
    icon: "/images/profile/returns-icon.svg",
  },
  {
    labelKey: "myBookings",
    href: "/my-bookings",
    icon: "/images/profile/my-bookings-icon.svg",
  },
  {
    labelKey: "noonCredits",
    href: "/noon-credits",
    icon: "/images/profile/noon-credit-icon.svg",
  },
  {
    labelKey: "wishlist",
    href: "/wishlist",
    icon: "/images/profile/account-wishlist-v2.svg",
  },
];

export const myAccountNavItems: NavItem[] = [
  {
    labelKey: "profile",
    href: "/profile",
    icon: "/images/profile/profile-icon.svg",
  },
  {
    labelKey: "addresses",
    href: "/addresses",
    icon: "/images/profile/address-icon.svg",
  },
  {
    labelKey: "payments",
    href: "/payments",
    icon: "/images/profile/payments-icon.svg",
  },
  {
    labelKey: "warrantyClaims",
    href: "/warranty-claims",
    icon: "/images/profile/warranty-claims-icon.svg",
  },
  {
    labelKey: "giftCards",
    href: "/gift-cards",
    icon: "/images/profile/gift-cards-icon.svg",
  },
  // {
  //   labelKey: "digitalCards",
  //   href: "/digital-cards",
  //   icon: "/images/profile/digital-cards-icon.svg",
  // },
  {
    labelKey: "disputes",
    href: "/disputes",
    icon: "/images/profile/disputes.svg",
  },
];

export const othersNavItems: NavItem[] = [
  {
    labelKey: "notifications",
    href: "/notifications",
    icon: "/images/profile/nav-notification.svg",
  },
  {
    labelKey: "securitySettings",
    href: "/security-settings",
    icon: "/images/profile/security-settings.svg",
  },
  {
    labelKey: "qrCode",
    href: "/qr-code",
    icon: "/images/profile/account-qr-code.svg",
  },
];

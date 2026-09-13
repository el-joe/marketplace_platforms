import {
  BookmarkIcon,
  CreditCardIcon,
  KeyRoundIcon,
  MapPinIcon,
  MessageCircleIcon,
  RefreshCcwIcon,
  ShieldCheckIcon,
  ShieldOffIcon,
  TicketIcon,
  TruckIcon,
  UserIcon,
  WalletIcon,
} from "lucide-react";
import type { HelpFaqItem, HelpNode } from "../types";

const noonOneFaqItems: HelpFaqItem[] = [
  {
    id: "what-is-noon-one",
    question: "What is noon One?",
    description: "Everything to know about becoming a noon One member",
    answer:
      "noon One is our loyalty membership that gives you free and fast delivery, exclusive deals, and rewards across noon.",
  },
  {
    id: "noon-one-benefits",
    question: "What are the benefits of noon One?",
    description: "Exclusive services, offers, and deals",
    answer:
      "Members enjoy free next-day delivery, early access to sales, extra cashback, and dedicated support.",
  },
  {
    id: "how-to-subscribe",
    question: "How do I subscribe to noon One?",
    description: "Be a part of the noon One experience",
    answer:
      "To subscribe to noon One, click 'Subscribe' and select 'Join noon One'. Once you've confirmed your payment method, your subscription will start.",
  },
  {
    id: "cancel-membership",
    question: "How can I cancel my noon One membership?",
    description: "What are my next steps?",
    answer:
      "You can cancel anytime from My Profile > noon One > Manage membership. Your benefits stay active until the end of the billing cycle.",
  },
  {
    id: "terms-and-conditions",
    question: "What are the terms and conditions?",
    description: "Read our noon One T&Cs",
    answer:
      "Full terms are available on the noon One page, including renewal, refund, and eligibility details.",
  },
];

const mashreqCreditCardFaqItems: HelpFaqItem[] = [
  {
    id: "how-to-apply",
    question: "How do I apply for the Mashreq noon Credit Card?",
    description: "Start your application from your profile",
    answer:
      "Apply directly from the Payments section in your profile, or through the Mashreq Bank website using your noon account.",
  },
  {
    id: "card-benefits",
    question: "What benefits do I get with the card?",
    description: "Cashback and Mashreq card perks",
    answer:
      "Cardholders earn extra cashback on noon purchases plus standard Mashreq credit card benefits.",
  },
];

const noonSavingsAccountFaqItems: HelpFaqItem[] = [
  {
    id: "what-is-savings-account",
    question: "What is the noon Savings Account?",
    description: "Save and earn returns with Mashreq",
    answer:
      "It's a digital savings account offered with Mashreq that lets you save and earn returns directly from the noon app.",
  },
  {
    id: "how-to-open-account",
    question: "How do I open a noon Savings Account?",
    description: "Complete onboarding from your profile",
    answer:
      "Open the account from the Payments section of your profile and complete the Mashreq onboarding steps.",
  },
];

export const helpTree: HelpNode = {
  id: "root",
  title: "How can we help?",
  description: "",
  icon: MessageCircleIcon,
  type: "nested",
  children: [
    {
      id: "orders",
      title: "Orders",
      description: "Manage, track, and modify your orders",
      icon: BookmarkIcon,
      type: "action",
      actionType: "NAVIGATE_ORDERS",
      viewType: "items-list",
    },
    {
      id: "returns-warranty-refunds",
      title: "Returns, Warranty and Refunds",
      description: "Returns, warranty details, and refund processes",
      icon: ShieldOffIcon,
      type: "nested",
      children: [
        {
          id: "returns",
          title: "Returns",
          description: "Everything about your return tracking and management",
          icon: RefreshCcwIcon,
          type: "action",
          actionType: "NAVIGATE_RETURNS",
          viewType: "items-list",
        },
        {
          id: "warranty",
          title: "Warranty",
          description: "How your product warranties work",
          icon: ShieldCheckIcon,
          type: "action",
          actionType: "NAVIGATE_WARRANTY",
          viewType: "items-list",
        },
        {
          id: "refunds",
          title: "Refunds",
          description: "Learn about your refund statuses and timelines",
          icon: CreditCardIcon,
          type: "action",
          actionType: "NAVIGATE_REFUNDS",
          viewType: "items-list",
        },
      ],
    },
    {
      id: "payments",
      title: "Payments",
      description: "Explore noonCredits, cashback and payment methods",
      icon: CreditCardIcon,
      type: "nested",
      children: [
        {
          id: "payment-methods",
          title: "Payment methods",
          description: "Manage your saved cards and payment options",
          icon: CreditCardIcon,
          type: "action",
          actionType: "NAVIGATE_PAYMENTS",
          viewType: "items-list",
        },
        {
          id: "noon-credits",
          title: "noonCredits",
          description: "Check your balance and transaction history",
          icon: WalletIcon,
          type: "action",
          actionType: "NAVIGATE_NOON_CREDITS",
          viewType: "items-list",
        },
        {
          id: "cashback",
          title: "Cashback",
          description: "Track cashback earned on your orders",
          icon: WalletIcon,
          type: "action",
          actionType: "NAVIGATE_CASHBACK",
          viewType: "items-list",
        },
      ],
    },
    {
      id: "noon-one-mashreq",
      title: "noon One and Mashreq Cards",
      description: "Get assistance with our loyalty programs and Mashreq cards",
      icon: CreditCardIcon,
      type: "nested",
      children: [
        {
          id: "noon-one",
          title: "noon One",
          description: "Exclusive benefits with noon One",
          icon: TicketIcon,
          type: "action",
          actionType: "NAVIGATE_NOON_ONE",
          viewType: "faq",
          faqItems: noonOneFaqItems,
        },
        {
          id: "mashreq-cards",
          title: "Mashreq Cards",
          description:
            "Get assistance with our loyalty programs and Mashreq cards",
          icon: CreditCardIcon,
          type: "nested",
          children: [
            {
              id: "mashreq-credit-card-faq",
              title: "Mashreq noon Credit Card FAQ",
              description: "",
              icon: CreditCardIcon,
              type: "action",
              actionType: "NAVIGATE_MASHREQ_CARDS",
              viewType: "faq",
              faqItems: mashreqCreditCardFaqItems,
            },
            {
              id: "noon-savings-account-faq",
              title: "noon Savings Account FAQ",
              description: "",
              icon: WalletIcon,
              type: "action",
              actionType: "NAVIGATE_MASHREQ_CARDS",
              viewType: "faq",
              faqItems: noonSavingsAccountFaqItems,
            },
          ],
        },
      ],
    },
    {
      id: "my-profile",
      title: "My Profile",
      description: "Manage your profile and personalize your experience",
      icon: UserIcon,
      type: "nested",
      children: [
        {
          id: "profile-details",
          title: "Profile details",
          description: "Update your name, email, and phone number",
          icon: UserIcon,
          type: "action",
          actionType: "NAVIGATE_PROFILE",
          viewType: "items-list",
        },
        {
          id: "addresses",
          title: "Addresses",
          description: "Manage your saved delivery addresses",
          icon: MapPinIcon,
          type: "action",
          actionType: "NAVIGATE_ADDRESSES",
          viewType: "items-list",
        },
        {
          id: "account-security",
          title: "Account & Security",
          description: "Password, login, and account protection",
          icon: KeyRoundIcon,
          type: "action",
          actionType: "NAVIGATE_SECURITY",
          viewType: "items-list",
        },
      ],
    },
    {
      id: "pre-order-other",
      title: "Pre-order & Other Topics",
      description: "General inquiries, promotions and campaigns assistance",
      icon: TruckIcon,
      type: "action",
      actionType: "NAVIGATE_PREORDER",
      viewType: "items-list",
    },
  ],
};

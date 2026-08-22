import type { DisputeDetails, SupportTicketDetails } from "./types";

const mockTickets: SupportTicketDetails[] = [
  {
    id: 42,
    ticket_number: "TKT-9F2KQZLP",
    category: "order_issue",
    priority: "normal",
    status: "resolved",
    subject: "Item missing from my order",
    created_at: "2026-07-01T10:22:31+00:00",
    resolved_at: "2026-07-03T14:00:00+00:00",
    satisfaction_rating: 4,
    satisfaction_comment: "Handled quickly, thanks!",
    messages: [
      {
        id: 101,
        sender_role: "customer",
        message: "One item from my order is missing, please help.",
        created_at: "2026-07-01T10:22:31+00:00",
        attachments: [
          {
            url: "https://images.unsplash.com/photo-1607082349566-187342175e2f?w=400",
            name: "photo.jpg",
          },
        ],
      },
      {
        id: 102,
        sender_role: "support",
        message:
          "Sorry to hear that! Could you confirm which item was missing from order NOON-20260701-XY12?",
        created_at: "2026-07-01T11:05:10+00:00",
        attachments: [],
      },
      {
        id: 103,
        sender_role: "customer",
        message: "It was the wireless charger, item #3 in the order.",
        created_at: "2026-07-01T11:20:44+00:00",
        attachments: [],
      },
      {
        id: 104,
        sender_role: "support",
        message:
          "Thanks for confirming. We've shipped a replacement free of charge — it should arrive within 2 business days.",
        created_at: "2026-07-03T14:00:00+00:00",
        attachments: [],
      },
    ],
  },
  {
    id: 43,
    ticket_number: "TKT-3H7BXQWM",
    category: "payment",
    priority: "high",
    status: "in_progress",
    subject: "Charged twice for the same order",
    created_at: "2026-07-06T08:40:00+00:00",
    resolved_at: null,
    satisfaction_rating: null,
    satisfaction_comment: null,
    messages: [
      {
        id: 105,
        sender_role: "customer",
        message:
          "I see two charges of 249 AED on my card for order NOON-20260706-QR90. Can you check?",
        created_at: "2026-07-06T08:40:00+00:00",
        attachments: [
          {
            url: "https://images.unsplash.com/photo-1580519542036-c47de6196ba5?w=400",
            name: "bank-statement.png",
          },
        ],
      },
      {
        id: 106,
        sender_role: "support",
        message:
          "Thanks for flagging this — we're checking with our payments team and will update you within 24 hours.",
        created_at: "2026-07-06T09:15:00+00:00",
        attachments: [],
      },
    ],
  },
  {
    id: 44,
    ticket_number: "TKT-1K9DPLNZ",
    category: "account",
    priority: "low",
    status: "open",
    subject: "Can't update my delivery address",
    created_at: "2026-07-09T16:02:00+00:00",
    resolved_at: null,
    satisfaction_rating: null,
    satisfaction_comment: null,
    messages: [
      {
        id: 107,
        sender_role: "customer",
        message:
          "The 'Save address' button doesn't respond on my profile page.",
        created_at: "2026-07-09T16:02:00+00:00",
        attachments: [],
      },
    ],
  },
  {
    id: 45,
    ticket_number: "TKT-7QANV2XE",
    category: "shipping",
    priority: "urgent",
    status: "open",
    subject: "Package stuck in transit for 8 days",
    created_at: "2026-07-10T07:12:00+00:00",
    resolved_at: null,
    satisfaction_rating: null,
    satisfaction_comment: null,
    messages: [
      {
        id: 108,
        sender_role: "customer",
        message:
          "Tracking hasn't updated in over a week for order NOON-20260628-ZT44. Where is it?",
        created_at: "2026-07-10T07:12:00+00:00",
        attachments: [],
      },
    ],
  },
  {
    id: 46,
    ticket_number: "TKT-5MYCF8RJ",
    category: "order_issue",
    priority: "normal",
    status: "closed",
    subject: "Wrong size delivered",
    created_at: "2026-06-20T12:30:00+00:00",
    resolved_at: "2026-06-24T09:00:00+00:00",
    satisfaction_rating: 5,
    satisfaction_comment: "Great support, resolved fast.",
    messages: [
      {
        id: 109,
        sender_role: "customer",
        message: "I ordered a size L but received a size M.",
        created_at: "2026-06-20T12:30:00+00:00",
        attachments: [],
      },
      {
        id: 110,
        sender_role: "support",
        message:
          "Apologies! A free exchange has been arranged and is on its way.",
        created_at: "2026-06-24T09:00:00+00:00",
        attachments: [],
      },
    ],
  },
];

const mockDisputes: DisputeDetails[] = [
  {
    id: "d-uuid-0000-4000-8000-000000000011",
    dispute_number: "DSP-AB12CD34",
    order_number: "NOON-20260706-AB12CD",
    reason: "item_not_received",
    description: "Never arrived",
    status: "under_review",
    resolution: null,
    resolution_notes: null,
    compensation: null,
    resolved_at: null,
    created_at: "2026-07-06T12:00:00+00:00",
    messages: [
      {
        id: "msg-uuid-0000-4000-8000-000000000012",
        sender_role: "customer",
        message: "Never arrived",
        created_at: "2026-07-06T12:00:00+00:00",
        attachments: [],
      },
      {
        id: "msg-uuid-0000-4000-8000-000000000013",
        sender_role: "support",
        message:
          "We're sorry about that. We've opened an investigation with the courier and will update you within 48 hours.",
        created_at: "2026-07-06T15:30:00+00:00",
        attachments: [],
      },
    ],
  },
  {
    id: "d-uuid-0000-4000-8000-000000000021",
    dispute_number: "DSP-EF56GH78",
    order_number: "NOON-20260628-EF56GH",
    reason: "item_not_as_described",
    description:
      "The color of the sofa is completely different from the listing photos.",
    status: "resolved",
    resolution: "partial_refund",
    resolution_notes: "20% partial refund issued as goodwill compensation.",
    compensation: "AED 180.00",
    resolved_at: "2026-07-02T10:00:00+00:00",
    created_at: "2026-06-29T09:20:00+00:00",
    messages: [
      {
        id: "msg-uuid-0000-4000-8000-000000000022",
        sender_role: "customer",
        message:
          "The color of the sofa is completely different from the listing photos.",
        created_at: "2026-06-29T09:20:00+00:00",
        attachments: [
          {
            url: "https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=400",
            name: "sofa-received.jpg",
          },
        ],
      },
      {
        id: "msg-uuid-0000-4000-8000-000000000023",
        sender_role: "support",
        message:
          "Thanks for the photo — we agree the shade doesn't match. We've issued a partial refund of AED 180.00.",
        created_at: "2026-07-02T10:00:00+00:00",
        attachments: [],
      },
    ],
  },
  {
    id: "d-uuid-0000-4000-8000-000000000031",
    dispute_number: "DSP-IJ90KL12",
    order_number: "NOON-20260630-IJ90KL",
    reason: "damaged_on_arrival",
    description: "Screen was cracked when the box was opened.",
    status: "escalated",
    resolution: null,
    resolution_notes: null,
    compensation: null,
    resolved_at: null,
    created_at: "2026-07-04T18:45:00+00:00",
    messages: [
      {
        id: "msg-uuid-0000-4000-8000-000000000032",
        sender_role: "customer",
        message: "Screen was cracked when the box was opened.",
        created_at: "2026-07-04T18:45:00+00:00",
        attachments: [
          {
            url: "https://images.unsplash.com/photo-1517059224940-d4af9eec41b7?w=400",
            name: "cracked-screen.jpg",
          },
        ],
      },
    ],
  },
  {
    id: "d-uuid-0000-4000-8000-000000000041",
    dispute_number: "DSP-MN34OP56",
    order_number: "NOON-20260615-MN34OP",
    reason: "wrong_item",
    description: "Received a completely different product than what I ordered.",
    status: "rejected",
    resolution: "no_action",
    resolution_notes:
      "Order details and delivery photos confirmed the correct item was shipped.",
    compensation: null,
    resolved_at: "2026-06-22T13:00:00+00:00",
    created_at: "2026-06-18T11:10:00+00:00",
    messages: [
      {
        id: "msg-uuid-0000-4000-8000-000000000042",
        sender_role: "customer",
        message: "Received a completely different product than what I ordered.",
        created_at: "2026-06-18T11:10:00+00:00",
        attachments: [],
      },
      {
        id: "msg-uuid-0000-4000-8000-000000000043",
        sender_role: "support",
        message:
          "After review, delivery photos and the order manifest confirm the correct item was shipped. We're unable to approve this dispute.",
        created_at: "2026-06-22T13:00:00+00:00",
        attachments: [],
      },
    ],
  },
];

export function getTickets(): SupportTicketDetails[] {
  return mockTickets;
}

export function getTicketById(id: string): SupportTicketDetails | undefined {
  return mockTickets.find((t) => String(t.id) === id);
}

export function getDisputes(): DisputeDetails[] {
  return mockDisputes;
}

export function getDisputeById(id: string): DisputeDetails | undefined {
  return mockDisputes.find((d) => d.id === id);
}

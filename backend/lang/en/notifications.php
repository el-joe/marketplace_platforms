<?php

return [

    'ads' => [
        'block_hidden' => [
            'title' => 'Your ad was hidden',
            'message' => 'The page block hosting your ad slot ":slot_code" was hidden by an admin. Your ad has stopped rendering, but your booking is still active.',
        ],
        'position_shifted' => [
            'title' => 'Your ad position changed',
            'message' => 'Your ad slot ":slot_code" moved from position :old_rank to position :new_rank after the page block was reordered.',
        ],
        'page_archived' => [
            'title' => 'Your ad went offline',
            'message' => 'The page ":page_name" hosting your ad slot ":slot_code" was archived. Your ad has stopped rendering, but your booking is still active.',
        ],
        'booking_submitted' => [
            'title' => 'New Ad Booking to Review',
            'message' => ':advertiser submitted ad booking :reference for review.',
        ],
        'booking_approved' => [
            'title' => 'Ad Booking Approved',
            'scheduled' => 'Your ad booking :reference was approved and is scheduled to go live on :date.',
            'live' => 'Your ad booking :reference was approved and is live now.',
            'payment_due' => 'Your ad booking :reference was approved. Please pay by :payment_due_at to go live.',
        ],
        'booking_rejected' => [
            'title' => 'Ad Booking Rejected',
            'message' => 'Your ad booking :reference was rejected. Reason: :reason',
        ],
        'creative_rejected' => [
            'title' => 'Ad Creative Rejected',
            'message' => 'Your ad creative for booking :reference was rejected (:code). Reason: :reason',
            'no_code' => 'no code',
        ],
        'payment_reminder' => [
            'title' => 'Payment Due Soon',
            'message' => 'Payment for ad booking :reference is due by :payment_due_at. Pay now to avoid losing your slot.',
        ],
        'booking_live' => [
            'title' => 'Your Ad Is Live',
            'message' => 'Ad booking :reference is now live.',
        ],
        'booking_paused' => [
            'title' => 'Ad Booking Paused',
            'message' => 'Ad booking :reference was paused. Reason: :reason',
        ],
        'booking_completed' => [
            'title' => 'Ad Booking Completed',
            'message' => 'Ad booking :reference has finished: :impressions impressions, :clicks clicks, :spend :currency spent.',
        ],
        'booking_cancelled' => [
            'title' => 'Ad Booking Cancelled',
            'message' => 'Ad booking :reference was cancelled. Refund: :refund :currency.',
        ],
        'booking_expired' => [
            'title' => 'Ad Booking Expired',
            'message' => 'Ad booking :reference expired before payment was completed.',
        ],
    ],

];

<?php

/*
|--------------------------------------------------------------------------
| Customer-facing API messages
|--------------------------------------------------------------------------
|
| Human-readable strings returned in JSON responses from
| app/Http/Controllers/Api/Customer/**. Keep this file organized by
| controller so the PHP __('customer_api.checkout.foo') calls map
| directly onto this nested structure. Internal/machine-readable codes
| (header names, enum values, log/audit descriptions) do not belong here.
|
*/

return [

    'validation_failed' => 'Validation failed.',
    'not_implemented'   => 'Not implemented.',

    'checkout' => [
        'calculation_failed'          => 'Checkout calculation failed.',
        'preview_calculated'          => 'Checkout preview calculated',
        'invalid_coupon_code'         => 'Invalid coupon code.',
        'coupon_not_active'           => 'Coupon is not active.',
        'coupon_not_valid_now'        => 'Coupon is not valid at this time.',
        'coupon_usage_limit_reached'  => 'Coupon usage limit reached.',
        'coupon_max_uses_reached'     => 'You have already used this coupon the maximum number of times.',
        'coupon_currency_mismatch'    => 'Coupon currency does not match your country.',
        'coupon_valid'                => 'Coupon is valid',
        'order_already_placed'        => 'Order already placed.',
        'insufficient_stock'          => 'Insufficient stock for one or more items. Please update your cart.',
        'gift_card_balance_changed'   => 'Gift card balance changed. Please try again.',
        'wallet_balance_changed'      => 'Wallet balance changed or wallet is frozen. Please try again.',
        'order_placed_successfully'   => 'Order placed successfully',
    ],

    'coupon' => [
        'applied' => 'Coupon applied successfully',
        'removed' => 'Coupon removed successfully',
    ],

    'gift_card_store' => [
        'purchased'             => 'Gift card purchased successfully.',
        'purchase_not_found'    => 'Gift card purchase not found.',
        'not_yet_available'     => 'Gift card is not yet available for delivery.',
        'max_resend_reached'    => 'Maximum resend attempts reached. Please contact support.',
        'delivery_email_resent' => 'Delivery email has been resent.',
    ],

    'customer_wallet' => [
        'gift_card_redeemed' => 'Gift card redeemed. :currency :amount has been added to your wallet.',
        'voucher_redeemed'   => 'Voucher redeemed. :currency :amount has been added to your wallet.',
    ],

    'gift_card' => [
        'invalid' => 'Gift card is invalid, expired, or not usable in this currency.',
    ],

    'listing' => [
        'country_not_found'           => 'Country not found or not active.',
        'not_found'                   => 'Listing not found or not available in this country.',
        'shipping_zone_unresolvable'  => 'Unable to resolve a shipping zone for this address.',
        'shipping_method_not_found'   => 'Shipping method not found.',
    ],

    'misc' => [
        'country_not_found' => 'Country not found.',
    ],

    'newsletter' => [
        'subscribed'    => 'You have been subscribed to our newsletter.',
        'resubscribed'  => 'Welcome back! You have been re-subscribed to our newsletter.',
        'unsubscribed'  => 'You have been unsubscribed from our newsletter.',
        'invalid_token' => 'Invalid or expired unsubscribe link.',
    ],

    'notification' => [
        'not_found'             => 'Notification not found.',
        'all_marked_read'       => 'All notifications marked as read.',
        'device_registered'     => 'Device registered.',
        'device_removed'        => 'Device removed.',
        'preferences_updated'   => 'Notification preferences updated.',
    ],

    'order' => [
        'retrieved'              => 'Orders retrieved',
        'not_found'              => 'Order not found.',
        'single_retrieved'       => 'Order retrieved',
        'sub_order_not_found'    => 'Sub-order not found.',
        'sub_order_retrieved'    => 'Sub-order retrieved',
        'tracking_retrieved'     => 'Tracking retrieved',
        'cannot_cancel_status'   => 'This order cannot be cancelled in its current status.',
        'cancelled_successfully' => 'Order cancelled successfully',
        'invoice_retrieved'      => 'Invoice data retrieved',
    ],

    'product_detail' => [
        'not_found'          => 'Product not found.',
        'country_not_found'  => 'Country not found or not active.',
        'listing_not_found'  => 'Listing not found',
    ],

    'return_request' => [
        'submitted'    => 'Return request submitted.',
        'not_found'    => 'Return request not found.',
        'message_sent' => 'Message sent.',
    ],

    'security' => [
        'current_password_incorrect' => 'Current password is incorrect.',
        'password_changed'           => 'Password changed successfully. Please log in again.',
        'email_already_verified'     => 'Email is already verified.',
        'otp_sent_to'                => 'OTP sent to :destination',
        'invalid_or_expired_otp'     => 'Invalid or expired OTP.',
        'email_verified'             => 'Email verified successfully.',
        'phone_already_verified'     => 'Phone is already verified.',
        'phone_verified'             => 'Phone verified successfully.',
        'sessions_retrieved'         => 'Active sessions retrieved.',
        'device_not_found'           => 'Device not found.',
        'device_revoked'             => 'Device revoked.',
        'all_devices_revoked'        => 'All devices revoked. Please log in again.',
        'otp_wait'                   => 'Please wait :seconds seconds before requesting another OTP.',
    ],

    'warranty' => [
        'order_item_not_found' => 'Order item not found.',
        'already_has_warranty' => 'This item already has a warranty.',
        'no_plans_available'   => 'No warranty plans available for this item.',
        'plans_retrieved'      => 'Warranty plans retrieved.',
        'claim_submitted'      => 'Warranty claim submitted.',
        'claim_not_found'      => 'Warranty claim not found.',
        'claim_closed'         => 'This warranty claim is closed and no longer accepts messages.',
        'message_sent'         => 'Message sent.',
    ],

    'wishlist' => [
        'max_groups_reached'    => 'You can have at most 20 wishlist groups.',
        'group_created'         => 'Wishlist group created.',
        'group_not_found'       => 'Wishlist group not found.',
        'name_empty'            => 'Name cannot be empty.',
        'group_updated'         => 'Group updated.',
        'cannot_delete_default' => 'Cannot delete your default wishlist.',
        'group_deleted'         => 'Group deleted. Items moved to your default wishlist.',
        'country_not_found'     => 'Country not found or not active.',
        'listing_not_found'     => 'Listing not found or unavailable.',
        'already_in_wishlist'   => 'Already in this wishlist.',
        'added_to_wishlist'     => 'Added to wishlist.',
        'item_not_found'        => 'Wishlist item not found.',
        'target_group_not_found' => 'Target wishlist group not found.',
        'items_moved'           => ':count item(s) moved.',
    ],

];

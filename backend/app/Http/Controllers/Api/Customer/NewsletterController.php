<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    /**
     * POST /v1/{country}/newsletter/subscribe
     *
     * Guest + authenticated customers can subscribe.
     * Idempotent: re-subscribing a previously unsubscribed email re-activates it.
     */
    public function subscribe(Request $request, $country): JsonResponse
    {
        $country = $request->attributes->get('country');

        $request->validate([
            'email'  => ['required', 'email', 'max:191'],
            'locale' => ['nullable', 'in:ar,en'],
            'source' => ['nullable', 'in:website,app,checkout,page_builder'],
        ]);

        $email    = strtolower(trim($request->input('email')));
        $locale   = $request->input('locale', app()->getLocale());
        $source   = $request->input('source', 'website');
        $customer = auth('customer')->user();

        $subscriber = NewsletterSubscriber::firstOrNew([
            'email'      => $email,
            'country_id' => $country->id,
        ]);

        $wasUnsubscribed = $subscriber->status === 'unsubscribed';

        $subscriber->fill([
            'customer_id'       => $subscriber->customer_id ?? $customer?->id,
            'source'            => $subscriber->exists ? $subscriber->source : $source,
            'locale'            => $locale,
            'status'            => 'active',
            'ip_address'        => $request->ip(),
            'unsubscribed_at'   => null,
            'unsubscribe_token' => $subscriber->unsubscribe_token
                ?? NewsletterSubscriber::generateUnsubscribeToken(),
        ])->save();

        $message = $wasUnsubscribed
            ? __('customer_api.newsletter.resubscribed')
            : __('customer_api.newsletter.subscribed');

        return ApiResponse::success(null, $message);
    }

    /**
     * GET /v1/{country}/newsletter/unsubscribe?token={token}
     *
     * Unsubscribe via email link (GET so it works from email clients).
     */
    public function unsubscribe(Request $request, $country): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $request->input('token'))
            ->first();

        if (! $subscriber) {
            return ApiResponse::error(__('customer_api.newsletter.invalid_token'), [], 404);
        }

        if ($subscriber->status === 'active') {
            $subscriber->update([
                'status'          => 'unsubscribed',
                'unsubscribed_at' => now(),
            ]);
        }

        return ApiResponse::success(null, __('customer_api.newsletter.unsubscribed'));
    }
}

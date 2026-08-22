<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Customer\NotificationPreferencesResource;
use App\Http\Resources\Api\Customer\NotificationResource;
use App\Http\Responses\ApiResponse;
use App\Models\DeviceToken;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Notification data contract.
 *
 * Every notification stored in `notifications.data` MUST have these top-level keys:
 * {
 *   "title_en": string,
 *   "title_ar": string,
 *   "body_en": string,
 *   "body_ar": string,
 *   "action_type": "order"|"product"|"return"|"dispute"|"wallet"|"general",
 *   "action_id": string|null,
 *   "icon": "order"|"wallet"|"bell"|"gift"|"shield"
 * }
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = auth('customer')->user();

        $paginator = Notification::query()
            ->where('notifiable_type', $customer::class)
            ->where('notifiable_id', $customer->getKey())
            ->where('channel', 'database')
            ->orderByDesc('created_at')
            ->paginate(20);

        $unreadCount = Notification::query()
            ->where('notifiable_type', $customer::class)
            ->where('notifiable_id', $customer->getKey())
            ->where('channel', 'database')
            ->whereNull('read_at')
            ->count();

        return ApiResponse::paginated($paginator, NotificationResource::class, [
            'unread_count' => $unreadCount,
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        $customer = auth('customer')->user();

        $count = Notification::query()
            ->where('notifiable_type', $customer::class)
            ->where('notifiable_id', $customer->getKey())
            ->where('channel', 'database')
            ->whereNull('read_at')
            ->count();

        return ApiResponse::success(['unread_count' => $count]);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $customer = auth('customer')->user();

        $notification = Notification::query()
            ->where('id', $id)
            ->where('notifiable_type', $customer::class)
            ->where('notifiable_id', $customer->getKey())
            ->where('channel', 'database')
            ->first();

        if (!$notification) {
            return ApiResponse::error(__('customer_api.notification.not_found'), [], 404);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return ApiResponse::success(new NotificationResource($notification));
    }

    public function markAllAsRead(): JsonResponse
    {
        $customer = auth('customer')->user();

        Notification::query()
            ->where('notifiable_type', $customer::class)
            ->where('notifiable_id', $customer->getKey())
            ->where('channel', 'database')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success(null, __('customer_api.notification.all_marked_read'));
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string', 'in:ios,android'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('customer_api.validation_failed'), $validator->errors()->toArray());
        }

        $customer = auth('customer')->user();
        $data = $validator->validated();

        DeviceToken::updateOrCreate(
            [
                'tokenable_type' => $customer::class,
                'tokenable_id' => $customer->getKey(),
                'token' => $data['token'],
            ],
            [
                'platform' => $data['platform'],
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return ApiResponse::success(null, __('customer_api.notification.device_registered'));
    }

    public function removeDevice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('customer_api.validation_failed'), $validator->errors()->toArray());
        }

        $customer = auth('customer')->user();

        DeviceToken::query()
            ->where('tokenable_type', $customer::class)
            ->where('tokenable_id', $customer->getKey())
            ->where('token', $validator->validated()['token'])
            ->update(['is_active' => false]);

        return ApiResponse::success(null, __('customer_api.notification.device_removed'));
    }

    public function preferences(): JsonResponse
    {
        $customer = auth('customer')->user();

        return ApiResponse::success((new NotificationPreferencesResource($customer))->toArray(request()));
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'locale' => ['sometimes', 'string', Rule::in(config('app.available_locales', ['ar', 'en']))],
            'marketing_preferences' => ['sometimes', 'array'],
            'marketing_preferences.email' => ['sometimes', 'boolean'],
            'marketing_preferences.sms' => ['sometimes', 'boolean'],
            'marketing_preferences.whatsapp' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('customer_api.validation_failed'), $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $customer = auth('customer')->user();

        $update = [];
        if (array_key_exists('locale', $data)) {
            $update['locale'] = $data['locale'];
        }
        $prefs = $data['marketing_preferences'] ?? [];
        if (array_key_exists('email', $prefs)) {
            $update['marketing_email_enabled'] = $prefs['email'];
        }
        if (array_key_exists('sms', $prefs)) {
            $update['marketing_sms_enabled'] = $prefs['sms'];
        }
        if (array_key_exists('whatsapp', $prefs)) {
            $update['marketing_whatsapp_enabled'] = $prefs['whatsapp'];
        }

        $customer->update($update);

        return ApiResponse::success(
            (new NotificationPreferencesResource($customer))->toArray(request()),
            __('customer_api.notification.preferences_updated'),
        );
    }
}

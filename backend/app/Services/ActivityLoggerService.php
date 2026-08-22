<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;

/**
 * ActivityLoggerService
 *
 * Thin wrapper around the `Activity` model that:
 *   - generates a consistent audit-log entry
 *   - auto-captures ip_address + user_agent from the request
 *   - pre-resolves a human display name for the subject and stores it
 *     in properties.subject_display_name to avoid N+1 queries on the
 *     activity log index page.
 *
 * Drop-in friendly with spatie/laravel-activitylog: when the package is
 * later installed, the same `activity_log` table is used. You can switch
 * to LogsActivity trait on individual models without code changes here.
 *
 * Usage:
 *   app(ActivityLoggerService::class)->log(
 *       description: 'Product status changed to ' . $status,
 *       subject:     $product,
 *       causer:      auth('admin')->user(),
 *       properties:  ['reason' => $request->reason, 'old_status' => $old],
 *       logName:     'catalog',
 *       event:       'updated',
 *   );
 */
class ActivityLoggerService
{
    /**
     * Map of subject class => callable($model): ?string that returns
     * a short display name for that model. Add more as needed.
     */
    public const SUBJECT_RESOLVERS = [
        \App\Models\Product::class => 'name_en',
        \App\Models\Order::class => 'order_number',
        \App\Models\Vendor::class => 'store_name',
        \App\Models\Payout::class => 'payout_number',
        \App\Models\VendorListing::class => null, // resolved inline
        \App\Models\Customer::class => 'name',
        \App\Models\Admin::class => 'name',
        \App\Models\VendorAdmin::class => 'name',
    ];

    public function log(
        string $description,
        ?Model $subject = null,
        ?Model $causer = null,
        array $properties = [],
        string $logName = 'default',
        string $event = ''
    ): Activity {
        // Resolve subject display name once (avoids N+1 on index page)
        if ($subject && !isset($properties['subject_display_name'])) {
            $name = $this->resolveSubjectDisplayName($subject);
            if ($name !== null) {
                $properties['subject_display_name'] = $name;
            }
        }

        $request = request();

        return Activity::create([
            'log_name' => $logName,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'causer_type' => $causer ? get_class($causer) : null,
            'causer_id' => $causer?->getKey(),
            'event' => $event !== '' ? $event : null,
            'properties' => $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * Resolve a short, human readable display name for a model instance.
     */
    public function resolveSubjectDisplayName(Model $subject): ?string
    {
        $resolver = self::SUBJECT_RESOLVERS[get_class($subject)] ?? null;

        if (is_string($resolver) && isset($subject->{$resolver})) {
            return (string) $subject->{$resolver};
        }

        // Fallbacks
        foreach (['name_en', 'name', 'title', 'store_name'] as $col) {
            if (isset($subject->{$col})) {
                return (string) $subject->{$col};
            }
        }

        return null;
    }

    /**
     * Resolve a display name for a previously-logged entry (after the fact)
     * by querying the subject table. Used by the controller when an older
     * log row does not have properties.subject_display_name pre-cached.
     */
    public function resolveByTypeAndId(?string $subjectType, ?string $subjectId): ?string
    {
        if (!$subjectType || !$subjectId || !class_exists($subjectType)) {
            return null;
        }

        $resolver = self::SUBJECT_RESOLVERS[$subjectType] ?? null;
        if ($resolver === null && !array_key_exists($subjectType, self::SUBJECT_RESOLVERS)) {
            return null;
        }

        /** @var Model $instance */
        $instance = (new $subjectType)->newQuery()->find($subjectId);
        return $instance ? $this->resolveSubjectDisplayName($instance) : null;
    }
}

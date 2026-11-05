<?php

namespace App\Services\PortalAnalytics;

use App\Enums\RegistrationFlow;
use App\Enums\RegistrationStepEvent;
use App\Http\Middleware\TrackPortalVisit;
use App\Models\PortalPageView;
use App\Models\PortalVisitor;
use App\Models\RegistrationProgress;
use App\Models\TravelAgency;
use App\Models\Vendor;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collects what happened during a portal request and writes it after the response has been
 * sent ({@see TrackPortalVisit::terminate()}), so tracking never slows
 * a page down. Every public method only buffers; {@see self::flush()} swallows and logs any
 * failure, so analytics can never break the portal.
 *
 * Funnel rules (the wizard is strictly sequential):
 *  - reaching step N implies steps 1..N were viewed and 1..N-1 completed;
 *  - completing step N implies step N was reached and the next step is now viewed;
 *  - viewed / completed events are unique per progress+step+event, repeats are ignored;
 *  - validation_failed repeats only increase `occurrences`.
 */
class PortalTracker
{
    /** @var array{visitor_id: string, country_code: ?string, ip_hash: ?string, device_type: string, locale: ?string, referrer_host: ?string, utm: array<string, ?string>}|null */
    private ?array $visit = null;

    /** @var array{path: string, route_name: ?string}|null */
    private ?array $pageView = null;

    /** @var list<array{op: string, flow: RegistrationFlow, step?: int, subject?: Vendor|TravelAgency}> */
    private array $funnelOps = [];

    /**
     * @param  array{visitor_id: string, country_code: ?string, ip_hash: ?string, device_type: string, locale: ?string, referrer_host: ?string, utm: array<string, ?string>}  $visit
     */
    public function begin(array $visit): void
    {
        $this->visit = $visit;
        $this->pageView = null;
        $this->funnelOps = [];
    }

    public function isActive(): bool
    {
        return $this->visit !== null;
    }

    public function recordPageView(string $path, ?string $routeName): void
    {
        if ($this->visit !== null) {
            $this->pageView = ['path' => $path, 'route_name' => $routeName];
        }
    }

    /** The visitor is looking at $step (e.g. the wizard page was opened or resumed). */
    public function stepReached(RegistrationFlow $flow, int $step): void
    {
        $this->queue(['op' => 'reached', 'flow' => $flow, 'step' => $step]);
    }

    /** The visitor submitted $step successfully. */
    public function stepCompleted(RegistrationFlow $flow, int $step): void
    {
        $this->queue(['op' => 'completed', 'flow' => $flow, 'step' => $step]);
    }

    /** The visitor submitted $step but validation rejected it. */
    public function stepFailed(RegistrationFlow $flow, int $step): void
    {
        $this->queue(['op' => 'failed', 'flow' => $flow, 'step' => $step]);
    }

    /** The application was submitted and the account was created. */
    public function registrationCompleted(RegistrationFlow $flow, Vendor|TravelAgency $subject): void
    {
        $this->queue(['op' => 'registered', 'flow' => $flow, 'subject' => $subject]);
    }

    /**
     * @param  array{op: string, flow: RegistrationFlow, step?: int, subject?: Vendor|TravelAgency}  $operation
     */
    private function queue(array $operation): void
    {
        if ($this->visit === null) {
            return;
        }

        if (isset($operation['step']) && ($operation['step'] < 1 || $operation['step'] > $operation['flow']->lastStep())) {
            return;
        }

        $this->funnelOps[] = $operation;
    }

    /**
     * Write everything buffered during this request. Never throws.
     */
    public function flush(): void
    {
        $visit = $this->visit;
        $pageView = $this->pageView;
        $operations = $this->funnelOps;

        $this->visit = null;
        $this->pageView = null;
        $this->funnelOps = [];

        if ($visit === null || ($pageView === null && $operations === [])) {
            return;
        }

        try {
            $now = now();
            [$visitor, $isNewVisit] = $this->touchVisitor($visit, $now);

            if ($pageView !== null) {
                $this->writePageView($visitor, $visit, $pageView, $isNewVisit, $now);
            }

            foreach ($operations as $operation) {
                $this->applyFunnelOperation($visitor, $visit, $operation, $now);
            }
        } catch (Throwable $e) {
            Log::warning('Portal analytics tracking failed', [
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ── Visitor + page view ──────────────────────────────────────────────

    /**
     * Find or create the visitor and refresh last_seen_at.
     *
     * @param  array<string, mixed>  $visit
     * @return array{0: PortalVisitor, 1: bool} the visitor and whether this request starts a new visit
     */
    private function touchVisitor(array $visit, CarbonInterface $now): array
    {
        $gap = max(1, (int) config('portal_analytics.visit_gap_minutes', 30));
        $visitor = PortalVisitor::find($visit['visitor_id']);

        if ($visitor === null) {
            try {
                $visitor = PortalVisitor::create([
                    'id' => $visit['visitor_id'],
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'country_code' => $visit['country_code'],
                    'device_type' => $visit['device_type'],
                    'locale' => $visit['locale'],
                    'referrer_host' => $visit['referrer_host'],
                    'utm_source' => $visit['utm']['source'] ?? null,
                    'utm_medium' => $visit['utm']['medium'] ?? null,
                    'utm_campaign' => $visit['utm']['campaign'] ?? null,
                    'ip_hash' => $visit['ip_hash'],
                ]);

                return [$visitor, true];
            } catch (UniqueConstraintViolationException) {
                $visitor = PortalVisitor::findOrFail($visit['visitor_id']);
            }
        }

        $isNewVisit = $visitor->last_seen_at->lt($now->copy()->subMinutes($gap));

        if ($isNewVisit || $visitor->last_seen_at->lt($now->copy()->subMinute())) {
            $visitor->forceFill(['last_seen_at' => $now, 'ip_hash' => $visit['ip_hash'] ?? $visitor->ip_hash])->save();
        }

        return [$visitor, $isNewVisit];
    }

    /**
     * @param  array<string, mixed>  $visit
     * @param  array{path: string, route_name: ?string}  $pageView
     */
    private function writePageView(PortalVisitor $visitor, array $visit, array $pageView, bool $isNewVisit, CarbonInterface $now): void
    {
        // Requesting the page the visitor is already on (a refresh) is not a new page view.
        $fingerprint = sha1($pageView['path']);
        $cacheKey = 'portal-analytics:last-page:'.$visitor->id;
        $ttl = now()->addMinutes(max(1, (int) config('portal_analytics.visit_gap_minutes', 30)));

        if (! $isNewVisit && Cache::get($cacheKey) === $fingerprint) {
            return;
        }

        Cache::put($cacheKey, $fingerprint, $ttl);

        PortalPageView::create([
            'visitor_id' => $visitor->id,
            'path' => $pageView['path'],
            'route_name' => $pageView['route_name'],
            'country_code' => $visit['country_code'],
            'is_new_visit' => $isNewVisit,
            'viewed_at' => $now,
        ]);
    }

    // ── Funnel ────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $visit
     * @param  array{op: string, flow: RegistrationFlow, step?: int, subject?: Vendor|TravelAgency}  $operation
     */
    private function applyFunnelOperation(PortalVisitor $visitor, array $visit, array $operation, CarbonInterface $now): void
    {
        $flow = $operation['flow'];
        $progress = $this->progressFor($visitor, $flow, $visit['country_code'], $now);

        if ($progress->completed_at !== null) {
            return; // the application is already submitted; later activity is not funnel data
        }

        $step = $operation['step'] ?? $flow->lastStep();
        $events = [];
        $attributes = ['last_activity_at' => $now];

        switch ($operation['op']) {
            case 'reached':
                $events = $this->reachedEvents($step);
                $attributes['highest_step_reached'] = max($progress->highest_step_reached, $step);
                $attributes['last_step_completed'] = max($progress->last_step_completed, $step - 1);
                break;

            case 'completed':
                $next = $step < $flow->lastStep() ? $step + 1 : null;
                $events = [
                    ...$this->reachedEvents($step),
                    [$step, RegistrationStepEvent::Completed],
                ];
                if ($next !== null) {
                    $events[] = [$next, RegistrationStepEvent::Viewed];
                }
                $attributes['highest_step_reached'] = max($progress->highest_step_reached, $next ?? $step);
                $attributes['last_step_completed'] = max($progress->last_step_completed, $step);
                $attributes['first_submitted_at'] = $progress->first_submitted_at ?? $now;
                break;

            case 'failed':
                $events = $this->reachedEvents($step);
                $attributes['highest_step_reached'] = max($progress->highest_step_reached, $step);
                $attributes['last_step_completed'] = max($progress->last_step_completed, $step - 1);
                $attributes['first_submitted_at'] = $progress->first_submitted_at ?? $now;
                break;

            case 'registered':
                $last = $flow->lastStep();
                $events = [
                    ...$this->reachedEvents($last),
                    [$last, RegistrationStepEvent::Completed],
                ];
                $attributes += [
                    'highest_step_reached' => $last,
                    'last_step_completed' => $last,
                    'first_submitted_at' => $progress->first_submitted_at ?? $now,
                    'completed_at' => $now,
                ];
                $subject = $operation['subject'] ?? null;
                if ($subject instanceof Vendor) {
                    $attributes['vendor_id'] = $subject->id;
                } elseif ($subject instanceof TravelAgency) {
                    $attributes['travel_agency_id'] = $subject->id;
                }
                break;
        }

        DB::transaction(function () use ($progress, $operation, $step, $events, $attributes, $now): void {
            $this->insertEvents($progress, $events, $now);

            if ($operation['op'] === 'failed') {
                $this->recordFailure($progress, $step, $now);
            }

            $progress->forceFill($attributes)->save();
        });
    }

    /**
     * Reaching step N means 1..N were viewed and 1..N-1 completed.
     *
     * @return list<array{0: int, 1: RegistrationStepEvent}>
     */
    private function reachedEvents(int $step): array
    {
        $events = [];

        for ($i = 1; $i <= $step; $i++) {
            $events[] = [$i, RegistrationStepEvent::Viewed];
            if ($i < $step) {
                $events[] = [$i, RegistrationStepEvent::Completed];
            }
        }

        return $events;
    }

    private function progressFor(PortalVisitor $visitor, RegistrationFlow $flow, ?string $countryCode, CarbonInterface $now): RegistrationProgress
    {
        $existing = RegistrationProgress::where('visitor_id', $visitor->id)->where('flow', $flow->value)->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return RegistrationProgress::create([
                'visitor_id' => $visitor->id,
                'flow' => $flow,
                'country_code' => $countryCode,
                'highest_step_reached' => 0,
                'last_step_completed' => 0,
                'started_at' => $now,
                'last_activity_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException) {
            return RegistrationProgress::where('visitor_id', $visitor->id)->where('flow', $flow->value)->firstOrFail();
        }
    }

    /**
     * @param  list<array{0: int, 1: RegistrationStepEvent}>  $events
     */
    private function insertEvents(RegistrationProgress $progress, array $events, CarbonInterface $now): void
    {
        if ($events === []) {
            return;
        }

        // Unique key (progress, step, event): duplicates are ignored by the database itself.
        DB::table('registration_step_events')->insertOrIgnore(array_map(fn (array $event): array => [
            'registration_progress_id' => $progress->id,
            'step' => $event[0],
            'event' => $event[1]->value,
            'occurrences' => 1,
            'occurred_at' => $now,
        ], $events));
    }

    private function recordFailure(RegistrationProgress $progress, int $step, CarbonInterface $now): void
    {
        $inserted = DB::table('registration_step_events')->insertOrIgnore([
            'registration_progress_id' => $progress->id,
            'step' => $step,
            'event' => RegistrationStepEvent::ValidationFailed->value,
            'occurrences' => 1,
            'occurred_at' => $now,
        ]);

        if ($inserted === 0) {
            DB::table('registration_step_events')
                ->where('registration_progress_id', $progress->id)
                ->where('step', $step)
                ->where('event', RegistrationStepEvent::ValidationFailed->value)
                ->increment('occurrences');
        }
    }
}

<?php

namespace App\Services\PortalAnalytics;

use App\Enums\RegistrationFlow;
use Carbon\CarbonImmutable;

/**
 * Validated filter set for the portal analytics report. The period is inclusive
 * (start of `from` day to end of `to` day); the comparison period is the immediately preceding
 * period of exactly the same length.
 */
final readonly class PortalAnalyticsFilters
{
    public const GRANULARITIES = ['day', 'week', 'month'];

    /** Daily charts beyond this many days are rolled up to weeks to stay readable. */
    private const MAX_DAILY_POINTS = 92;

    public CarbonImmutable $from;

    public CarbonImmutable $to;

    public string $granularity;

    public function __construct(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $granularity,
        public ?string $countryCode,
        public RegistrationFlow $flow,
        public bool $compare,
    ) {
        $this->from = $from->startOfDay();
        $this->to = $to->endOfDay();

        $days = $this->days();
        $granularity = in_array($granularity, self::GRANULARITIES, true) ? $granularity : 'day';
        $this->granularity = ($granularity === 'day' && $days > self::MAX_DAILY_POINTS) ? 'week' : $granularity;
    }

    public static function default(): self
    {
        return new self(
            CarbonImmutable::now()->subDays(29),
            CarbonImmutable::now(),
            'day',
            null,
            RegistrationFlow::Vendor,
            true,
        );
    }

    /** Number of calendar days covered by the period. */
    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to->startOfDay()) + 1;
    }

    public function previousFrom(): CarbonImmutable
    {
        return $this->from->subDays($this->days());
    }

    public function previousTo(): CarbonImmutable
    {
        return $this->from->subSecond();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date_from' => $this->from->toDateString(),
            'date_to' => $this->to->toDateString(),
            'granularity' => $this->granularity,
            'country' => $this->countryCode,
            'flow' => $this->flow->value,
            'compare' => $this->compare,
        ];
    }
}

<?php

namespace App\Enums;

/**
 * The portal registration wizards that are tracked in the funnel, with their real steps.
 *
 * This is the single place that names the steps: the tracker, the beacon validation and the
 * admin dashboard (labels under `admin.portal_analytics.steps.<flow>.<key>`) all read it.
 *
 *  - vendor: /vendors/register. Steps 1-3 are saved to the session by `storeStep`, step 4
 *    (documents) is validated in the browser only, step 5 (review & sign) posts `complete`.
 *  - travel_agency: /companies/travel-agency/register. Steps 1-2 are saved by `storeStep`,
 *    step 3 (review) posts `complete`.
 */
enum RegistrationFlow: string
{
    case Vendor = 'vendor';
    case TravelAgency = 'travel_agency';

    /**
     * @return array<int, string> step number => step key
     */
    public function steps(): array
    {
        return match ($this) {
            self::Vendor => [1 => 'account', 2 => 'store', 3 => 'contact', 4 => 'documents', 5 => 'review'],
            self::TravelAgency => [1 => 'account', 2 => 'agency', 3 => 'review'],
        };
    }

    public function lastStep(): int
    {
        return array_key_last($this->steps());
    }

    public function stepKey(int $step): ?string
    {
        return $this->steps()[$step] ?? null;
    }

    public function label(): string
    {
        return __('admin.portal_analytics.flows.'.$this->value);
    }

    public function stepLabel(int $step): string
    {
        return __('admin.portal_analytics.steps.'.$this->value.'.'.$this->stepKey($step));
    }

    /**
     * Steps the server learns about from a form post (saved into the session).
     *
     * @return list<int>
     */
    public function serverSavedSteps(): array
    {
        return match ($this) {
            self::Vendor => [1, 2, 3],
            self::TravelAgency => [1, 2],
        };
    }

    /**
     * Steps completed in the browser alone, reported through the tracking beacon.
     *
     * @return list<int>
     */
    public function clientReportedSteps(): array
    {
        return match ($this) {
            self::Vendor => [4],
            self::TravelAgency => [],
        };
    }

    /** Session key holding the in-progress wizard data. */
    public function sessionKey(): string
    {
        return match ($this) {
            self::Vendor => 'reg_data',
            self::TravelAgency => 'travel_reg_data',
        };
    }

    /**
     * True when the session already holds every server-saved step before $step, i.e. the
     * visitor genuinely got that far. Used so a forged beacon cannot invent progress.
     */
    public function sessionReached(int $step): bool
    {
        foreach ($this->serverSavedSteps() as $saved) {
            if ($saved < $step && empty(session($this->sessionKey().".step{$saved}"))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $flow): string => $flow->value, self::cases());
    }
}

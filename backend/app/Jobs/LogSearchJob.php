<?php

namespace App\Jobs;

use App\Models\SearchLog;
use App\Models\SearchSuggestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LogSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $query,
        public readonly string $countryId,
        public readonly int $resultsCount,
        public readonly array $filters,
        public readonly ?string $customerId,
        public readonly string $sessionId,
        public readonly string $language,
    ) {}

    public function handle(): void
    {
        SearchLog::create([
            'id'               => Str::uuid(),
            'customer_id'      => $this->customerId,
            'session_id'       => $this->sessionId ?: Str::random(26),
            'query'            => $this->query,
            'query_normalized' => Str::lower(trim($this->query)),
            'filters_json'     => json_encode($this->filters),
            'results_count'    => $this->resultsCount,
            'language'         => $this->language,
            'country_id'       => $this->countryId,
        ]);

        $normalized = Str::lower(trim($this->query));

        if (strlen($normalized) >= 2 && !is_numeric($normalized)) {
            SearchSuggestion::upsert(
                [[
                    'id'                 => (string) Str::uuid(),
                    'keyword'            => trim($this->query),
                    'keyword_normalized' => $normalized,
                    'country_id'         => $this->countryId,
                    'search_count'       => 1,
                    'is_pinned'          => false,
                    'is_blocked'         => false,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]],
                uniqueBy: ['keyword_normalized', 'country_id'],
                update: [
                    'search_count' => DB::raw('search_count + 1'),
                    'keyword'      => trim($this->query),
                    'updated_at'   => now(),
                ],
            );
        }
    }
}

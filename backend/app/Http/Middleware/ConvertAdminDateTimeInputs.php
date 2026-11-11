<?php

namespace App\Http\Middleware;

use App\Support\Timezones;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admins type every date-time in Oman time (config app.admin_timezone), but the database
 * stores UTC. This converts every posted wall-clock date-time ("Y-m-d H:i[:s]" or the
 * datetime-local "Y-m-dTH:i") from Oman to UTC before validation and controllers see it.
 *
 * Date-only values ("Y-m-d") are calendar days, not instants, and are left untouched;
 * filters turn them into Oman day ranges with admin_day_start()/admin_day_end().
 *
 * When validation fails, the original Oman values are restored into the flashed old input
 * so the re-rendered form shows what the admin typed (and is not shifted a second time).
 */
class ConvertAdminDateTimeInputs
{
    public function handle(Request $request, Closure $next): Response
    {
        $originals = [];

        $converted = $this->convert($request->request->all(), '', $originals);
        if ($originals !== []) {
            $request->request->replace($converted);
            if ($request->isJson()) {
                $request->json()->replace($converted);
            }
        }

        $queryOriginals = [];
        $convertedQuery = $this->convert($request->query->all(), '', $queryOriginals);
        if ($queryOriginals !== []) {
            $request->query->replace($convertedQuery);
        }

        $response = $next($request);

        if ($originals !== [] && $request->hasSession() && $request->session()->has('_old_input')) {
            $old = $request->session()->get('_old_input');
            foreach ($originals as $key => $value) {
                if (Arr::has($old, $key)) {
                    Arr::set($old, $key, $value);
                }
            }
            $request->session()->flash('_old_input', $old);
        }

        return $response;
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @param  array<string, string>  $originals  dot-path => original Oman value
     * @return array<array-key, mixed>
     */
    private function convert(array $input, string $prefix, array &$originals): array
    {
        foreach ($input as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $input[$key] = $this->convert($value, $path, $originals);

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $utc = Timezones::adminInputToUtc(trim($value));
            if ($utc !== null) {
                $originals[$path] = $value;
                $input[$key] = $utc->format('Y-m-d H:i:s');
            }
        }

        return $input;
    }
}

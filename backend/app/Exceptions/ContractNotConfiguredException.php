<?php

namespace App\Exceptions;

use App\Models\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * No contract is configured for the contracting party's country. Raised instead of silently using another
 * country's contract. Renders as a 422 validation error with the stable error code `contract_not_configured`.
 */
class ContractNotConfiguredException extends ValidationException
{
    public const CODE = 'contract_not_configured';

    public ?Country $country = null;

    public string $scope = 'platform';

    public static function forCountry(?Country $country, string $scope = 'platform'): static
    {
        $message = $country
            ? __('contracts.api.not_configured', ['country' => $country->name_ar && app()->getLocale() === 'ar' ? $country->name_ar : $country->name_en])
            : __('contracts.api.not_configured_no_country');

        $exception = static::withMessages(['contract' => [$message]]);
        $exception->country = $country;
        $exception->scope = $scope;

        return $exception;
    }

    public function errorCode(): string
    {
        return self::CODE;
    }

    /**
     * API clients get the stable code and the country next to the usual validation payload; web requests use
     * the default validation redirect.
     */
    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'code' => self::CODE,
            'errors' => $this->errors(),
            'data' => [
                'scope' => $this->scope,
                'country' => $this->country ? ['id' => $this->country->id, 'code' => $this->country->iso_code_2, 'name' => $this->country->name_en] : null,
            ],
        ], 422);
    }
}

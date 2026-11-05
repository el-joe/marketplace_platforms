<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The customer must accept the customer purchase contract of an advertisement before the inquiry / purchase action
 * can go ahead. Renders as a 422 with the stable error code `customer_contract_required` and the contract to review.
 */
class CustomerContractRequiredException extends ValidationException
{
    public const CODE = 'customer_contract_required';

    /** @var array<string, mixed> */
    public array $contract = [];

    /**
     * @param  array<string, mixed>  $contract  presented contract (see CustomerContractService::present)
     */
    public static function for(array $contract, ?string $message = null): static
    {
        $exception = static::withMessages(['contract' => [$message ?? __('contracts.customer.required')]]);
        $exception->contract = $contract;

        return $exception;
    }

    public function errorCode(): string
    {
        return self::CODE;
    }

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
            'data' => ['contract' => $this->contract],
        ], 422);
    }
}

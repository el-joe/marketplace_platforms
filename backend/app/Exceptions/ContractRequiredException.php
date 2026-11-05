<?php

namespace App\Exceptions;

use App\Models\ClassifiedContractTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The seller must sign an individual (product or category) contract before the action can go ahead.
 * Renders as a 422 with the stable error code `contract_required` and the pending contract.
 */
class ContractRequiredException extends ValidationException
{
    public const CODE = 'contract_required';

    /** @var array<string, mixed> */
    public array $contract = [];

    /**
     * @param  array<string, mixed>  $contract  presented contract (see CategoryContractService::present) plus scope / subject ids
     */
    public static function for(ClassifiedContractTemplate $template, array $contract, ?string $message = null): static
    {
        $exception = static::withMessages(['contract' => [$message ?? __('contracts.api.contract_required')]]);
        $exception->contract = $contract + ['template_id' => $template->id];

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

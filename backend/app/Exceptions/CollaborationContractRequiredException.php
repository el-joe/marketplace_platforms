<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The seller has to review and sign a seller-marketer collaboration contract before the collaboration is confirmed.
 * Renders as a 422 with the stable error code `collaboration_contract_required`.
 */
class CollaborationContractRequiredException extends ValidationException
{
    public const CODE = 'collaboration_contract_required';

    /** @var list<string> */
    public array $templateIds = [];

    /**
     * @param  list<string>  $templateIds  templates that are still unsigned
     * @param  list<string>  $names
     */
    public static function missing(array $templateIds, array $names, string $field = 'collaboration_contracts'): static
    {
        $exception = static::withMessages([$field => [__('contracts.marketer.signing_required', ['names' => implode(', ', $names)])]]);
        $exception->templateIds = $templateIds;

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
            'data' => ['template_ids' => $this->templateIds],
        ], 422);
    }
}

<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps the cached app-config payload (contexts + bottom nav) built in
 * AppConfigController::config(). The underlying resource is a plain array
 * (already fully computed/cached), not an Eloquent model.
 */
class AppConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'contexts'         => $this->resource['contexts'],
            'default_context'  => $this->resource['default_context'],
            'announcement_bar' => $this->resource['announcement_bar'] ?? null,
        ];
    }
}

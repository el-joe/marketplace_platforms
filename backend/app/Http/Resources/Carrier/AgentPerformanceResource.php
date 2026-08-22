<?php

namespace App\Http\Resources\Carrier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentPerformanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'agent_id'             => $this->resource['agent_id'],
            'period_days'          => $this->resource['period_days'],
            'total_deliveries'     => $this->resource['total_deliveries'],
            'rating_avg'           => $this->resource['rating_avg'],
            'on_time_rate'         => $this->resource['on_time_rate'],
            'failed_delivery_rate' => $this->resource['failed_delivery_rate'],
            'period_from'          => $this->resource['period_from'],
            'period_to'            => $this->resource['period_to'],
        ];
    }
}

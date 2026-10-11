<?php

namespace Tests\Feature;

use App\Services\MarketerConversionReversalService;
use App\Services\OrderCancellationService;
use App\Services\RefundService;
use Tests\TestCase;

class RefundCancellationServiceResolutionTest extends TestCase
{
    public function test_container_resolves_refund_cancellation_and_reversal_services(): void
    {
        $this->assertInstanceOf(MarketerConversionReversalService::class, app(MarketerConversionReversalService::class));
        $this->assertInstanceOf(RefundService::class, app(RefundService::class));
        $this->assertInstanceOf(OrderCancellationService::class, app(OrderCancellationService::class));
    }

    public function test_reversal_service_can_be_constructed_without_arguments(): void
    {
        $this->assertInstanceOf(MarketerConversionReversalService::class, new MarketerConversionReversalService);
    }
}

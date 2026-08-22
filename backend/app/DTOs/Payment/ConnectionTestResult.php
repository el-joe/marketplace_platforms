<?php

namespace App\DTOs\Payment;

readonly class ConnectionTestResult
{
    public function __construct(
        public bool   $success,
        public string $message,
        public array  $details = [],
    ) {}
}

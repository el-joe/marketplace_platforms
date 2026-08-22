<?php

namespace App\Services\Delivery;

use App\Models\DeliveryAssignment;

class OtpVerificationService
{
    private const MAX_ATTEMPTS = 3;

    /**
     * Verify an OTP against the assignment.
     *
     * Increments otp_attempts before comparing so every call counts,
     * even if the caller crashes afterward. Returns false and marks
     * the assignment locked once MAX_ATTEMPTS is reached.
     *
     * @throws \RuntimeException when already locked (caller should return 423)
     */
    public function verify(DeliveryAssignment $assignment, string $code): bool
    {
        if ($assignment->otp_attempts >= self::MAX_ATTEMPTS) {
            throw new \RuntimeException('otp_locked');
        }

        // Increment before comparing — every attempt burns a slot.
        $assignment->increment('otp_attempts');
        $assignment->refresh();

        $expected = (string) ($assignment->delivery_otp ?? $assignment->shipment?->delivery_otp ?? '');

        // Constant-time comparison prevents timing-based enumeration.
        $match = hash_equals($expected, $code);

        if ($assignment->otp_attempts >= self::MAX_ATTEMPTS && ! $match) {
            // Reached the cap — flag so callers know to return 423.
            throw new \RuntimeException('otp_locked');
        }

        return $match;
    }

    public function isLocked(DeliveryAssignment $assignment): bool
    {
        return $assignment->otp_attempts >= self::MAX_ATTEMPTS && ! $assignment->otp_verified;
    }

    public function remainingAttempts(DeliveryAssignment $assignment): int
    {
        return max(0, self::MAX_ATTEMPTS - $assignment->otp_attempts);
    }
}

<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\CustomerOtpToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendVerificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Customer $customer) {}

    public function handle(): void
    {
        // Invalidate any existing unused email verification tokens
        $this->customer->otpTokens()
            ->where('type', 'email_verification')
            ->whereNull('used_at')
            ->delete();

        $token = CustomerOtpToken::create([
            'customer_id' => $this->customer->id,
            'token' => Str::random(64),
            'type' => 'email_verification',
            'expires_at' => now()->addHours(24),
        ]);

        // TODO: swap for a proper Mailable once email templates are designed
        Mail::raw(
            "Verify your email: your token is {$token->token}",
            fn ($m) => $m->to($this->customer->email)->subject('Verify your email address')
        );
    }
}

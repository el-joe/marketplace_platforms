<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications to customer mobile devices via FCM HTTP v1.
 * Mirrors App\Services\Vendor\VendorFCMService's implementation (no
 * kreait/laravel-firebase dependency; Google OAuth2 JWT bearer flow).
 */
class CustomerFCMService
{
    private const TOKEN_CACHE_KEY = 'fcm:google_access_token';

    public function sendToCustomer(string $customerId, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::where('tokenable_type', Customer::class)
            ->where('tokenable_id', $customerId)
            ->where('is_active', 1)
            ->pluck('token');

        foreach ($tokens as $token) {
            $this->sendToToken($token, $title, $body, $data);
        }
    }

    /**
     * Send to a single FCM token. Never throws — a push failure must never
     * block the caller's main flow (e.g. bulk notification dispatch).
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): void
    {
        if ($token === '') {
            return;
        }

        try {
            $projectId   = config('services.firebase.project_id');
            $accessToken = $this->getGoogleAccessToken();

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token'        => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data'         => array_map('strval', $data),
                        'android'      => ['priority' => 'high'],
                        'apns'         => ['headers' => ['apns-priority' => '10']],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('FCM send failed', [
                    'token'  => substr($token, 0, 10) . '...',
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('FCM send failed', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getGoogleAccessToken(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, 3300, function (): string {
            $path = config('services.firebase.credentials');

            if (!is_string($path) || !is_file($path)) {
                throw new \RuntimeException("Firebase credentials file not found at [{$path}].");
            }

            $credentials = json_decode(file_get_contents($path), true);

            if (!isset($credentials['client_email'], $credentials['private_key'])) {
                throw new \RuntimeException('Firebase credentials file is missing client_email/private_key.');
            }

            $now    = time();
            $header = ['alg' => 'RS256', 'typ' => 'JWT'];
            $claims = [
                'iss'   => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ];

            $segments = [
                $this->base64UrlEncode(json_encode($header)),
                $this->base64UrlEncode(json_encode($claims)),
            ];

            openssl_sign(
                implode('.', $segments),
                $signature,
                $credentials['private_key'],
                OPENSSL_ALGO_SHA256
            );

            $segments[] = $this->base64UrlEncode($signature);
            $assertion  = implode('.', $segments);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ])->throw();

            return $response->json('access_token');
        });
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

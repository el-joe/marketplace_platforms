<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Mail\MailDiagnostics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings > Integrations "Send test email": a real, synchronous send that reports the real result.
 */
class MailTestController extends Controller
{
    public function __construct(private readonly MailDiagnostics $diagnostics) {}

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to' => ['nullable', 'email', 'max:255'],
        ]);

        $recipient = $validated['to'] ?? auth('admin')->user()->email;

        $result = $this->diagnostics->sendTest($recipient);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'mailer' => $result['mailer'],
        ], $result['ok'] ? 200 : 502);
    }
}

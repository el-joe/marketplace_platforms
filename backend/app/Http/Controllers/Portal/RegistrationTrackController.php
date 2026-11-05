<?php

namespace App\Http\Controllers\Portal;

use App\Enums\RegistrationFlow;
use App\Http\Controllers\Controller;
use App\Services\PortalAnalytics\PortalTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * POST /vendors/register/track — funnel beacon for the steps that only the browser performs
 * (vendor wizard step 4, documents, which is validated client-side and moves to the review step
 * without a request).
 *
 * The request carries no identifiers: the visitor comes from the anonymous cookie and the
 * country from the server-side portal country. The flow and step are validated strictly, only
 * steps the flow declares as browser-reported are accepted, and the session must prove the
 * visitor really saved the previous steps.
 */
class RegistrationTrackController extends Controller
{
    public function __invoke(Request $request, PortalTracker $tracker): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'flow' => ['required', 'string', Rule::in(RegistrationFlow::values())],
            'step' => ['required', 'integer'],
            'event' => ['required', 'string', Rule::in(['completed'])],
        ]);

        $flow = RegistrationFlow::tryFrom((string) $request->input('flow'));

        if ($validator->fails() || $flow === null || ! in_array((int) $request->input('step'), $flow->clientReportedSteps(), true)) {
            return response()->json(['success' => false], 422);
        }

        $step = (int) $request->input('step');

        if (! $flow->sessionReached($step)) {
            return response()->json(['success' => false], 422);
        }

        $tracker->stepCompleted($flow, $step);

        return response()->json(['success' => true]);
    }
}

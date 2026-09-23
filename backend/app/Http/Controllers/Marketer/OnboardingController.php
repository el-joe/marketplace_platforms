<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Services\Marketer\MarketerOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function __construct(private MarketerOnboardingService $service) {}

    public function index(): RedirectResponse|View
    {
        $marketer = $this->marketer();
        if (! $marketer->needsOnboarding()) {
            return redirect()->route('marketer.dashboard');
        }
        if (! $marketer->marketerJobs()->exists()) {
            return $this->jobTypeView();
        }

        return redirect()->route('marketer.onboarding.profile');
    }

    public function jobTypeView(): View
    {
        return view('marketer.onboarding.job-type', [
            'jobKeys' => $this->service->availableJobKeys(),
            'selected' => $this->marketer()->marketerJobs()->pluck('key')->all(),
        ]);
    }

    public function saveJobType(Request $request): RedirectResponse
    {
        if (! $this->marketer()->needsOnboarding()) {
            return redirect()->route('marketer.dashboard');
        }

        $data = $request->validate([
            'job_keys' => ['required', 'array', 'min:1'],
            'job_keys.*' => ['required', 'string', Rule::in([...$this->service->availableJobKeys(), 'broker'])],
        ]);
        $this->service->setJobTypes($this->marketer(), $data['job_keys']);

        return redirect()->route('marketer.onboarding.profile');
    }

    public function profileForm(): RedirectResponse|View
    {
        $marketer = $this->marketer();
        if (! $marketer->needsOnboarding()) {
            return redirect()->route('marketer.dashboard');
        }
        if (! $marketer->marketerJobs()->exists()) {
            return redirect()->route('marketer.onboarding.index');
        }

        return view('marketer.onboarding.profile', [
            'marketer' => $marketer,
            'profile' => $marketer->marketerProfile()->first(),
            'isInfluencer' => $this->service->hasJob($marketer, 'influencer'),
            'isBroker' => $this->service->hasJob($marketer, 'affiliate'),
            'platforms' => MarketerOnboardingService::SOCIAL_PLATFORMS,
            'categories' => Category::orderBy('name_ar')->get(),
            'cities' => City::orderBy('name_ar')->get(),
        ]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $marketer = $this->marketer();
        if (! $marketer->needsOnboarding()) {
            return redirect()->route('marketer.dashboard');
        }
        if (! $marketer->marketerJobs()->exists()) {
            return redirect()->route('marketer.onboarding.index');
        }

        $data = $request->validate($this->service->profileRules($marketer));
        $this->service->saveProfile($marketer, $data, $request->file('avatar'), $request->file('banner'));
        $this->service->storeDocumentsFromRequest($marketer, $request->file('cv'), $request->file('certifications', []));

        $missing = $this->service->complete($marketer);
        if ($missing) {
            return back()->withErrors(['onboarding' => __('marketer.onboarding_incomplete').' ('.implode(', ', $missing).')']);
        }

        return redirect()->route('marketer.dashboard')->with('status', __('marketer.onboarding_done'));
    }

    private function marketer()
    {
        return Auth::guard('marketer')->user()->marketer;
    }
}

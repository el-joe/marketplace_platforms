<?php

namespace App\Http\Controllers\Marketer;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\MarketerProfile;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    private function marketer(): \App\Models\Marketer
    {
        return Auth::guard('marketer')->user()->marketer;
    }

    public function show(): View
    {
        $marketer = $this->marketer();
        $profile  = $marketer->marketerProfile()->firstOrCreate(['marketer_id' => $marketer->id]);

        if (! $profile->profile_slug) {
            $profile->update([
                'profile_slug' => Str::slug($marketer->name) . '-' . Str::lower(Str::random(6)),
            ]);
        }

        $categories = \App\Models\Category::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);
        $cities     = \App\Models\City::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar', 'name_en']);

        return view('marketer.profile', compact('marketer', 'profile', 'categories', 'cities'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'bio_ar'          => 'nullable|string|max:1000',
            'bio_en'          => 'nullable|string|max:1000',
            'specialty_ar'    => 'nullable|string|max:150',
            'specialty_en'    => 'nullable|string|max:150',
            'video_url'       => 'nullable|url|max:500',
            'social_links'    => 'nullable|array',
            'social_links.*'  => 'nullable|url|max:500',
            'contact_details' => 'nullable|array',
            'contact_details.*' => 'nullable|string|max:255',
            'whatsapp_for_campaigns' => 'nullable|string|max:30',
            'avatar'          => 'nullable|image|max:5120',
            'banner'          => 'nullable|image|max:5120',
            'broker_category_id'       => 'nullable|uuid|exists:categories,id',
            'broker_city_id'           => 'nullable|uuid|exists:cities,id',
            'broker_serves_all_cities' => 'nullable|boolean',
        ]);

        $marketer = $this->marketer();
        $profile  = $marketer->marketerProfile()->firstOrCreate(['marketer_id' => $marketer->id]);

        // Update WhatsApp on the marketer row itself
        $marketer->update([
            'whatsapp_for_campaigns' => $request->whatsapp_for_campaigns,
        ]);

        if ($request->hasFile('avatar')) {
            $profile->avatar_file_id = $this->storeProfileImage($request, 'avatar', 'marketer-avatars', $profile)->id;
        }

        if ($request->hasFile('banner')) {
            $profile->banner_file_id = $this->storeProfileImage($request, 'banner', 'marketer-banners', $profile)->id;
        }

        $profile->fill($request->only(['bio_ar', 'bio_en', 'specialty_ar', 'specialty_en', 'video_url', 'social_links', 'contact_details']));

        // Broker specialization: affiliate marketers only.
        if ($marketer->isAffiliate()) {
            $serveAll = $request->boolean('broker_serves_all_cities');
            $profile->fill([
                'broker_category_id'       => $request->input('broker_category_id') ?: null,
                'broker_serves_all_cities' => $serveAll,
                'broker_city_id'           => $serveAll ? null : ($request->input('broker_city_id') ?: null),
            ]);
        }
        $profile->save();

        if (! $profile->qr_code_path) {
            $this->generateQrCode($profile);
        }

        // Public profile cache is busted automatically by MarketerProfile's
        // model events (see MarketerProfile::booted()) on the $profile->save() above.

        return back()->with('success', 'تم حفظ البروفايل.');
    }

    /**
     * Self-edit for ad_price. Only marketers granted can_self_edit_ad_price
     * by admin may use this — guarded even if the endpoint is hit directly.
     */
    public function updateAdPrice(Request $request): RedirectResponse
    {
        $marketer = $this->marketer();
        $profile  = $marketer->marketerProfile()->firstOrCreate(['marketer_id' => $marketer->id]);

        abort_unless($profile->can_self_edit_ad_price, 403, 'غير مسموح لك بتعديل سعر الإعلان.');

        $request->validate([
            'ad_price'          => ['required', 'integer', 'min:0'],
            'ad_price_currency' => ['nullable', 'string', 'size:3'],
        ]);

        $profile->update([
            'ad_price'          => $request->integer('ad_price'),
            'ad_price_currency' => $request->input('ad_price_currency', $profile->ad_price_currency),
        ]);

        return back()->with('success', 'تم تحديث سعر الإعلان.');
    }

    private function storeProfileImage(Request $request, string $field, string $directory, MarketerProfile $profile): File
    {
        $upload = $request->file($field);
        $path   = $upload->store($directory . '/' . $profile->marketer_id, 'public');

        return File::create([
            'key'          => Str::uuid(),
            'path'         => $path,
            'storage_type' => 'public',
            'file_type'    => 'image',
            'mime_type'    => $upload->getClientMimeType(),
            'extension'    => $upload->getClientOriginalExtension(),
            'size'         => $upload->getSize(),
            'model_type'   => MarketerProfile::class,
            'model_id'     => $profile->id,
        ]);
    }

    private function generateQrCode(MarketerProfile $profile): void
    {
        // Points to the customer-facing marketer profile page (handled by Next.js frontend)
        $url = rtrim(config('app.frontend_url', config('app.url')), '/') . '/marketer/' . $profile->profile_slug;

        $qr     = QrCode::create($url)->setSize(300)->setMargin(10)->setEncoding(new Encoding('UTF-8'));
        $result = (new PngWriter())->write($qr);

        $path = 'marketer-profile-qr/' . $profile->marketer_id . '.png';
        Storage::disk('public')->put($path, $result->getString());

        $profile->update(['qr_code_path' => $path]);
    }
}

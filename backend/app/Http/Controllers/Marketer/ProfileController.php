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

        return view('marketer.profile', compact('marketer', 'profile'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'bio_ar'          => 'nullable|string|max:1000',
            'bio_en'          => 'nullable|string|max:1000',
            'video_url'       => 'nullable|url|max:500',
            'social_links'    => 'nullable|array',
            'social_links.*'  => 'nullable|url|max:500',
            'contact_details' => 'nullable|array',
            'contact_details.*' => 'nullable|string|max:255',
            'whatsapp_for_campaigns' => 'nullable|string|max:30',
            'banner'          => 'nullable|image|max:5120',
        ]);

        $marketer = $this->marketer();
        $profile  = $marketer->marketerProfile()->firstOrCreate(['marketer_id' => $marketer->id]);

        // Update WhatsApp on the marketer row itself
        $marketer->update([
            'whatsapp_for_campaigns' => $request->whatsapp_for_campaigns,
        ]);

        if ($request->hasFile('banner')) {
            $upload = $request->file('banner');
            $path   = $upload->store('marketer-banners/' . $marketer->id, 'public');

            $file = File::create([
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

            $profile->banner_file_id = $file->id;
        }

        $profile->fill($request->only(['bio_ar', 'bio_en', 'video_url', 'social_links', 'contact_details']));
        $profile->save();

        if (! $profile->qr_code_path) {
            $this->generateQrCode($profile);
        }

        return back()->with('success', 'تم حفظ البروفايل.');
    }

    private function generateQrCode(MarketerProfile $profile): void
    {
        $url = url('/m/' . $profile->profile_slug);

        $qr     = QrCode::create($url)->setSize(300)->setMargin(10)->setEncoding(new Encoding('UTF-8'));
        $result = (new PngWriter())->write($qr);

        $path = 'marketer-profile-qr/' . $profile->marketer_id . '.png';
        Storage::disk('public')->put($path, $result->getString());

        $profile->update(['qr_code_path' => $path]);
    }
}

<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\File;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MarketerProfileController extends Controller
{
    use AuthorizesRequests;

    private function vendor(): \App\Models\Vendor
    {
        $vendor = Auth::guard('vendor')->user()->vendor;
        abort_unless($vendor->isMarketer(), 403, 'هذا الحساب ليس ماركتر.');

        return $vendor;
    }

    public function show()
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_profile.view'),
            403
        );
        $vendor  = $this->vendor();
        $profile = $vendor->marketerProfile()->firstOrCreate(['vendor_id' => $vendor->id]);

        if (! $profile->profile_slug) {
            $profile->update(['profile_slug' => Str::slug($vendor->store_name).'-'.Str::lower(Str::random(6))]);
        }

        return view('partner.marketer.profile', compact('vendor', 'profile'));
    }

    public function update(Request $request)
    {
        abort_unless(
            auth('vendor')->user()?->hasPermissionTo('marketer_profile.edit'),
            403
        );
        $vendor = $this->vendor();

        $request->validate([
            'bio_ar'                    => 'nullable|string|max:1000',
            'bio_en'                    => 'nullable|string|max:1000',
            'video_url'                 => 'nullable|url|max:500',
            'social_links'              => 'nullable|array',
            'social_links.*'            => 'nullable|url|max:500',
            'contact_details'           => 'nullable|array',
            'contact_details.*'         => 'nullable|string|max:255',
            'banner'                    => 'nullable|image|max:5120',
        ]);

        $profile = $vendor->marketerProfile()->firstOrCreate(['vendor_id' => $vendor->id]);

        if ($request->hasFile('banner')) {
            $upload = $request->file('banner');
            $path   = $upload->store('marketer-banners/'.$vendor->id, 'public');

            $file = File::create([
                'key'          => Str::uuid(),
                'path'         => $path,
                'storage_type' => 'public',
                'file_type'    => 'image',
                'mime_type'    => $upload->getClientMimeType(),
                'extension'    => $upload->getClientOriginalExtension(),
                'size'         => $upload->getSize(),
                'model_type'   => \App\Models\MarketerProfile::class,
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

    private function generateQrCode(\App\Models\MarketerProfile $profile): void
    {
        // VERIFY: replace with the real public marketer-profile route once it exists.
        $url = url('/m/'.$profile->profile_slug);

        $qr     = QrCode::create($url)->setSize(300)->setMargin(10)->setEncoding(new Encoding('UTF-8'));
        $result = (new PngWriter())->write($qr);

        $path = 'marketer-profile-qr/'.$profile->vendor_id.'.png';
        Storage::disk('public')->put($path, $result->getString());

        $profile->update(['qr_code_path' => $path]);
    }
}

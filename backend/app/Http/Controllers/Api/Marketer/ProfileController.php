<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\MarketerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function show()
    {
        $marketer = Auth::guard('marketer_api')->user()->marketer;
        $profile  = $marketer->marketerProfile()->firstOrCreate(['marketer_id' => $marketer->id]);
        return response()->json(['success' => true, 'marketer' => $marketer, 'profile' => $profile]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'bio_ar'          => 'nullable|string|max:1000',
            'bio_en'          => 'nullable|string|max:1000',
            'video_url'       => 'nullable|url|max:500',
            'whatsapp_for_campaigns' => 'nullable|string|max:30',
            'avatar'          => 'nullable|image|max:5120',
            'banner'          => 'nullable|image|max:5120',
        ]);
        $marketer = Auth::guard('marketer_api')->user()->marketer;
        $marketer->update(['whatsapp_for_campaigns' => $request->whatsapp_for_campaigns]);
        $profile  = $marketer->marketerProfile()->firstOrCreate(['marketer_id' => $marketer->id]);

        if ($request->hasFile('avatar')) {
            $profile->avatar_file_id = $this->storeProfileImage($request, 'avatar', 'marketer-avatars', $profile)->id;
        }

        if ($request->hasFile('banner')) {
            $profile->banner_file_id = $this->storeProfileImage($request, 'banner', 'marketer-banners', $profile)->id;
        }

        $profile->fill($request->only(['bio_ar', 'bio_en', 'video_url', 'social_links', 'contact_details']))->save();
        return response()->json(['success' => true, 'message' => 'تم تحديث البروفايل.']);
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
}

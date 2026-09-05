<?php

namespace App\Http\Controllers\Api\Marketer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        ]);
        $marketer = Auth::guard('marketer_api')->user()->marketer;
        $marketer->update(['whatsapp_for_campaigns' => $request->whatsapp_for_campaigns]);
        $profile  = $marketer->marketerProfile()->firstOrCreate(['marketer_id' => $marketer->id]);
        $profile->fill($request->only(['bio_ar', 'bio_en', 'video_url', 'social_links', 'contact_details']))->save();
        return response()->json(['success' => true, 'message' => 'تم تحديث البروفايل.']);
    }
}

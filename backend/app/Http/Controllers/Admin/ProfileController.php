<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        $admin = auth('admin')->user();
        return view('admin.profile.edit', compact('admin'));
    }

    public function update(Request $request)
    {
        $admin = auth('admin')->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:admins,email,' . $admin->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        $admin->name = $validated['name'];
        $admin->email = $validated['email'];
        $admin->phone = $validated['phone'] ?? null;

        if (!empty($validated['password'])) {
            $admin->password = Hash::make($validated['password']);
        }

        $admin->save();

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $path = $file->store('admins/avatars', 'public');

            $admin->files()->updateOrCreate(
                ['key' => 'avatar'],
                [
                    'path' => $path,
                    'storage_type' => 'public',
                    'file_type' => 'image',
                    'mime_type' => $file->getClientMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                    'size' => $file->getSize(),
                ]
            );
        }

        return redirect()->route('admin.profile.edit')->with('success', __('common.saved_successfully') ?? 'Profile updated successfully.');
    }
}

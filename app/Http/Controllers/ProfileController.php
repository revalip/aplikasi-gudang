<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile.edit', [
            'user' => auth()->user(),
        ]);
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();

        $photoRules = ['nullable', 'file', 'max:2048', 'extensions:jpg,jpeg,png,webp'];
        if (extension_loaded('fileinfo')) {
            $photoRules[] = 'image';
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:50',
            'photo' => $photoRules,
            'password' => 'nullable|string|min:8|confirmed',
        ], [
            'required' => ':attribute is required.',
            'email' => ':attribute must be a valid email address.',
            'unique' => ':attribute is already in use by another user.',
            'min' => ':attribute must be at least :min characters.',
            'confirmed' => 'Password confirmation does not match.',
            'image' => 'The avatar must be an image file (JPG, PNG, WEBP).',
            'extensions' => 'The avatar must have a valid extension (.jpg, .jpeg, .png, .webp).',
            'max' => ':attribute must not exceed :max kilobytes.',
        ], [
            'name' => 'Name',
            'email' => 'Email Address',
            'phone' => 'Phone Number',
            'password' => 'Password',
            'photo' => 'Avatar Photo',
        ]);

        // Handle Avatar Photo (Immune to missing PHP finfo extension)
        if ($request->hasFile('photo')) {
            try {
                if ($user->photo_path && Storage::disk('local')->exists($user->photo_path)) {
                    @Storage::disk('local')->delete($user->photo_path);
                }
                $file = $request->file('photo');
                $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
                $filename = \Illuminate\Support\Str::random(40) . '.' . $ext;
                $targetDir = storage_path('app/private/avatars');
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0775, true);
                }
                // move() uses native move_uploaded_file, bypassing Symfony's finfo MIME guesser completely
                $file->move($targetDir, $filename);
                $data['photo_path'] = 'avatars/' . $filename;
            } catch (\Throwable $e) {
                return back()->withInput()->withErrors([
                    'photo' => 'Gagal mengunggah foto profil: ' . $e->getMessage() . '. Pastikan izin folder storage dapat ditulis oleh webserver (chown -R www:www storage && chmod -R 775 storage).',
                ]);
            }
        } elseif ($request->boolean('remove_photo')) {
            try {
                if ($user->photo_path && Storage::disk('local')->exists($user->photo_path)) {
                    @Storage::disk('local')->delete($user->photo_path);
                }
                $data['photo_path'] = null;
            } catch (\Throwable $e) {
                $data['photo_path'] = null;
            }
        }

        // Handle Password
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        unset($data['photo']);

        $user->update($data);

        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully.');
    }
}


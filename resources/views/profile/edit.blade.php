@extends('layouts.app')
@section('title', 'Profile Settings')

@section('content')
<x-page-header title="Profile Settings" icon="user-cog">
    <x-slot:subtitle>Manage your account credentials, avatar picture, contact number, and security settings.</x-slot:subtitle>
</x-page-header>

<div class="max-w-4xl space-y-6">
    @if ($errors->any())
        <div class="rounded-2xl border border-accent/30 bg-accent-tint p-4 text-sm font-semibold text-accent-dark">
            <div class="flex items-center gap-2 mb-1 font-bold">
                <i data-lucide="circle-alert" class="h-4 w-4"></i> Please check the following issues:
            </div>
            <ul class="list-disc pl-6 space-y-0.5 font-normal text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- SECTION 1: AVATAR & BASIC DETAILS --}}
        <section class="rounded-2xl border border-line bg-white p-5 md:p-6 shadow-sm">
            <h2 class="text-base font-extrabold text-ink mb-1">Avatar & Profile Photo</h2>
            <p class="text-xs text-muted mb-5">Your avatar is displayed in the top bar, activity logs, and system audits.</p>

            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">
                <div class="relative group">
                    <div id="avatar-preview-container" class="h-24 w-24 rounded-full overflow-hidden border-2 border-line shadow-inner flex items-center justify-center bg-canvas">
                        @if ($user->photo_path)
                            <img id="avatar-preview-img" src="{{ route('users.photo', $user) }}" alt="{{ $user->name }}" class="h-full w-full object-cover">
                        @else
                            <div id="avatar-preview-placeholder" class="h-full w-full flex items-center justify-center bg-brand-tint text-brand text-2xl font-black">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex-1 space-y-3 text-center sm:text-left">
                    <div>
                        <label for="photo-input" class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-brand-tint px-4 py-2 text-xs font-bold text-brand hover:bg-brand hover:text-white transition-colors">
                            <i data-lucide="upload" class="h-4 w-4"></i> Upload New Picture
                        </label>
                        <input id="photo-input" type="file" name="photo" accept="image/png,image/jpeg,image/webp" class="hidden">
                        <span id="selected-filename" class="ml-2 text-xs text-muted italic"></span>
                    </div>

                    <p class="text-[11px] text-muted">Allowed formats: JPG, PNG, WEBP. Maximum file size: 2MB.</p>

                    @if ($user->photo_path)
                        <label class="inline-flex items-center gap-2 cursor-pointer pt-1">
                            <input type="checkbox" name="remove_photo" value="1" class="h-4 w-4 rounded border-slate-300 text-accent focus:ring-accent">
                            <span class="text-xs font-semibold text-accent-dark">Remove current avatar photo</span>
                        </label>
                    @endif
                </div>
            </div>
        </section>

        {{-- SECTION 2: PERSONAL & CONTACT INFORMATION --}}
        <section class="rounded-2xl border border-line bg-white p-5 md:p-6 shadow-sm">
            <h2 class="text-base font-extrabold text-ink mb-1">Personal & Contact Information</h2>
            <p class="text-xs text-muted mb-5">Ensure your contact details are accurate for system notifications and delivery handovers.</p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="block text-xs font-bold text-slate-700 uppercase tracking-wide">Full Name <span class="text-accent">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required
                           class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/30 px-3.5 text-sm font-semibold text-ink focus:border-brand focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="email" class="block text-xs font-bold text-slate-700 uppercase tracking-wide">Email Address <span class="text-accent">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required
                           class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/30 px-3.5 text-sm font-semibold text-ink focus:border-brand focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="phone" class="block text-xs font-bold text-slate-700 uppercase tracking-wide">Phone / Telepon</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="+62 8..."
                           class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/30 px-3.5 text-sm font-semibold text-ink focus:border-brand focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide">Division / Department</label>
                    <input type="text" disabled value="{{ $user->division ?: 'General' }}"
                           class="mt-1.5 h-11 w-full rounded-xl border border-line bg-slate-100 px-3.5 text-sm text-slate-500 cursor-not-allowed">
                </div>

                <div class="sm:col-span-2 pt-1">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Assigned Role</label>
                    <div class="inline-flex items-center gap-2 rounded-xl border border-brand/20 bg-brand-tint px-3 py-1.5 text-xs font-bold text-brand">
                        <i data-lucide="shield-check" class="h-4 w-4"></i>
                        <span>{{ $user->roleLabel() }}</span>
                    </div>
                    <span class="ml-2 text-xs text-muted">Role permissions are managed by Superadmin.</span>
                </div>
            </div>
        </section>

        {{-- SECTION 3: SECURITY & PASSWORD --}}
        <section class="rounded-2xl border border-line bg-white p-5 md:p-6 shadow-sm">
            <h2 class="text-base font-extrabold text-ink mb-1">Security & Password</h2>
            <p class="text-xs text-muted mb-5">Leave the password fields empty if you do not wish to change your current password.</p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wide">New Password</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" placeholder="Leave blank to keep current"
                           class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/30 px-3.5 text-sm text-ink focus:border-brand focus:bg-white focus:outline-none">
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-bold text-slate-700 uppercase tracking-wide">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" placeholder="Repeat new password"
                           class="mt-1.5 h-11 w-full rounded-xl border border-line bg-canvas/30 px-3.5 text-sm text-ink focus:border-brand focus:bg-white focus:outline-none">
                </div>
            </div>
        </section>

        {{-- ACTION BUTTONS --}}
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-6 text-sm font-bold text-white hover:bg-brand-dark transition-colors shadow-sm">
                <i data-lucide="save" class="h-4 w-4"></i> Save Settings
            </button>
            <a href="{{ route('dashboard') }}" class="inline-flex h-11 items-center rounded-xl px-5 text-sm font-bold text-slate-600 hover:bg-canvas transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    // Live preview for avatar file input
    const photoInput = document.getElementById('photo-input');
    const previewContainer = document.getElementById('avatar-preview-container');
    const filenameLabel = document.getElementById('selected-filename');

    if (photoInput && previewContainer) {
        photoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (filenameLabel) {
                filenameLabel.textContent = file.name;
            }

            const reader = new FileReader();
            reader.onload = function(evt) {
                previewContainer.innerHTML = `<img src="${evt.target.result}" alt="Preview" class="h-full w-full object-cover">`;
            };
            reader.readAsDataURL(file);
        });
    }
</script>
@endpush
@endsection


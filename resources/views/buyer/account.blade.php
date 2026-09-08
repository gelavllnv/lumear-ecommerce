@extends('layouts.buyer')

@section('title', 'My Account — Lumear')

@section('content')

<div x-data="accountPage()" class="max-w-6xl mx-auto px-4 md:px-6 pt-8 pb-20">
    <h1 class="font-display text-3xl text-maroon-900 mb-8">My Account</h1>

    <div class="grid md:grid-cols-4 gap-8">

        {{-- Sidebar --}}
        <aside class="md:col-span-1">
            <div class="bg-cream-50 border border-sand-400/40 rounded-lg p-5 mb-5 flex items-center gap-3">

                {{-- Avatar: click anywhere on it (or the hover camera icon) to pick a photo --}}
                <label class="relative block w-14 h-14 rounded-full cursor-pointer group shrink-0">
                    {{-- name="avatar" — on the backend, either nest this inside the profile form below
                         or wire it to its own endpoint (e.g. POST /account/avatar) via fetch on change. --}}
                    <input x-ref="fileInput" type="file" name="avatar" accept="image/*" class="hidden" @change="onSelect($event)">

                    <div class="w-14 h-14 rounded-full overflow-hidden bg-maroon-700 grid place-items-center">
                        <img x-show="preview" x-cloak :src="preview" class="w-full h-full object-cover" alt="Profile photo">
                        <span x-show="!preview" class="text-cream-50 text-xl font-display">JD</span>
                    </div>

                    {{-- Hover overlay --}}
                    <span class="absolute inset-0 rounded-full bg-maroon-950/50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity duration-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-cream-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 13a3 3 0 100 6 3 3 0 000-6z"/>
                        </svg>
                    </span>

                    {{-- Small always-visible camera badge --}}
                    <span class="absolute -bottom-0.5 -right-0.5 w-5 h-5 rounded-full bg-maroon-700 border-2 border-cream-50 grid place-items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-cream-50" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8a4 4 0 100 8 4 4 0 000-8zm8-3h-2.5l-1-1.5a1 1 0 00-.8-.5H8.3a1 1 0 00-.8.5L6.5 5H4a2 2 0 00-2 2v11a2 2 0 002 2h16a2 2 0 002-2V7a2 2 0 00-2-2z"/></svg>
                    </span>
                </label>

                <div class="min-w-0">
                    <p class="text-sm font-semibold text-maroon-900">Juana Dela Cruz</p>
                    {{-- No backend/name lookup yet — so rather than guessing a name from the
                         login identifier, this is honest about what we actually know: what
                         was typed at login. The name above stays placeholder until real auth exists. --}}
                    <p class="text-xs text-maroon-900/50" x-text="identity ? 'Signed in as ' + identity : 'Verified buyer'"></p>
                    <div class="flex items-center gap-2.5 mt-1">
                        <button type="button" @click="$refs.fileInput.click()" class="text-[11px] font-semibold text-maroon-700 link-grow">Change photo</button>
                        <template x-if="preview">
                            <button type="button" @click="remove()" class="text-[11px] font-semibold text-maroon-900/40 link-grow">Remove</button>
                        </template>
                    </div>
                </div>
            </div>
            <nav class="bg-cream-50 border border-sand-400/40 rounded-lg overflow-hidden text-sm">
                @foreach ([
                    'profile' => ['My Profile', '🪪'],
                    'addresses' => ['Addresses', '📍'],
                    'notifications' => ['Notifications', '🔔'],
                    'security' => ['Password & Security', '🔒'],
                ] as $key => [$label, $icon])
                    <button @click="tab = '{{ $key }}'"
                            class="w-full flex items-center gap-3 px-5 py-3.5 border-b border-sand-400/20 last:border-b-0 transition-colors duration-200"
                            :class="tab === '{{ $key }}' ? 'bg-maroon-700 text-cream-50' : 'hover:bg-sand-400/15 text-maroon-800'">
                        <span>{{ $icon }}</span><span class="font-medium">{{ $label }}</span>
                    </button>
                @endforeach
            </nav>
        </aside>

        {{-- Content --}}
        <div class="md:col-span-3 space-y-6">

            {{-- Profile --}}
            <div x-show="tab === 'profile'" x-transition.opacity class="bg-cream-50 border border-sand-400/40 rounded-lg p-7">
                <h2 class="font-display text-xl text-maroon-900 mb-6">Profile Information</h2>
                <form class="grid md:grid-cols-2 gap-5">
                    <div>
                        <label class="text-xs font-semibold text-maroon-900/60 uppercase tracking-wide">First name</label>
                        <input type="text" value="Juana" class="mt-2 w-full h-12 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-500 transition-colors">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-maroon-900/60 uppercase tracking-wide">Last name</label>
                        <input type="text" value="Dela Cruz" class="mt-2 w-full h-12 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-500 transition-colors">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-maroon-900/60 uppercase tracking-wide">Middle initial</label>
                        <input type="text" value="S." class="mt-2 w-full h-12 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-500 transition-colors">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-maroon-900/60 uppercase tracking-wide">Sex</label>
                        <select class="mt-2 w-full h-12 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-500 transition-colors">
                            <option>Female</option><option>Male</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-maroon-900/60 uppercase tracking-wide">Email</label>
                        <input type="email" :value="isEmail && identity ? identity : 'juana.delacruz@email.com'" class="mt-2 w-full h-12 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-500 transition-colors">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-maroon-900/60 uppercase tracking-wide">Contact no.</label>
                        <input type="text" :value="!isEmail && identity ? identity : '0917 123 4567'" class="mt-2 w-full h-12 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-500 transition-colors">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-maroon-900/60 uppercase tracking-wide">Birthday</label>
                        <input type="date" value="1998-04-12" class="mt-2 w-full h-12 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-500 transition-colors">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-maroon-900/60 uppercase tracking-wide">Age</label>
                        <input type="text" value="27" disabled class="mt-2 w-full h-12 rounded-xl bg-sand-400/25 border border-sand-400/50 px-4 text-sm text-maroon-900/50">
                    </div>
                    <div class="md:col-span-2 flex justify-end pt-2">
                        <button type="submit" class="px-8 py-3 rounded-md bg-maroon-700 text-cream-50 text-sm font-semibold hover:bg-maroon-600 hover:shadow-lift transition-all duration-300">Save Changes</button>
                    </div>
                </form>
            </div>

            {{-- Addresses --}}
            <div x-show="tab === 'addresses'" x-cloak x-transition.opacity class="bg-cream-50 border border-sand-400/40 rounded-lg p-7">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-display text-xl text-maroon-900">Saved Addresses</h2>
                    <button class="px-5 py-2.5 rounded-md border border-maroon-700 text-maroon-700 text-sm font-semibold hover:bg-maroon-700/5 transition-colors">+ Add Address</button>
                </div>
                <div class="space-y-4">
                    <div class="border border-sand-400/40 rounded-xl p-5">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-maroon-900">Juana Dela Cruz</span>
                            <span class="text-xs bg-maroon-700 text-cream-50 px-2 py-0.5 rounded-md">Default</span>
                        </div>
                        <p class="text-sm text-maroon-900/60 mt-1.5">0917 123 4567</p>
                        <p class="text-sm text-maroon-900/60">123 Sampaguita St., Brgy. San Isidro, Candaba, Pampanga, Central Luzon</p>
                    </div>
                </div>
            </div>

            {{-- Notifications --}}
            <div x-show="tab === 'notifications'" x-cloak x-transition.opacity class="bg-cream-50 border border-sand-400/40 rounded-lg p-7 space-y-4">
                <h2 class="font-display text-xl text-maroon-900 mb-2">Notifications</h2>
                @foreach (['Order updates', 'Promos & vouchers', 'Chat messages', 'Price drop alerts'] as $n)
                    <label class="flex items-center justify-between py-2">
                        <span class="text-sm text-maroon-900">{{ $n }}</span>
                        <input type="checkbox" checked class="w-5 h-5 rounded accent-maroon-700">
                    </label>
                @endforeach
            </div>

            {{-- Security --}}
            <div x-show="tab === 'security'" x-cloak x-transition.opacity class="bg-cream-50 border border-sand-400/40 rounded-lg p-7 space-y-5 max-w-md">
                <h2 class="font-display text-xl text-maroon-900 mb-2">Password & Security</h2>
                <div>
                    <label class="text-xs font-semibold text-maroon-900/60 uppercase tracking-wide">Current password</label>
                    <input type="password" class="mt-2 w-full h-12 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-500 transition-colors">
                </div>
                <div>
                    <label class="text-xs font-semibold text-maroon-900/60 uppercase tracking-wide">New password</label>
                    <input type="password" class="mt-2 w-full h-12 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-500 transition-colors">
                </div>
                <button class="px-8 py-3 rounded-md bg-maroon-700 text-cream-50 text-sm font-semibold hover:bg-maroon-600 transition-colors">Update Password</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function accountPage() {
        return {
            tab: 'profile',

            // What was typed at login (no backend yet — see login.blade.php's @submit.prevent).
            // Once real auth exists, this whole block goes away and the profile fields below
            // get their values from the actual logged-in user record instead.
            identity: localStorage.getItem('lumear_identity') || '',
            get isEmail() { return this.identity.includes('@') },

            // Avatar picker
            preview: null,
            onSelect(e) {
                const file = e.target.files[0];
                if (!file) return;
                if (!file.type.startsWith('image/')) { alert('Please choose an image file.'); return; }
                if (file.size > 5 * 1024 * 1024) { alert('Image must be under 5MB.'); return; }
                const reader = new FileReader();
                reader.onload = () => { this.preview = reader.result };
                reader.readAsDataURL(file);
            },
            remove() {
                this.preview = null;
                this.$refs.fileInput.value = '';
            }
        }
    }
</script>
@endpush

@endsection
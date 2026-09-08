@extends('layouts.auth')

@section('title', 'Create your account — Lumear')
@section('pageTitle', 'Sign Up')

@section('content')
<div x-data="registerWizard()">

    <h1 class="font-display text-[26px] text-maroon-900 leading-tight">Create your account</h1>
    <p class="text-sm text-maroon-900/50 mt-1.5">Already registered? <a href="{{ url('/') }}" class="text-maroon-700 font-bold link-grow">Log in</a></p>

    {{-- Standard Laravel flash message — once /register is a real controller, a
         redirect()->back()->with('status', '...') (e.g. on validation failure) shows here. --}}
    @if (session('status'))
        <div class="mt-4 bg-maroon-700/10 border border-maroon-700/30 text-maroon-800 text-sm rounded-xl px-4 py-3">
            {{ session('status') }}
        </div>
    @endif

    {{-- Step indicator: numbered circles + filling connector, Shopee checkout-style --}}
    <div class="flex items-center mt-7 mb-7">
        @foreach ([1 => 'Personal', 2 => 'Address', 3 => 'Verify'] as $n => $label)
            <div class="flex items-center {{ $n < 3 ? 'flex-1' : '' }}">
                <div class="flex flex-col items-center gap-1.5 shrink-0">
                    <div class="w-8 h-8 rounded-full grid place-items-center text-xs font-bold transition-colors duration-300"
                         :class="step >= {{ $n }} ? 'bg-maroon-700 text-cream-50' : 'bg-sand-400/30 text-maroon-900/40'">
                        <template x-if="step > {{ $n }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </template>
                        <template x-if="step <= {{ $n }}"><span>{{ $n }}</span></template>
                    </div>
                    <span class="text-[10px] font-medium text-maroon-900/45 whitespace-nowrap">{{ $label }}</span>
                </div>
                @if ($n < 3)
                    <div class="flex-1 h-0.5 mx-1.5 -mt-4 rounded-full transition-colors duration-300" :class="step > {{ $n }} ? 'bg-maroon-700' : 'bg-sand-400/30'"></div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- @submit.prevent is a placeholder so this demo shows a confirmation modal without a
         real backend. Once /register actually saves the buyer, remove @submit.prevent so
         this becomes a normal POST, and drive the same confirmation from a `status` flash
         message on redirect instead (see the banner below the heading). --}}
    <form method="POST" action="{{ url('/register') }}" enctype="multipart/form-data" @submit.prevent="submitted = true">
        @csrf

        {{-- STEP 1 — Personal info --}}
        <div x-show="step === 1" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-3" x-transition:enter-end="opacity-100 translate-x-0" class="space-y-3.5">

            <div class="grid grid-cols-2 gap-3.5">
                <input type="text" name="first_name" required placeholder="First name*"
                       class="h-11 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-600 focus:bg-cream-50 transition-colors">
                <input type="text" name="last_name" required placeholder="Last name*"
                       class="h-11 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-600 focus:bg-cream-50 transition-colors">
            </div>

            <div class="grid grid-cols-2 gap-3.5">
                <input type="text" name="middle_initial" maxlength="4" placeholder="M.I."
                       class="h-11 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-600 focus:bg-cream-50 transition-colors">
                <select name="sex" required class="h-11 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-600 focus:bg-cream-50 transition-colors text-maroon-900/70">
                    <option value="">Sex*</option><option>Female</option><option>Male</option>
                </select>
            </div>

            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-maroon-900/35">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </span>
                <input type="email" name="email" required placeholder="Email*"
                       class="w-full h-11 rounded-xl bg-sand-400/15 border border-sand-400/50 pl-11 pr-4 text-sm outline-none focus:border-maroon-600 focus:bg-cream-50 transition-colors">
            </div>

            {{-- Phone with attached country code, mirrors Shopee's phone field --}}
            <div class="flex rounded-xl border border-sand-400/50 bg-sand-400/15 overflow-hidden focus-within:border-maroon-600 focus-within:bg-cream-50 transition-colors">
                <span class="flex items-center gap-1.5 px-3.5 text-sm text-maroon-900/60 border-r border-sand-400/50 shrink-0">🇵🇭 +63</span>
                <input type="text" name="contact_no" required placeholder="9XX XXX XXXX"
                       class="flex-1 h-11 bg-transparent px-3.5 text-sm outline-none min-w-0">
            </div>

            <div class="grid grid-cols-2 gap-3.5">
                <div>
                    <input type="date" name="birthday" required x-model="birthday" @change="computeAge"
                           class="w-full h-11 rounded-xl bg-sand-400/15 border border-sand-400/50 px-3.5 text-sm outline-none focus:border-maroon-600 focus:bg-cream-50 transition-colors text-maroon-900/70">
                    <span class="text-[10px] text-maroon-900/40 ml-1">Birthday*</span>
                </div>
                <div>
                    <input type="text" :value="age" disabled placeholder="Age"
                           class="w-full h-11 rounded-xl bg-sand-400/25 border border-sand-400/50 px-3.5 text-sm text-maroon-900/50">
                    <span class="text-[10px] text-maroon-900/40 ml-1">Auto-generated</span>
                </div>
            </div>

            <button type="button" @click="step = 2" class="w-full h-12 rounded-xl bg-maroon-700 text-cream-50 font-bold text-sm tracking-wide hover:bg-maroon-600 hover:shadow-lift transition-all duration-300 mt-2">
                CONTINUE
            </button>
        </div>

        {{-- STEP 2 — Address. Province/Municipality/Barangay are populated live from the
             PSGC API (psgc.gitlab.io) — the official Philippine Standard Geographic Code
             dataset, free and public, no API key needed. Picking a province fetches its
             cities/municipalities; picking one of those fetches its barangays. --}}
        <div x-init="loadProvinces()"
             x-show="step === 2" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-3" x-transition:enter-end="opacity-100 translate-x-0" class="space-y-3.5">

            <div>
                <select name="province_code" x-model="selectedProvinceCode" @change="onProvinceChange()" required
                        class="w-full h-11 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-600 focus:bg-cream-50 transition-colors text-maroon-900/70">
                    <option value="">Province*</option>
                    <template x-for="p in provinces" :key="p.code">
                        <option :value="p.code" x-text="p.name"></option>
                    </template>
                </select>
                <p x-show="loadingProvinces" class="text-[11px] text-maroon-900/40 mt-1">Loading provinces…</p>
                <p x-show="errorProvinces" x-text="errorProvinces" class="text-[11px] text-maroon-600 mt-1"></p>
                <input type="hidden" name="province" :value="provinces.find(p => p.code === selectedProvinceCode)?.name || ''">
            </div>

            <div class="grid grid-cols-2 gap-3.5">
                <div>
                    <select name="municipality_code" x-model="selectedMunicipalityCode" @change="onMunicipalityChange()" required :disabled="!selectedProvinceCode"
                            class="w-full h-11 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-600 focus:bg-cream-50 transition-colors text-maroon-900/70 disabled:opacity-50 disabled:cursor-not-allowed">
                        <option value="">Municipality/City*</option>
                        <template x-for="m in municipalities" :key="m.code">
                            <option :value="m.code" x-text="m.name"></option>
                        </template>
                    </select>
                    <p x-show="loadingMunicipalities" class="text-[11px] text-maroon-900/40 mt-1">Loading…</p>
                    <p x-show="errorMunicipalities" x-text="errorMunicipalities" class="text-[11px] text-maroon-600 mt-1"></p>
                    <input type="hidden" name="municipality" :value="municipalities.find(m => m.code === selectedMunicipalityCode)?.name || ''">
                </div>
                <div>
                    <select name="barangay" required :disabled="!selectedMunicipalityCode"
                            class="w-full h-11 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-600 focus:bg-cream-50 transition-colors text-maroon-900/70 disabled:opacity-50 disabled:cursor-not-allowed">
                        <option value="">Barangay*</option>
                        <template x-for="b in barangays" :key="b.code">
                            <option :value="b.name" x-text="b.name"></option>
                        </template>
                    </select>
                    <p x-show="loadingBarangays" class="text-[11px] text-maroon-900/40 mt-1">Loading…</p>
                    <p x-show="errorBarangays" x-text="errorBarangays" class="text-[11px] text-maroon-600 mt-1"></p>
                </div>
            </div>

            <input type="text" name="street" required placeholder="Street*"
                   class="w-full h-11 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-600 focus:bg-cream-50 transition-colors">
            <input type="text" name="house_number" required placeholder="House / unit no.*"
                   class="w-full h-11 rounded-xl bg-sand-400/15 border border-sand-400/50 px-4 text-sm outline-none focus:border-maroon-600 focus:bg-cream-50 transition-colors">

            <div class="flex gap-3 pt-1">
                <button type="button" @click="step = 1" class="flex-1 h-12 rounded-xl border border-sand-400/60 text-maroon-800 font-bold text-sm hover:bg-sand-400/15 transition-colors">BACK</button>
                <button type="button" @click="step = 3" class="flex-1 h-12 rounded-xl bg-maroon-700 text-cream-50 font-bold text-sm tracking-wide hover:bg-maroon-600 hover:shadow-lift transition-all duration-300">CONTINUE</button>
            </div>
        </div>

        {{-- STEP 3 — ID upload --}}
        <div x-show="step === 3" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-3" x-transition:enter-end="opacity-100 translate-x-0">

            <label class="block border-2 border-dashed border-sand-400/60 rounded-2xl p-7 text-center cursor-pointer hover:border-maroon-500 transition-colors duration-300" x-data="{ fileName: '' }">
                <input type="file" name="valid_id" required class="hidden" accept="image/*,.pdf" @change="fileName = $event.target.files[0]?.name">
                <span class="text-3xl block mb-2">🪪</span>
                <span class="text-sm font-semibold text-maroon-800" x-text="fileName || 'Upload a valid government ID'"></span>
                <span class="block text-xs text-maroon-900/40 mt-1">JPG, PNG, or PDF — max 5MB</span>
            </label>

            <div class="bg-sand-400/15 border border-sand-400/40 rounded-xl p-3.5 mt-4 flex gap-2.5">
                <span class="text-base shrink-0">⏳</span>
                <p class="text-xs text-maroon-900/60 leading-relaxed">After you submit, an administrator reviews your details. We'll email you once your account is approved.</p>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" @click="step = 2" class="flex-1 h-12 rounded-xl border border-sand-400/60 text-maroon-800 font-bold text-sm hover:bg-sand-400/15 transition-colors">BACK</button>
                {{-- Real validation still runs (required fields, file type) since only the
                     network submission is intercepted, not the browser's own form checks. --}}
                <button type="submit" class="flex-1 h-12 rounded-xl bg-maroon-700 text-cream-50 font-bold text-sm tracking-wide hover:bg-maroon-600 hover:shadow-lift transition-all duration-300">SUBMIT</button>
            </div>
        </div>
    </form>

    {{-- Confirmation modal — shown the instant Submit is clicked. Mirrors the exact
         admin-approval wording from the ERP spec so the message stays consistent
         whether it comes from this demo state or from a real backend redirect. --}}
    <div x-show="submitted" x-cloak
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         class="fixed inset-0 z-50 flex items-center justify-center bg-maroon-950/60 p-6">
        <div @click.outside="null" x-show="submitted"
             x-transition:enter="transition ease-out duration-250" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             class="bg-cream-50 rounded-lg shadow-lift max-w-sm w-full p-8 text-center">
            <div class="w-14 h-14 rounded-full bg-maroon-700/10 grid place-items-center mx-auto mb-5">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-maroon-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h2 class="font-display text-2xl text-maroon-900">Registration Submitted</h2>
            <p class="text-sm text-maroon-900/60 mt-3 leading-relaxed">
                After submitting your registration, please wait for the administrator's approval, which will be sent to your email.
            </p>
            <a href="{{ url('/') }}" class="block mt-7 h-12 rounded-xl bg-maroon-700 text-cream-50 font-bold text-sm tracking-wide leading-[3rem] hover:bg-maroon-600 transition-colors duration-200">
                BACK TO LOG IN
            </a>
        </div>
    </div>
</div>

<script>
    function registerWizard() {
        return {
            step: 1,
            birthday: '',
            age: '—',
            submitted: false,

            computeAge() {
                if (!this.birthday) { this.age = '—'; return; }
                const dob = new Date(this.birthday);
                const diff = Date.now() - dob.getTime();
                this.age = Math.abs(new Date(diff).getUTCFullYear() - 1970);
            },

            // ---- PH address cascade, backed by the free PSGC API (psgc.gitlab.io) ----
            // Province -> its cities/municipalities -> the chosen one's barangays.
            // No API key, no manual dataset to maintain — it's the official PSGC dataset
            // served as static JSON. If a field name below ever comes back undefined,
            // check the current shape at https://psgc.gitlab.io/api/ (schema has shifted
            // slightly between versions, but .code and .name have been stable).
            provinces: [],
            municipalities: [],
            barangays: [],
            selectedProvinceCode: '',
            selectedMunicipalityCode: '',
            loadingProvinces: false,
            loadingMunicipalities: false,
            loadingBarangays: false,
            errorProvinces: '',
            errorMunicipalities: '',
            errorBarangays: '',

            async loadProvinces() {
                if (this.provinces.length) return; // already loaded
                this.loadingProvinces = true; this.errorProvinces = '';
                try {
                    const res = await fetch('https://psgc.gitlab.io/api/provinces/');
                    if (!res.ok) throw new Error('bad response');
                    this.provinces = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    this.errorProvinces = "Couldn't load provinces — check your connection and reopen this step.";
                } finally {
                    this.loadingProvinces = false;
                }
            },

            async onProvinceChange() {
                this.municipalities = [];
                this.barangays = [];
                this.selectedMunicipalityCode = '';
                if (!this.selectedProvinceCode) return;
                this.loadingMunicipalities = true; this.errorMunicipalities = '';
                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/provinces/${this.selectedProvinceCode}/cities-municipalities/`);
                    if (!res.ok) throw new Error('bad response');
                    this.municipalities = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    this.errorMunicipalities = "Couldn't load municipalities/cities.";
                } finally {
                    this.loadingMunicipalities = false;
                }
            },

            async onMunicipalityChange() {
                this.barangays = [];
                if (!this.selectedMunicipalityCode) return;
                this.loadingBarangays = true; this.errorBarangays = '';
                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/cities-municipalities/${this.selectedMunicipalityCode}/barangays/`);
                    if (!res.ok) throw new Error('bad response');
                    this.barangays = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    this.errorBarangays = "Couldn't load barangays.";
                } finally {
                    this.loadingBarangays = false;
                }
            },
        }
    }
</script>
@endsection
@extends('layouts.auth')

@section('title', 'Log in — Lumear')
@section('pageTitle', 'Log In')

@section('content')
<div x-data="{ showPw: false, identity: '' }">

    <h1 class="font-display text-[26px] text-maroon-900 leading-tight">Log In</h1>

    {{-- No backend yet, so this intercepts the real POST and instead: saves whatever was
         typed into the identifier field (as a stand-in for "the logged-in buyer"), then
         sends them to the home page. Once /login is a real controller, delete
         @submit.prevent and the two lines inside it — a normal POST + redirect()->route('home')
         on the server will replace this, and the session (not localStorage) becomes the
         source of truth for who's logged in. --}}
    <form method="POST" action="{{ url('/login') }}" class="mt-7 space-y-4"
          @submit.prevent="localStorage.setItem('lumear_identity', identity); window.location.href = '{{ url('/home') }}'">
        @csrf

        {{-- ERP only collects Email and Contact No. at registration — no separate username exists,
             so the identifier field asks for those two rather than implying a username login. --}}
        <div class="relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-maroon-900/35">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </span>
            <input type="text" name="login" x-model="identity" required placeholder="Email or contact number"
                   class="w-full h-12 rounded-xl bg-sand-400/15 border border-sand-400/50 pl-11 pr-4 text-sm outline-none
                          focus:border-maroon-600 focus:bg-cream-50 transition-colors duration-200">
        </div>

        {{-- Password, with "Forgot?" sitting inline beside the field like Shopee's --}}
        <div class="flex items-center gap-3">
            <div class="relative flex-1 min-w-0">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-maroon-900/35">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 10-8 0v4h8z"/>
                    </svg>
                </span>
                <input :type="showPw ? 'text' : 'password'" name="password" required placeholder="Password"
                       class="w-full h-12 rounded-xl bg-sand-400/15 border border-sand-400/50 pl-11 pr-11 text-sm outline-none
                              focus:border-maroon-600 focus:bg-cream-50 transition-colors duration-200">
                <button type="button" @click="showPw = !showPw" class="absolute right-4 top-1/2 -translate-y-1/2 text-maroon-900/35 hover:text-maroon-700 transition-colors">
                    <svg x-show="!showPw" xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <svg x-show="showPw" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12c1.292 4.338 5.31 7.5 10.066 7.5.847 0 1.67-.105 2.454-.303M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                    </svg>
                </button>
            </div>
            <a href="#" class="text-xs text-maroon-700 font-semibold link-grow shrink-0">Forgot?</a>
        </div>

        <button type="submit" class="w-full h-12 rounded-xl bg-maroon-700 text-cream-50 font-bold text-sm tracking-wide hover:bg-maroon-600 hover:shadow-lift transition-all duration-300">
            LOG IN
        </button>

        <label class="flex items-center gap-2 text-xs text-maroon-900/55 pt-1">
            <input type="checkbox" checked class="w-4 h-4 rounded accent-maroon-700">
            Stay signed in
            <span title="Keeps you logged in on this device until you log out manually." class="text-maroon-900/30 cursor-help">ⓘ</span>
        </label>
    </form>

    <div class="flex items-center gap-3 my-6">
        <div class="flex-1 h-px bg-sand-400/40"></div>
        <span class="text-[11px] text-maroon-900/40 font-medium">OR</span>
        <div class="flex-1 h-px bg-sand-400/40"></div>
    </div>

    {{-- Google only, per request — full-width so it doesn't look like a half-empty row.
         Same demo redirect for now; a real "Continue with Google" needs OAuth wired server-side. --}}
    <button type="button" @click="localStorage.setItem('lumear_identity', 'Google Account'); window.location.href = '{{ url('/home') }}'"
            class="w-full h-12 rounded-xl border border-sand-400/60 flex items-center justify-center gap-2.5 text-sm font-semibold text-maroon-800 hover:border-maroon-600 hover:bg-sand-400/15 transition-colors duration-200">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-[18px] h-[18px]" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M23.5 12.27c0-.79-.07-1.54-.2-2.27H12v4.3h6.47a5.54 5.54 0 01-2.4 3.63v3h3.87c2.27-2.09 3.56-5.17 3.56-8.66z"/>
            <path fill="#34A853" d="M12 24c3.24 0 5.96-1.08 7.94-2.93l-3.87-3c-1.08.72-2.45 1.15-4.07 1.15-3.13 0-5.78-2.11-6.73-4.96H1.28v3.09A12 12 0 0012 24z"/>
            <path fill="#FBBC05" d="M5.27 14.26a7.2 7.2 0 010-4.52V6.65H1.28a12 12 0 000 10.7l3.99-3.09z"/>
            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0A12 12 0 001.28 6.65l3.99 3.09C6.22 6.86 8.87 4.75 12 4.75z"/>
        </svg>
        Continue with Google
    </button>

    <p class="text-center text-sm text-maroon-900/60 mt-7">
        New to Lumear? <a href="{{ url('/register') }}" class="text-maroon-700 font-bold link-grow">Sign up</a>
    </p>
</div>
@endsection
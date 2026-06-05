@extends('layouts.guest')

@section('title', 'Mag-login — '.config('app.name'))

@section('content')
    <div
        class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden p-4 sm:p-6"
        style="background: linear-gradient(180deg, #060d24 0%, #0B318F 45%, #123a7a 100%);"
    >
        {{-- Mobile blurred background --}}
        <div
            class="absolute inset-0 scale-105 bg-cover bg-center bg-no-repeat opacity-90 md:hidden"
            style="background-image: url('{{ asset('img/pulse-login-bg.png') }}'); filter: blur(8px);"
            aria-hidden="true"
        ></div>
        <div class="absolute inset-0 bg-black/50 md:hidden" aria-hidden="true"></div>

        {{-- Desktop background --}}
        <div
            class="absolute inset-0 hidden bg-cover bg-center bg-no-repeat opacity-90 md:block"
            style="background-image: url('{{ asset('img/pulse-login-bg.png') }}');"
            aria-hidden="true"
        ></div>
        <div
            class="absolute inset-0"
            style="background: linear-gradient(180deg, rgba(6,13,36,0.8) 0%, rgba(11,49,143,0.6) 50%, transparent 100%);"
            aria-hidden="true"
        ></div>

        <div class="absolute left-4 top-4 z-20 hidden sm:left-6 sm:top-6 md:block">
            <img src="{{ asset('img/icct-colleges-logo.png') }}" alt="ICCT Colleges" class="h-12 w-auto object-contain drop-shadow-lg sm:h-14">
        </div>

        <div class="relative z-10 w-full max-w-4xl lg:max-w-5xl">
            {{-- Mobile logo above card --}}
            <div class="mb-8 flex justify-center md:hidden">
                <img src="{{ asset('img/icct-colleges-logo.png') }}" alt="ICCT Colleges" class="h-20 w-auto max-w-[320px] object-contain drop-shadow-lg sm:h-24">
            </div>

            <div class="flex min-h-0 flex-col overflow-hidden rounded-2xl bg-white shadow-[0_25px_60px_-12px_rgba(0,0,0,0.45)] max-md:bg-white/95 max-md:backdrop-blur-sm sm:rounded-3xl md:min-h-[480px] md:flex-row">
                {{-- Welcome panel (desktop only) --}}
                <div
                    class="relative hidden flex-col justify-center overflow-hidden p-8 text-white sm:p-10 md:flex md:w-[42%]"
                    style="background: linear-gradient(145deg, #060d24 0%, #0B318F 40%, #1a3d8f 100%);"
                >
                    <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                        <div class="absolute -left-16 -top-16 h-56 w-56 rounded-full bg-[#00A3E6] opacity-30"></div>
                        <div class="absolute -right-12 top-1/3 h-40 w-40 rounded-full bg-[#38bdf8] opacity-20"></div>
                        <div class="absolute bottom-8 left-1/4 h-28 w-28 rounded-full bg-[#00A3E6] opacity-25"></div>
                    </div>

                    <div class="relative z-10">
                        <p class="mb-2 text-xs font-semibold tracking-[0.25em] text-white/80 sm:text-sm">MALIGAYANG PAGDATING</p>
                        <h1 class="mb-1 text-2xl font-bold leading-tight tracking-tight sm:text-3xl lg:text-4xl">
                            SKOLARIS
                            <br>
                            <span class="text-[#00A3E6]">PULSE</span>
                        </h1>
                        <div class="mb-5 mt-4 flex gap-1">
                            <span class="h-1 w-10 rounded-full bg-[#E31837]"></span>
                            <span class="h-1 w-10 rounded-full bg-[#F5B800]"></span>
                            <span class="h-1 w-10 rounded-full bg-[#00A3E6]"></span>
                        </div>
                        <p class="max-w-xs text-sm leading-relaxed text-white/75">
                            Desktop application ng ISKOLARIS. Mag-login gamit ang iyong account para magpatuloy.
                        </p>
                        <p class="mt-6 text-[11px] font-medium tracking-wide text-[#F5B800]">A Global Pinoy Distinction</p>
                    </div>
                </div>

                {{-- Login form --}}
                <div class="relative flex flex-1 flex-col justify-center bg-white p-6 sm:p-8 md:p-10">
                    <div class="absolute bottom-0 right-0 hidden h-16 w-16 translate-x-1/3 translate-y-1/3 rounded-full bg-[#00A3E6] opacity-20 sm:block" aria-hidden="true"></div>

                    <div class="relative z-10 mx-auto w-full max-w-sm">
                        <div class="mb-6 md:hidden">
                            <p class="text-xs font-semibold tracking-[0.2em] text-[#0B318F]/70">SKOLARIS PULSE</p>
                        </div>

                        <h2 class="mb-1 text-2xl font-bold text-gray-900 sm:text-3xl">Mag-login</h2>
                        <p class="mb-6 text-sm text-gray-500">Ilagay ang iyong credentials para ma-access ang PULSO.</p>

                        @if ($errors->any())
                            <div class="mb-4 rounded-xl border border-red-100 bg-red-50 p-3">
                                <p class="text-xs text-red-700 sm:text-sm">{{ $errors->first() }}</p>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login') }}" class="space-y-4">
                            @csrf

                            <div>
                                <div class="form-input-pill @error('email') !border-red-400 @enderror">
                                    <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <input
                                        id="email"
                                        name="email"
                                        type="email"
                                        value="{{ old('email') }}"
                                        placeholder="Email"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        class="flex-1 bg-transparent text-sm text-gray-900 outline-none placeholder:text-gray-400"
                                    >
                                </div>
                                @error('email')
                                    <p class="ml-1 mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <div class="form-input-pill @error('password') !border-red-400 @enderror">
                                    <svg class="h-5 w-5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    <input
                                        id="password"
                                        name="password"
                                        type="password"
                                        placeholder="Password"
                                        required
                                        autocomplete="current-password"
                                        class="flex-1 bg-transparent text-sm text-gray-900 outline-none placeholder:text-gray-400"
                                    >
                                    <button type="button" data-toggle-password="password" class="shrink-0 text-xs font-semibold text-[#0B318F]">IPAKITA</button>
                                </div>
                                @error('password')
                                    <p class="ml-1 mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="flex items-center text-sm">
                                <label class="flex cursor-pointer items-center gap-2 text-gray-700">
                                    <input type="checkbox" name="remember" class="h-4 w-4 rounded border-gray-300" style="accent-color: #0B318F;">
                                    Tandaan ako
                                </label>
                            </div>

                            <button
                                type="submit"
                                class="flex w-full items-center justify-center gap-2 rounded-xl py-3.5 text-sm font-semibold text-white shadow-md transition-all hover:opacity-95 hover:shadow-lg"
                                style="background: #1e293b;"
                            >
                                Mag-login
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <p class="mt-4 flex items-center justify-center gap-1.5 text-center text-[10px] text-white/60">
                <svg class="h-3 w-3 text-[#00A3E6]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                ICCT Colleges Foundation Inc. · Skolaris Pulse
            </p>
        </div>
    </div>
@endsection

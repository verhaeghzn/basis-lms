<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BASIS Mobile | Sign in</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=syne:700,800|dm-sans:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --basis-ink: #0b1f2a;
            --basis-accent: #04688f;
        }
        body { font-family: 'DM Sans', ui-sans-serif, sans-serif; }
        .mobile-login {
            min-height: 100dvh;
            display: grid;
            background: #0b1216;
        }
        .mobile-login__hero {
            position: relative;
            min-height: 38vh;
            overflow: hidden;
        }
        .mobile-login__hero img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 40%;
            transform: scale(1.06);
            animation: drift 16s ease-in-out infinite alternate;
        }
        .mobile-login__veil {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(8,18,24,.2), rgba(8,18,24,.78));
        }
        .mobile-login__copy {
            position: absolute;
            left: 1.25rem;
            right: 1.25rem;
            bottom: 1.25rem;
            color: #f7fbfd;
            z-index: 1;
        }
        .mobile-login__eyebrow {
            margin: 0;
            font-family: Syne, sans-serif;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .28em;
            text-transform: uppercase;
            opacity: .75;
        }
        .mobile-login__tagline {
            margin: .5rem 0 0;
            font-family: Syne, sans-serif;
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -.02em;
        }
        .mobile-login__panel {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 1.25rem 1.25rem 2rem;
            background: linear-gradient(180deg, #e8eef2, #f4f7f9);
        }
        @keyframes drift {
            from { transform: scale(1.06) translateY(0); }
            to { transform: scale(1.12) translateY(-1%); }
        }
        @media (prefers-reduced-motion: reduce) {
            .mobile-login__hero img { animation: none; transform: none; }
        }
    </style>
</head>
<body class="antialiased text-slate-800">
    <div class="mobile-login">
        <aside class="mobile-login__hero" aria-hidden="true">
            <img src="{{ asset('images/login-hero.jpg') }}" alt="">
            <div class="mobile-login__veil"></div>
            <div class="mobile-login__copy">
                <p class="mobile-login__eyebrow">Basis Mobile</p>
                <p class="mobile-login__tagline">All materials properly indexed.</p>
            </div>
        </aside>

        <div class="mobile-login__panel">
            <div class="w-full max-w-sm rounded-3xl border border-slate-200/80 bg-white/95 p-8 shadow-xl shadow-slate-900/10">
                <div class="mb-6 text-center">
                    <img src="{{ asset('images/logo.png') }}" alt="BASIS" class="mx-auto h-10 w-auto">
                    <h1 class="mt-4 text-2xl font-semibold tracking-tight text-slate-900" style="font-family: Syne, sans-serif">Sign in</h1>
                    <p class="mt-2 text-sm text-slate-500">Access research materials and samples.</p>
                </div>

                @if (session('status'))
                    <p class="mb-4 rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-800">
                        {{ session('status') }}
                    </p>
                @endif

                <form method="POST" action="{{ route('mobile.login.attempt') }}" class="space-y-4">
                    @csrf
                    <div class="space-y-2">
                        <label for="email" class="block text-sm font-medium text-slate-600">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100"
                        />
                        @error('email')
                            <p class="text-xs font-semibold text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="password" class="block text-sm font-medium text-slate-600">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100"
                        />
                        @error('password')
                            <p class="text-xs font-semibold text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center gap-2 text-slate-500">
                            <input type="checkbox" name="remember" class="rounded border-slate-300 text-sky-600 focus:ring-sky-200"/>
                            Remember me
                        </label>
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-[#04688f] py-3 text-sm font-semibold text-white shadow hover:bg-[#03506e]">
                        Sign in
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">
                    Need desktop features?
                    <a href="{{ route('filament.admin.auth.login') }}" class="font-semibold text-[#04688f] hover:underline">Go to admin</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>

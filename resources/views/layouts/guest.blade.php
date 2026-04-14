<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'JobGuard') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,700,900|inter:400,500,600" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
</head>

<body class="font-sans antialiased">
    <div class="auth-shell flex flex-col items-center justify-center px-6 py-12">
        <div
            class="fixed top-[-20%] left-[-10%] w-[50%] h-[50%] bg-blue-600/20 rounded-full blur-[120px] pointer-events-none z-0">
        </div>
        <div
            class="fixed bottom-[-20%] right-[-10%] w-[40%] h-[40%] bg-cyan-600/10 rounded-full blur-[100px] pointer-events-none z-0">
        </div>

        <div class="relative z-10 w-full flex flex-col items-center">
            <a href="{{ url('/') }}" class="flex flex-col items-center gap-3">
                <div
                    class="h-14 w-14 rounded-2xl bg-gradient-to-br from-cyan-400 to-blue-600 flex items-center justify-center shadow-[0_0_20px_rgba(34,211,238,0.4)]">
                    <i class="fas fa-shield-halved text-white text-2xl"></i>
                </div>
                <div class="text-center">
                    <h1 class="text-2xl font-['Outfit'] font-black tracking-wide text-white uppercase">Job<span
                            class="text-cyan-400">Guard</span></h1>
                    <p class="text-sm text-slate-400 mt-1">Secure access to your scam detection workspace</p>
                </div>
            </a>

            <div class="auth-card">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>

</html>
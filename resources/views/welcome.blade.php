<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>AI Job Analyzer | Cyber Security Tool</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('jobguard-favicon.svg?v=5') }}">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,700,900|inter:400,500,600" rel="stylesheet" />
    
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        .text-gradient {
            background: linear-gradient(to right, #22d3ee, #2563eb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="bg-[#070b14] text-slate-300 font-sans antialiased min-h-screen flex flex-col selection:bg-cyan-500/30">

    <!-- Abstract glowing background element -->
    <div class="fixed top-[-20%] left-[-10%] w-[50%] h-[50%] bg-blue-600/20 rounded-full blur-[120px] pointer-events-none z-0"></div>
    <div class="fixed bottom-[-20%] right-[-10%] w-[40%] h-[40%] bg-cyan-600/10 rounded-full blur-[100px] pointer-events-none z-0"></div>

    <!-- Navigation -->
    <header class="w-full px-6 py-6 z-10 relative border-b border-white/5 bg-[#070b14]/50 backdrop-blur-md">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="relative h-10 w-10 rounded-xl bg-gradient-to-br from-cyan-400 via-sky-500 to-blue-600 flex items-center justify-center shadow-[0_0_15px_rgba(34,211,238,0.4)] overflow-hidden">
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_16%_50%,rgba(102,246,255,0.5),rgba(102,246,255,0)_62%)]"></div>
                    <svg class="relative h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 3.8 6.7 6.1v4.3c0 3.1 2.1 5.9 5.3 6.9 3.2-1 5.3-3.8 5.3-6.9V6.1L12 3.8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h1 class="text-xl font-bold tracking-wide text-white uppercase font-['Outfit']">Job<span class="text-cyan-400">Guard</span></h1>
            </div>

            @if (Route::has('login'))
                <nav class="flex items-center gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="text-sm font-medium text-slate-300 hover:text-white transition-colors">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-slate-300 hover:text-white transition-colors">Sign In</a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="text-sm font-medium px-5 py-2 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 text-white transition-all">Sign Up</a>
                        @endif
                    @endauth
                </nav>
            @endif
        </div>
    </header>

    <!-- Main Hero Section -->
    <main class="flex-1 flex flex-col items-center justify-center z-10 relative px-6 py-12 md:py-24">
        <div class="max-w-4xl w-full text-center">
            
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-sm font-semibold uppercase tracking-wider mb-8">
                <span class="relative flex h-2 w-2">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-2 w-2 bg-cyan-500"></span>
                </span>
                Powered by Advanced NLP
            </div>

            <h1 class="text-5xl md:text-7xl font-black text-white mb-6 tracking-tight font-['Outfit'] leading-tight">
                Online Fake Job Posting <br class="hidden md:block">
                <span class="text-gradient">Detection System</span>
            </h1>
            
            <p class="text-lg md:text-xl text-slate-400 mb-12 max-w-2xl mx-auto leading-relaxed">
                Deploy advanced neural networks to analyze job postings and detect fraudulent hiring campaigns in real time. Protect yourself from employment scams before they happen.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                @auth
                    <a href="{{ url('/dashboard') }}" class="w-full sm:w-auto bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white font-bold py-4 px-8 rounded-xl transition-all shadow-[0_0_20px_rgba(6,182,212,0.4)] hover:shadow-[0_0_30px_rgba(6,182,212,0.6)] flex items-center justify-center gap-3">
                        <span>Go to Dashboard</span> <i class="fas fa-arrow-right"></i>
                    </a>
                @else
                    <a href="{{ route('register') }}" class="w-full sm:w-auto bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white font-bold py-4 px-8 rounded-xl transition-all shadow-[0_0_20px_rgba(6,182,212,0.4)] hover:shadow-[0_0_30px_rgba(6,182,212,0.6)] flex items-center justify-center gap-3">
                        <span>Get Started</span> <i class="fas fa-user-plus"></i>
                    </a>
                    <a href="{{ route('login') }}" class="w-full sm:w-auto bg-slate-800/50 hover:bg-slate-700/50 border border-white/10 text-white font-semibold py-4 px-8 rounded-xl transition-all flex items-center justify-center">
                        Sign In 
                    </a>
                @endauth
            </div>
        </div>

        <!-- Features Grid -->
        <div class="max-w-5xl w-full grid grid-cols-1 md:grid-cols-3 gap-6 mt-24">
            <div class="bg-white/5 border border-white/10 p-6 rounded-2xl backdrop-blur-sm">
                <div class="h-12 w-12 rounded-lg bg-blue-500/20 text-blue-400 flex items-center justify-center mb-4 text-xl">
                    <i class="fas fa-brain"></i>
                </div>
                <h3 class="text-white font-bold text-lg mb-2">Machine Learning</h3>
                <p class="text-slate-400 text-sm leading-relaxed">Our AI analyzes linguistic patterns and semantic structures to identify suspicious phrasing commonly used in recruitment fraud.</p>
            </div>
            <div class="bg-white/5 border border-white/10 p-6 rounded-2xl backdrop-blur-sm">
                <div class="h-12 w-12 rounded-lg bg-cyan-500/20 text-cyan-400 flex items-center justify-center mb-4 text-xl">
                    <i class="fas fa-bolt"></i>
                </div>
                <h3 class="text-white font-bold text-lg mb-2">Real-time Scanning</h3>
                <p class="text-slate-400 text-sm leading-relaxed">Paste any job URL and get instantaneous threat assessments with a detailed breakdown of red flags and safe indicators.</p>
            </div>
            <div class="bg-white/5 border border-white/10 p-6 rounded-2xl backdrop-blur-sm">
                <div class="h-12 w-12 rounded-lg bg-purple-500/20 text-purple-400 flex items-center justify-center mb-4 text-xl">
                    <i class="fas fa-shield-virus"></i>
                </div>
                <h3 class="text-white font-bold text-lg mb-2">Proactive Defense</h3>
                <p class="text-slate-400 text-sm leading-relaxed">Stay steps ahead of scammers targeting vulnerable job seekers through continuous model improvements.</p>
            </div>
        </div>
    </main>

</body>
</html>

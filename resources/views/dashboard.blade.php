@extends('layouts.app')

@section('content')
@php
    $score        = (int)($result['score'] ?? 0);
    $statusRaw    = strtolower((string)($result['status'] ?? 'safe'));
    $isHigh       = str_contains($statusRaw, 'high');
    $isMedium     = str_contains($statusRaw, 'medium') || str_contains($statusRaw, 'suspicious');
    $isSafe       = !$isHigh && !$isMedium;

    $badgeClass   = $isHigh
        ? 'bg-rose-500/20 text-rose-300 border-rose-500/40'
        : ($isMedium ? 'bg-amber-500/20 text-amber-300 border-amber-500/40'
                     : 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40');

    $badgeLabel   = $isHigh ? 'High Risk' : ($isMedium ? 'Medium Risk' : 'Safe');
    $gaugeColor   = $isHigh ? '#f43f5e' : ($isMedium ? '#f59e0b' : '#10b981');

    // New enriched data from updated app.py
    $urlAnalysis     = $result['url_analysis']     ?? [];
    $contentAnalysis = $result['content_analysis'] ?? [];
    $company         = $result['company']          ?? [];
    $redFlags        = $result['red_flags']        ?? [];
    $greenSignals    = $result['green_signals']    ?? [];
    $aiKeywords      = $result['keywords']         ?? [];
@endphp

<style>
    @keyframes fadeUp {
        from { opacity:0; transform:translateY(16px); }
        to   { opacity:1; transform:translateY(0);    }
    }
    @keyframes gaugeArc {
        from { stroke-dashoffset: 314; }
        to   { stroke-dashoffset: var(--target-offset); }
    }
    .fade-up          { animation: fadeUp .45s ease both; }
    .fade-up-1        { animation-delay: .05s; }
    .fade-up-2        { animation-delay: .12s; }
    .fade-up-3        { animation-delay: .19s; }
    .fade-up-4        { animation-delay: .26s; }
    .fade-up-5        { animation-delay: .33s; }
    .fade-up-6        { animation-delay: .40s; }
    .card             { @apply rounded-2xl bg-white/5 border border-white/10 backdrop-blur-xl shadow-2xl; }
    .card-hover       { @apply hover:bg-white/[0.07] transition duration-300; }
    .section-label    { @apply text-xs uppercase tracking-[0.18em] text-slate-400 font-semibold mb-3; }
    .pill-red         { @apply inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs font-medium; }
    .pill-green       { @apply inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-medium; }
    .pill-amber       { @apply inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-300 text-xs font-medium; }
    .pill-slate       { @apply inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-500/15 border border-slate-500/30 text-slate-300 text-xs font-medium; }
    .kv-row           { @apply flex items-center justify-between gap-4 py-2.5 border-b border-white/5 last:border-0; }
    .kv-label         { @apply text-sm text-slate-400; }
    .kv-val           { @apply text-sm font-semibold text-slate-100 text-right; }
</style>

<div class="max-w-7xl mx-auto space-y-6 pb-12">

    {{-- ── HEADER ──────────────────────────────────────────────────────────── --}}
    <section class="card p-6 sm:p-8 fade-up fade-up-1">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-3xl sm:text-4xl font-['Outfit'] font-bold text-white leading-tight">
                    Online Fake Job Posting <span class="text-cyan-400">Detection</span>
                </h1>
                <p class="mt-2 text-slate-400 max-w-2xl text-sm">
                    Detect fraudulent job listings using AI-driven risk analysis, domain checks, content NLP, and scam pattern detection.
                </p>
            </div>
        </div>
    </section>

    {{-- ── FORM ─────────────────────────────────────────────────────────────── --}}
    <section class="card p-5 sm:p-6 fade-up fade-up-2">
        <form id="analyzeForm" method="POST" action="{{ route('dashboard.analyze') }}" class="flex flex-col sm:flex-row gap-3">
            @csrf
            <div class="relative flex-1">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M13.828 10.172a4 4 0 0 0-5.656 0l-4 4a4 4 0 1 0 5.656 5.656l1.102-1.102"/>
                        <path d="M10.172 13.828a4 4 0 0 0 5.656 0l4-4a4 4 0 0 0-5.656-5.656L13.07 5.27"/>
                    </svg>
                </span>
                <input
                    id="job_url" name="url" type="url" required
                    value="{{ old('url', $result['url'] ?? '') }}"
                    placeholder="Paste job posting URL here…"
                    class="w-full rounded-xl bg-white/5 border border-white/15 text-white placeholder:text-slate-500 pl-10 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400/60 focus:border-cyan-400 transition"
                >
            </div>
            <button id="analyzeBtn" type="submit"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-cyan-500 to-blue-600 hover:from-cyan-400 hover:to-blue-500 text-white font-semibold px-6 py-3 text-sm shadow-[0_0_24px_rgba(6,182,212,0.35)] hover:shadow-[0_0_32px_rgba(6,182,212,0.55)] transition duration-300 whitespace-nowrap">
                <svg id="analyzeSpinner" class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity="0.25" stroke-width="4"/>
                    <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                </svg>
                <span id="analyzeBtnText">Analyze Target</span>
            </button>
        </form>
    </section>

    @if(isset($result))

    {{-- ── ROW 1 — SCORE + EVIDENCE ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 fade-up fade-up-3">

        {{-- Gauge card --}}
        <div class="card p-6 flex flex-col items-center justify-center text-center">
            <p class="section-label w-full text-center">Threat Assessment</p>

            {{-- SVG Gauge --}}
            <div class="relative w-40 h-40 my-2">
                <svg viewBox="0 0 120 120" class="w-full h-full -rotate-90">
                    <circle cx="60" cy="60" r="50" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="10"/>
                    <circle
                        id="gaugeArc"
                        cx="60" cy="60" r="50"
                        fill="none"
                        stroke="{{ $gaugeColor }}"
                        stroke-width="10"
                        stroke-linecap="round"
                        stroke-dasharray="314"
                        stroke-dashoffset="314"
                        style="transition: stroke-dashoffset 1.2s cubic-bezier(.4,0,.2,1); filter: drop-shadow(0 0 6px {{ $gaugeColor }});"
                    />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-4xl font-['Outfit'] font-black text-white">{{ $score }}</span>
                    <span class="text-xs text-slate-400 mt-0.5">/ 100</span>
                </div>
            </div>

            <span class="mt-2 inline-flex items-center px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-widest border {{ $badgeClass }}">
                {{ $badgeLabel }}
            </span>

            @if(isset($result['ai_score']))
            <div class="mt-4 w-full space-y-1.5 text-xs text-slate-400">
                <div class="flex justify-between">
                    <span>AI Model Score</span>
                    <span class="text-slate-200 font-semibold">{{ $result['ai_score'] }}%</span>
                </div>
                <div class="flex justify-between">
                    <span>Rule-Based Score</span>
                    <span class="text-slate-200 font-semibold">{{ round(($score - $result['ai_score'] * 0.6) / 0.4) }}%</span>
                </div>
            </div>
            @endif
        </div>

        {{-- Red Flags + Green Signals --}}
        <div class="lg:col-span-2 card p-6">
            <h2 class="text-lg font-['Outfit'] font-semibold text-white mb-4">Evidence Summary</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                {{-- Red Flags --}}
                <div>
                    <p class="section-label text-rose-400/80">🚨 Red Flags</p>
                    @if(count($redFlags) > 0)
                        <ul class="space-y-2">
                            @foreach($redFlags as $flag)
                                <li class="flex items-start gap-2 text-sm text-slate-200">
                                    <span class="mt-0.5 flex-shrink-0 w-4 h-4 rounded-full bg-rose-500/20 border border-rose-500/40 flex items-center justify-center">
                                        <svg class="w-2.5 h-2.5 text-rose-400" fill="currentColor" viewBox="0 0 20 20"><path d="M10 3a7 7 0 1 0 0 14A7 7 0 0 0 10 3Zm-.75 3.75a.75.75 0 0 1 1.5 0v4a.75.75 0 0 1-1.5 0v-4Zm.75 7a1 1 0 1 1 0-2 1 1 0 0 1 0 2Z"/></svg>
                                    </span>
                                    {{ $flag }}
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-slate-500 italic">No red flags detected.</p>
                    @endif
                </div>

                {{-- Green Signals --}}
                <div>
                    <p class="section-label text-emerald-400/80">✅ Trust Signals</p>
                    @if(count($greenSignals) > 0)
                        <ul class="space-y-2">
                            @foreach($greenSignals as $signal)
                                <li class="flex items-start gap-2 text-sm text-slate-200">
                                    <span class="mt-0.5 flex-shrink-0 w-4 h-4 rounded-full bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center">
                                        <svg class="w-2.5 h-2.5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                                    </span>
                                    {{ $signal }}
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-slate-500 italic">No trust signals found.</p>
                    @endif
                </div>

            </div>

            {{-- AI Explanation --}}
            @if(isset($result['explain']))
            <div class="mt-5 rounded-xl bg-cyan-500/5 border border-cyan-500/20 px-4 py-3 flex gap-3 items-start">
                <svg class="w-4 h-4 text-cyan-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path d="M9.663 17h4.673M12 3v1m6.364 1.636-.707.707M21 12h-1M4 12H3m3.343-5.657-.707-.707m2.828 9.9a5 5 0 1 1 7.072 0l-.548.547A3.374 3.374 0 0 0 14 18.469V19a2 2 0 1 1-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547Z"/>
                </svg>
                <p class="text-sm text-slate-300">{{ $result['explain'] }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- ── ROW 2 — DETAIL CARDS ─────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6 fade-up fade-up-4">

        {{-- URL & Domain --}}
        <div class="card card-hover p-6">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M13.828 10.172a4 4 0 0 0-5.656 0l-4 4a4 4 0 1 0 5.656 5.656l1.102-1.102"/>
                        <path d="M10.172 13.828a4 4 0 0 0 5.656 0l4-4a4 4 0 0 0-5.656-5.656L13.07 5.27"/>
                    </svg>
                </div>
                <h3 class="font-['Outfit'] font-semibold text-white text-base">URL & Domain</h3>
            </div>
            <dl class="space-y-0">
                <div class="kv-row">
                    <dt class="kv-label">SSL Certificate</dt>
                    <dd class="kv-val">
                        @if($urlAnalysis['ssl_valid'] ?? false)
                            <span class="pill-green">✓ Valid</span>
                        @else
                            <span class="pill-red">✗ Invalid</span>
                        @endif
                    </dd>
                </div>
                <div class="kv-row">
                    <dt class="kv-label">Domain Age</dt>
                    <dd class="kv-val">
                        @php $age = $urlAnalysis['domain_age_days'] ?? null; @endphp
                        @if($age !== null)
                            <span class="{{ $age < 180 ? 'pill-red' : 'pill-green' }}">
                                {{ $age >= 365 ? round($age/365, 1).' yrs' : $age.' days' }}
                            </span>
                        @else
                            <span class="pill-amber">Unknown</span>
                        @endif
                    </dd>
                </div>
                <div class="kv-row">
                    <dt class="kv-label">Redirects</dt>
                    <dd class="kv-val">
                        @php $redirects = $urlAnalysis['redirect_count'] ?? 0; @endphp
                        <span class="{{ $redirects > 2 ? 'pill-red' : 'pill-green' }}">{{ $redirects }}</span>
                    </dd>
                </div>
                <div class="kv-row">
                    <dt class="kv-label">Known Job Board</dt>
                    <dd class="kv-val">
                        @if($urlAnalysis['known_job_board'] ?? false)
                            <span class="pill-green">✓ Yes</span>
                        @else
                            <span class="pill-amber">Unrecognised</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Content Analysis --}}
        <div class="card card-hover p-6">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-lg bg-cyan-500/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"/>
                    </svg>
                </div>
                <h3 class="font-['Outfit'] font-semibold text-white text-base">Content Analysis</h3>
            </div>
            <dl class="space-y-0">
                <div class="kv-row">
                    <dt class="kv-label">Payment Demanded</dt>
                    <dd class="kv-val">
                        @if($contentAnalysis['payment_demanded'] ?? false)
                            <span class="pill-red">⚠ Detected</span>
                        @else
                            <span class="pill-green">✓ None</span>
                        @endif
                    </dd>
                </div>
                <div class="kv-row">
                    <dt class="kv-label">Urgency Language</dt>
                    <dd class="kv-val">
                        @php $urgWords = $contentAnalysis['urgency_words'] ?? []; @endphp
                        @if(count($urgWords) > 0)
                            <span class="pill-amber">{{ implode(', ', array_slice($urgWords, 0, 3)) }}</span>
                        @else
                            <span class="pill-green">✓ None</span>
                        @endif
                    </dd>
                </div>
                <div class="kv-row">
                    <dt class="kv-label">Personal Email</dt>
                    <dd class="kv-val">
                        @if($contentAnalysis['personal_email'] ?? false)
                            <span class="pill-red">⚠ Found</span>
                        @else
                            <span class="pill-green">✓ None</span>
                        @endif
                    </dd>
                </div>
                <div class="kv-row">
                    <dt class="kv-label">Suspicious Salary</dt>
                    <dd class="kv-val">
                        @if($contentAnalysis['suspicious_salary'] ?? false)
                            <span class="pill-red">⚠ Detected</span>
                        @else
                            <span class="pill-green">✓ Normal</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Company Verification --}}
        <div class="card card-hover p-6">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-lg bg-purple-500/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                    </svg>
                </div>
                <h3 class="font-['Outfit'] font-semibold text-white text-base">Company</h3>
            </div>
            <dl class="space-y-0">
                <div class="kv-row">
                    <dt class="kv-label">Company Name</dt>
                    <dd class="kv-val text-slate-200 truncate max-w-[120px]">
                        {{ $company['name'] ?? 'Not detected' }}
                    </dd>
                </div>
                <div class="kv-row">
                    <dt class="kv-label">Verified</dt>
                    <dd class="kv-val">
                        @if($company['verified'] ?? false)
                            <span class="pill-green">✓ Verified</span>
                        @else
                            <span class="pill-red">✗ Unverified</span>
                        @endif
                    </dd>
                </div>
            </dl>

            {{-- Fraud keywords from AI --}}
            @if(count($aiKeywords) > 0)
            <div class="mt-4">
                <p class="section-label text-rose-400/70">AI Top Indicators</p>
                <div class="flex flex-wrap gap-1.5 mt-2">
                    @foreach($aiKeywords as $kw)
                        <span class="pill-slate">{{ $kw }}</span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Scam Indicators --}}
        <div class="card card-hover p-6">
            <div class="flex items-center gap-2 mb-4">
                <div class="w-8 h-8 rounded-lg bg-rose-500/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                </div>
                <h3 class="font-['Outfit'] font-semibold text-white text-base">Scam Indicators</h3>
            </div>

            @php
                $fraudKws = $contentAnalysis['fraud_keywords'] ?? [];
                $legitKws = $contentAnalysis['legit_keywords'] ?? [];
            @endphp

            <div class="mb-3">
                <p class="section-label text-rose-400/70">Fraud Keywords ({{ count($fraudKws) }})</p>
                @if(count($fraudKws) > 0)
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($fraudKws as $kw)
                            <span class="pill-red">{{ $kw }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-slate-500 italic">None detected</p>
                @endif
            </div>

            <div>
                <p class="section-label text-emerald-400/70">Legit Keywords ({{ count($legitKws) }})</p>
                @if(count($legitKws) > 0)
                    <div class="flex flex-wrap gap-1.5">
                        @foreach(array_slice($legitKws, 0, 5) as $kw)
                            <span class="pill-green">{{ $kw }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-slate-500 italic">None detected</p>
                @endif
            </div>
        </div>

    </div>

    {{-- ── ROW 3 — ENRICHED ANALYSIS SECTIONS ─────────────────────────────── --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 fade-up fade-up-5">
        <div class="xl:col-span-2 card p-6">
            <h2 class="text-lg font-['Outfit'] font-semibold text-white mb-4">Per-Category Subscores</h2>
            <div class="space-y-4">
                @php
                    $barItems = [
                        'URL Risk' => (int)($result['url_risk'] ?? 0),
                        'Content Risk' => (int)($result['content_risk'] ?? 0),
                        'Company Risk' => (int)($result['company_risk'] ?? 0),
                        'Salary Risk' => (int)($result['salary_risk'] ?? 0),
                        'Application Risk' => (int)($result['application_risk'] ?? 0),
                    ];
                @endphp

                @foreach($barItems as $label => $value)
                    @php
                        $riskColor = $value > 60 ? 'from-rose-500 to-red-500' : ($value >= 30 ? 'from-amber-500 to-yellow-500' : 'from-cyan-500 to-blue-500');
                    @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <p class="text-sm text-slate-200">{{ $label }}</p>
                            <p class="text-sm text-cyan-400 font-semibold">{{ $value }}%</p>
                        </div>
                        <div class="w-full h-2.5 rounded-full bg-gray-800 overflow-hidden">
                            <div class="h-full bg-gradient-to-r {{ $riskColor }}" style="width: {{ max(0, min(100, $value)) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card p-6 flex flex-col items-center justify-center text-center">
            <h2 class="text-lg font-['Outfit'] font-semibold text-white mb-5">Confidence Level</h2>
            @php
                $confidencePct = (int)($result['confidence_pct'] ?? 0);
                $confidenceLabel = $confidencePct >= 75 ? 'HIGH' : ($confidencePct >= 45 ? 'MEDIUM' : 'LOW');
                $ringColor = $confidencePct >= 75 ? '#22c55e' : ($confidencePct >= 45 ? '#f59e0b' : '#ef4444');
            @endphp
            <div class="relative w-36 h-36 rounded-full flex items-center justify-center"
                 style="background: conic-gradient({{ $ringColor }} {{ max(0, min(100, $confidencePct)) }}%, #1f2937 0);">
                <div class="w-28 h-28 rounded-full bg-gray-900 border border-white/10 flex flex-col items-center justify-center">
                    <p class="text-2xl font-black text-white">{{ $confidencePct }}%</p>
                    <p class="text-xs tracking-[0.2em] text-cyan-400 mt-1">{{ $confidenceLabel }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="fade-up fade-up-6">
        @php
            $verdict = strtoupper((string)($result['verdict'] ?? 'SAFE'));
            $recommendedAction = (string)($result['recommended_action'] ?? 'Analysis complete.');
            $actionClass = $verdict === 'FRAUD'
                ? 'border-red-500/40 bg-red-500/10 text-red-200'
                : ($verdict === 'CAUTION'
                    ? 'border-yellow-500/40 bg-yellow-500/10 text-yellow-200'
                    : 'border-green-500/40 bg-green-500/10 text-green-200');
            $actionTitle = $verdict === 'FRAUD' ? 'Recommended Action: FRAUD' : ($verdict === 'CAUTION' ? 'Recommended Action: CAUTION' : 'Recommended Action: SAFE');
        @endphp
        <div class="rounded-2xl border {{ $actionClass }} p-5">
            <p class="text-sm uppercase tracking-[0.16em] text-cyan-400 font-semibold mb-2">{{ $actionTitle }}</p>
            <p class="text-sm leading-relaxed">{{ $recommendedAction }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 fade-up fade-up-6">
        <div class="card p-6">
            <h2 class="text-lg font-['Outfit'] font-semibold text-white mb-4">Domain Intelligence</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-xl bg-gray-900/80 border border-white/10 p-4">
                    <p class="text-xs uppercase tracking-[0.16em] text-cyan-400 mb-1">Domain Age</p>
                    <p class="text-sm text-slate-100">{{ $result['domain_age_years'] ?? 'Unknown' }} years</p>
                </div>
                <div class="rounded-xl bg-gray-900/80 border border-white/10 p-4">
                    <p class="text-xs uppercase tracking-[0.16em] text-cyan-400 mb-1">SSL Status</p>
                    <p class="text-sm text-slate-100">{{ ($result['ssl_valid'] ?? false) ? 'Valid' : 'Invalid' }} ({{ $result['ssl_issuer'] ?? 'Unknown' }})</p>
                </div>
                <div class="rounded-xl bg-gray-900/80 border border-white/10 p-4">
                    <p class="text-xs uppercase tracking-[0.16em] text-cyan-400 mb-1">WHOIS Registrant</p>
                    <p class="text-sm text-slate-100">{{ $result['whois_registrant'] ?? 'Unknown' }}</p>
                </div>
                <div class="rounded-xl bg-gray-900/80 border border-white/10 p-4">
                    <p class="text-xs uppercase tracking-[0.16em] text-cyan-400 mb-1">Redirect Count</p>
                    <p class="text-sm text-slate-100">{{ (int)($result['redirect_count'] ?? 0) }}</p>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <h2 class="text-lg font-['Outfit'] font-semibold text-white mb-4">Flagged Indicators</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <p class="text-xs uppercase tracking-[0.16em] text-green-400 mb-2">Authentic Signals</p>
                    @php $authSignals = $result['authentic_signals'] ?? []; @endphp
                    @if(count($authSignals) > 0)
                        <ul class="space-y-2">
                            @foreach($authSignals as $signal)
                                <li class="text-sm text-green-300 flex items-start gap-2"><span class="text-green-400">•</span><span>{{ $signal }}</span></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-slate-500 italic">No authentic signals found.</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs uppercase tracking-[0.16em] text-amber-400 mb-2">Warning Signals</p>
                    @php $warnSignals = $result['warning_signals'] ?? []; @endphp
                    @if(count($warnSignals) > 0)
                        <ul class="space-y-2">
                            @foreach($warnSignals as $signal)
                                <li class="text-sm text-amber-300 flex items-start gap-2"><span class="text-amber-400">•</span><span>{{ $signal }}</span></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-slate-500 italic">No warning signals found.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card p-6 fade-up fade-up-6">
        <h2 class="text-lg font-['Outfit'] font-semibold text-white mb-4">Job Content Stats</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody>
                    <tr class="border-b border-white/10">
                        <td class="py-3 text-slate-400">Description Word Count</td>
                        <td class="py-3 text-right text-slate-100">{{ (int)($result['desc_word_count'] ?? 0) }}</td>
                    </tr>
                    <tr class="border-b border-white/10">
                        <td class="py-3 text-slate-400">Grammar Score</td>
                        <td class="py-3 text-right text-slate-100">{{ (int)($result['grammar_score'] ?? 0) }} / 100</td>
                    </tr>
                    <tr class="border-b border-white/10">
                        <td class="py-3 text-slate-400">Contact Type</td>
                        <td class="py-3 text-right text-slate-100">{{ $result['contact_type'] ?? 'unknown' }}</td>
                    </tr>
                    <tr>
                        <td class="py-3 text-slate-400">Scam Phrases Matched</td>
                        <td class="py-3 text-right text-slate-100">{{ count($result['scam_phrases_matched'] ?? []) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── ROW 4 — FINAL VERDICT BANNER ────────────────────────────────────── --}}
    <div class="fade-up fade-up-6">
        @if($isHigh)
        <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 backdrop-blur-xl p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-rose-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-rose-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
            </div>
            <div>
                <p class="text-rose-300 font-bold font-['Outfit'] text-base">⛔ High Risk — Likely Fraudulent</p>
                <p class="text-rose-200/70 text-sm mt-1">This job posting shows multiple strong indicators of a scam. Do not submit personal information, pay any fees, or respond to this posting.</p>
            </div>
        </div>
        @elseif($isMedium)
        <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 backdrop-blur-xl p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-amber-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
            </div>
            <div>
                <p class="text-amber-300 font-bold font-['Outfit'] text-base">⚠️ Medium Risk — Proceed with Caution</p>
                <p class="text-amber-200/70 text-sm mt-1">Some suspicious signals were detected. Verify the company independently, never pay any fees, and do not share sensitive personal details before confirming legitimacy.</p>
            </div>
        </div>
        @else
        <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 backdrop-blur-xl p-5 flex items-start gap-4">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
            </div>
            <div>
                <p class="text-emerald-300 font-bold font-['Outfit'] text-base">✅ Safe — Appears Legitimate</p>
                <p class="text-emerald-200/70 text-sm mt-1">No major fraud signals were detected. However, always exercise caution — verify the company directly before sharing personal documents or attending interviews.</p>
            </div>
        </div>
        @endif
    </div>

    @endif {{-- end isset($result) --}}

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Form loading state
    const form    = document.getElementById('analyzeForm');
    const btn     = document.getElementById('analyzeBtn');
    const spinner = document.getElementById('analyzeSpinner');
    const txt     = document.getElementById('analyzeBtnText');
    if (form) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            spinner.classList.remove('hidden');
            txt.textContent = 'Analyzing…';
        });
    }

    // Animate gauge arc
    const arc   = document.getElementById('gaugeArc');
    const score = {{ $score ?? 0 }};
    if (arc) {
        // 314 = full circumference (2π×50). offset = 314 - (score/100 × 314)
        const offset = 314 - (score / 100) * 314;
        setTimeout(() => { arc.style.strokeDashoffset = offset; }, 200);
    }
});
</script>
@endsection
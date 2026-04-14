@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'mt-1 w-full rounded-lg border border-white/10 bg-slate-900/60 text-slate-100 placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400/60 shadow-sm']) }}>

@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-cyan-300 bg-cyan-500/10 border border-cyan-500/20 rounded-lg px-3 py-2']) }}>
        {{ $status }}
    </div>
@endif

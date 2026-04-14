<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-5 py-2.5 bg-gradient-to-r from-cyan-500 to-blue-600 border border-transparent rounded-lg font-semibold text-sm text-white tracking-wide hover:from-cyan-400 hover:to-blue-500 focus:outline-none focus:ring-2 focus:ring-cyan-400/80 focus:ring-offset-0 transition ease-in-out duration-150 shadow-[0_0_20px_rgba(6,182,212,0.35)]']) }}>
    {{ $slot }}
</button>

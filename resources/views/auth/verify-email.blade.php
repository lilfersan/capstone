<x-guest-layout>
    <div class="mb-2">
        <h2 class="text-2xl font-['Outfit'] font-bold text-white">Verify your email</h2>
    </div>

    <div class="mb-4 text-sm text-slate-400">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-emerald-300 bg-emerald-500/10 border border-emerald-500/20 rounded-lg px-3 py-2">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="auth-link rounded-md focus:outline-none focus:ring-2 focus:ring-cyan-400/70">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>

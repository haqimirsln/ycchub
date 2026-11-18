<x-layouts.auth title="Waiting for approval" :heading="$user->account_status === 'rejected' ? 'Sign-up not approved' : 'Thanks, '.$user->firstName().'!'">
    @if ($user->account_status === 'rejected')
        <p class="mt-3 text-ink-soft">An MT member reviewed your sign-up and couldn't approve it.</p>
        @if ($user->review_note)
            <div class="mt-5 rounded-lg border border-st-inactive/25 bg-white p-4">
                <p class="text-sm font-bold text-ink">Note from the MT</p>
                <p class="mt-1 text-ink-soft">{{ $user->review_note }}</p>
            </div>
        @endif
        <p class="mt-5 text-ink-soft">If this looks wrong, message the club on Instagram and include your matric number.</p>
    @else
        <p class="mt-3 text-ink-soft">Your sign-up is with the MT. Once it's approved you can register for events, and we'll email {{ $user->email }} to let you know.</p>

        <div class="panel mt-6 p-5">
            <dl class="grid grid-cols-[8rem_1fr] gap-y-2 text-sm">
                <dt class="text-ink-soft">Matric number</dt><dd class="font-bold">{{ $user->matric_no }}</dd>
                <dt class="text-ink-soft">Phone</dt><dd>{{ $user->phone_number }}</dd>
                <dt class="text-ink-soft">Signed up</dt><dd>{{ $user->created_at->format('j M Y, g:i A') }}</dd>
                <dt class="text-ink-soft">Status</dt><dd><x-status-badge status="pending" /></dd>
            </dl>
        </div>
        <p class="mt-4 text-sm text-ink-soft">Details wrong? Log out and ask the MT to remove the sign-up, then register again.</p>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-8">
        @csrf
        <button type="submit" class="btn-logout w-full">Log out</button>
    </form>
</x-layouts.auth>

<x-layouts.auth title="Club emails off" :heading="'Done, '.$user->firstName()">
    <p class="mt-3 text-ink-soft">You won't get club news and promotions by email any more. Account emails, like password resets, still come through.</p>
    <p class="mt-3 text-ink-soft">Changed your mind? Turn them back on any time under <span class="font-bold text-ink">My profile</span>.</p>
    <a href="{{ route('profile.edit') }}#emails" class="btn-dark mt-6 w-full">Open my profile</a>
</x-layouts.auth>

<x-layouts.app title="Roles & access">
    <x-page-header title="Roles & access" :subtitle="($roleCounts['superadmin'] ?? 0).' superadmin, '.($roleCounts['admin'] ?? 0).' MT, '.($roleCounts['member'] ?? 0).' members'.(($roleCounts['advisor'] ?? 0) ? ', '.$roleCounts['advisor'].' advisor' : '')" />
    <p class="-mt-3 mb-5 text-sm text-ink-soft"><span class="font-bold text-ink">MT (admin)</span> runs the club side: approvals, events, attendance, members, merit, analytics and settings. <span class="font-bold text-ink">Superadmin</span> can also change roles, delete accounts and events, and see the activity log. <span class="font-bold text-ink">Advisor</span> is for the Penasihat or staff: they see the MT pages and the activity log, but can't change anything, and they're left out of member counts and merit. Giving someone a <span class="font-bold text-ink">position</span> (Presiden, Setiausaha...) makes them MT and shows it on <a href="{{ route('mt.index') }}" class="font-bold text-kelp-500 hover:underline">Our MT</a>.</p>

    <form method="GET" class="toolbar mb-5">
        <div class="toolbar-grow relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-soft" />
            <input name="q" value="{{ $filters['q'] }}" class="field pl-9" placeholder="Name, matric or email" aria-label="Search">
        </div>
        <select name="role" class="field" aria-label="Role">
            <option value="">Any role</option>
            @foreach (\App\Models\User::ROLE_LABELS as $r => $label)<option value="{{ $r }}" @selected($filters['role'] === $r)>{{ $label }}</option>@endforeach
        </select>
        <select name="state" class="field" aria-label="Account">
            <option value="">Any account state</option>
            @foreach (['approved', 'pending', 'suspended', 'rejected'] as $s)<option value="{{ $s }}" @selected($filters['state'] === $s)>{{ ucfirst($s) }}</option>@endforeach
        </select>
        <button class="btn-line w-full sm:w-auto">Filter</button>
    </form>

    <div class="panel relative overflow-x-auto">
        <table class="table-clean min-w-[56rem]">
            <thead><tr><th>Account</th><th>Account state</th><th>Role</th>@if ($mtReady)<th>Position {{ $session }}</th>@endif<th class="text-right">Access</th></tr></thead>
            <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <x-avatar :user="$u" />
                                <div class="min-w-0"><span class="font-bold">{{ $u->full_name }}</span><span class="block text-xs text-ink-soft">{{ $u->matric_no }} &middot; {{ $u->email }}</span></div>
                            </div>
                        </td>
                        <td><x-status-badge :status="$u->account_status" /></td>
                        <td>
                            @if ($u->is(auth()->user()))
                                <span class="text-sm font-bold">{{ $u->titleLabel() }} (you)</span>
                            @else
                                <form method="POST" action="{{ route('admin.users.role', $u) }}" class="flex items-center gap-2">
                                    @csrf @method('PUT')
                                    <select name="role" class="field field-sm w-40" aria-label="Role for {{ $u->full_name }}" onchange="this.form.submit()" @disabled(! $u->isApproved())>
                                        @foreach (\App\Models\User::ROLE_LABELS as $r => $label)<option value="{{ $r }}" @selected($u->role === $r)>{{ $label }}</option>@endforeach
                                    </select>
                                </form>
                            @endif
                        </td>
                        @if ($mtReady)
                            <td>
                                @if ($u->isAdvisor())
                                    <span class="text-sm text-ink-soft">Penasihat / staff</span>
                                @elseif ($u->isApproved())
                                    <form method="POST" action="{{ route('admin.users.position', $u) }}">
                                        @csrf @method('PUT')
                                        <select name="position" class="field field-sm w-48" aria-label="Position for {{ $u->full_name }}" onchange="this.form.submit()">
                                            <option value="">No position</option>
                                            @foreach (config('ycc.positions') as $key => $pos)
                                                @php $holder = $holders[$key] ?? null; @endphp
                                                <option value="{{ $key }}" @selected($holder?->user_id === $u->id)>{{ $pos['label'] }}{{ $holder && $holder->user_id !== $u->id ? ' (now '.\Illuminate\Support\Str::of($holder->name())->words(2, '').')' : '' }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @else
                                    <span class="text-sm text-ink-soft">Approve first</span>
                                @endif
                            </td>
                        @endif
                        <td class="text-right">
                            @unless ($u->is(auth()->user()) || $u->isSuperadmin())
                                <div class="flex justify-end gap-1.5">
                                    @if ($u->account_status === 'suspended')
                                        <form method="POST" action="{{ route('admin.users.reinstate', $u) }}">@csrf <button class="btn-line btn-sm">Reinstate</button></form>
                                    @elseif ($u->account_status === 'approved')
                                        <form method="POST" action="{{ route('admin.users.suspend', $u) }}" onsubmit="return confirm('Suspend {{ addslashes($u->full_name) }}? They will be logged out and can\'t log in.')">@csrf <button class="btn-quiet-danger btn-sm">Suspend</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.users.destroy', $u) }}" onsubmit="return confirm('Delete {{ addslashes($u->full_name) }} for good? Their attendance, merit and feedback are removed too. This can\'t be undone.')">@csrf @method('DELETE') <button class="btn-quiet-danger btn-sm" aria-label="Delete {{ $u->full_name }}"><x-icon name="trash" class="size-4" /></button></form>
                                </div>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty icon="search" title="No accounts match" bare>Try a different name, role or account state.</x-empty></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</x-layouts.app>

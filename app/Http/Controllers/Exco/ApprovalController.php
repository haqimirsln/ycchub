<?php

namespace App\Http\Controllers\Exco;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountReviewed;
use App\Services\ActivityStatusService;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class ApprovalController extends Controller
{
    public function __construct(private ActivityStatusService $activity) {}

    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'rejected' ? 'rejected' : 'pending';

        $users = User::query()
            ->where('account_status', $tab)
            ->orderBy($tab === 'pending' ? 'created_at' : 'reviewed_at', $tab === 'pending' ? 'asc' : 'desc')
            ->paginate(30)
            ->withQueryString();

        return view('exco.approvals', [
            'users' => $users,
            'tab' => $tab,
            'pendingCount' => User::query()->pending()->count(),
        ]);
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        if (! in_array($user->account_status, ['pending', 'rejected'], true)) {
            return back()->with('error', 'This account is not waiting for approval.');
        }

        $this->review($user, true, $request->user());

        return back()->with('success', "{$user->full_name} approved.");
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        if ($user->account_status !== 'pending') {
            return back()->with('error', 'This account is not waiting for approval.');
        }

        $this->review($user, false, $request->user(), $data['note'] ?? null);

        return back()->with('success', "{$user->full_name} rejected.");
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'Select at least one applicant.']);

        $users = User::query()->pending()->whereIn('id', $data['ids'])->get();
        foreach ($users as $user) {
            $this->review($user, true, $request->user());
        }

        return back()->with('success', $users->count().' member(s) approved.');
    }

    private function review(User $user, bool $approve, User $by, ?string $note = null): void
    {
        $user->forceFill([
            'account_status' => $approve ? 'approved' : 'rejected',
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
            'review_note' => $note,
            'activity_status' => 'new',
        ])->save();

        if ($approve) {
            $position = app(\App\Services\MtService::class)->linkOnApproval($user);
            $this->activity->refreshUser($user->fresh());
            if ($position) {
                Activity::log('member.role', "{$user->full_name} joined the MT as {$position} (on the MT list)", $user, $by->id);
            }
        }
        Activity::log($approve ? 'member.approved' : 'member.rejected', ($approve ? 'Approved ' : 'Rejected ')."{$user->full_name} ({$user->matric_no})".($note ? ": {$note}" : ''), $user, $by->id);

        try {
            $user->notify(new AccountReviewed($approve, $note));
        } catch (Throwable $e) {
            // Mail problems should never block an approval.
            Log::warning('Approval email failed', ['user' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}

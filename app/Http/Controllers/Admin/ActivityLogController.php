<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\DatabaseStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Who did what: sign-ups, approvals, events, merit and settings. Superadmin only. */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $group = array_key_exists((string) $request->query('group'), ActivityLog::GROUPS) ? (string) $request->query('group') : '';
        $q = trim((string) $request->query('q', ''));
        $ready = DatabaseStatus::hasTable('activity_logs');

        $logs = $ready
            ? ActivityLog::query()
                ->with('user:id,full_name,matric_no,role,account_status')
                ->when($group !== '', fn ($w) => $w->whereIn('action', ActivityLog::GROUPS[$group][1]))
                ->when($q !== '', function ($w) use ($q) {
                    $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                    $w->where(fn ($x) => $x->where('description', 'like', $like)
                        ->orWhereHas('user', fn ($u) => $u->where('full_name', 'like', $like)->orWhere('matric_no', 'like', $like)));
                })
                ->orderByDesc('created_at')->orderByDesc('id')
                ->paginate(40)
                ->withQueryString()
            : null;

        return view('admin.activity', [
            'logs' => $logs,
            'filters' => compact('group', 'q'),
            'ready' => $ready,
        ]);
    }
}

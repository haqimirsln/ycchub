<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Advisors (the Penasihat, staff) can look at the MT side of YCC Hub but not
 * change anything. Only these pages open for them; every other MT page and
 * every form or button that saves something is refused.
 */
class AdvisorReadOnly
{
    public const PAGES = [
        'exco.dashboard', 'exco.members.index', 'exco.members.show', 'exco.members.export',
        'exco.approvals.index', 'exco.programs.index', 'exco.attendance.show', 'exco.attendance.export', 'exco.programs.report',
        'exco.announcements.index', 'admin.analytics', 'admin.activity',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->isAdvisor()) {
            $allowed = $request->isMethodSafe() && in_array($request->route()?->getName(), self::PAGES, true);
            abort_unless($allowed, 403, 'Advisors have view-only access to YCC Hub.');
        }

        return $next($request);
    }
}

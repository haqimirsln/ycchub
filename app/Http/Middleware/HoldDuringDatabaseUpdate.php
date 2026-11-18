<?php

namespace App\Http\Middleware;

use App\Support\DatabaseStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Right after new files are uploaded, the code can expect database changes that
 * haven't been applied yet. Until someone presses "Update database", the MT is
 * sent to Settings and everyone else sees a short "updating" page instead of
 * an error.
 */
class HoldDuringDatabaseUpdate
{
    /** Pages that keep working while the database waits for its update. */
    /** Tests switch this on to check the hold itself; it is off for the rest of the suite. */
    public static bool $enabledInTests = false;

    private const ALLOWED_ROUTES = ['login', 'logout', 'admin.settings.edit', 'admin.settings.database', 'password.request', 'password.email', 'password.reset', 'password.update', 'pwa.manifest', 'pwa.offline', 'install.show', 'install.run'];

    public function handle(Request $request, Closure $next): Response
    {
        if ((app()->runningUnitTests() && ! self::$enabledInTests) || ! DatabaseStatus::pending()) {
            return $next($request);
        }

        $name = $request->route()?->getName();
        if (in_array($name, self::ALLOWED_ROUTES, true) || ($request->is('login') && $request->isMethod('post'))) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && in_array($user->role, ['admin', 'superadmin'], true) && $user->account_status === 'approved') {
            return redirect()->route('admin.settings.edit')
                ->with('error', 'A new update was uploaded. Press "Update database" to finish it. Members can use YCC Hub again right after.');
        }

        return response()->view('errors.updating', [], 503)->header('Retry-After', '120');
    }
}

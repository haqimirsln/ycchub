<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Pending/rejected accounts only see the status page; suspended accounts are signed out. */
class EnsureAccountApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->account_status === 'suspended') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'login' => 'Your account has been suspended. Please contact the YCC exco.',
            ]);
        }

        if ($user->account_status !== 'approved') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account is not approved yet.'], 403);
            }

            return redirect()->route('account.pending');
        }

        return $next($request);
    }
}

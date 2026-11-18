<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    /** Where to land after login, based on role and approval state. */
    public function redirect(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isApproved()) {
            return redirect()->route('account.pending');
        }

        return $user->isExco()
            ? redirect()->route('exco.dashboard')
            : redirect()->route('portal');
    }

    public function pending(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->isApproved()) {
            return redirect()->route('home.redirect');
        }

        return view('account.pending', ['user' => $user]);
    }
}

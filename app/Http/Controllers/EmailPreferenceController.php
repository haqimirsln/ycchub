<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\DatabaseStatus;
use Illuminate\View\View;

/** The "turn off club news" link at the bottom of every club email. Works without logging in. */
class EmailPreferenceController extends Controller
{
    public function unsubscribe(User $user): View
    {
        if (DatabaseStatus::hasColumn('users', 'email_opt_out') && ! $user->email_opt_out) {
            $user->forceFill(['email_opt_out' => true])->save();
        }

        return view('account.unsubscribed', ['user' => $user]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\MeritEntry;
use App\Services\MeritService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A member's own merit and demerit history. */
class MeritPageController extends Controller
{
    public function index(Request $request, MeritService $merit): View
    {
        $user = $request->user();

        return view('merit.index', [
            'user' => $user,
            'summary' => $merit->summary($user),
            'entries' => $merit->ready()
                ? MeritEntry::query()->where('user_id', $user->id)->with('program:id,title')->latest('created_at')->latest('id')->paginate(30)
                : collect(),
            'rules' => $merit->rules(),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityStatusService;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

/**
 * One-time web installer so the app can be set up on cPanel without SSH.
 *
 * Enabled only while INSTALL_KEY is set in .env AND no admin exists AND
 * storage/app/installed.lock is absent. Remove INSTALL_KEY after installing.
 */
class InstallController extends Controller
{
    public function show(): View
    {
        $this->guard();

        $dbError = null;
        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $dbError = $e->getMessage();
        }

        return view('install', [
            'dbError' => $dbError,
            'dbName' => config('database.connections.'.config('database.default').'.database'),
        ]);
    }

    public function run(Request $request, ActivityStatusService $activity): RedirectResponse
    {
        $this->guard();

        $request->merge([
            'matric_no' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('matric_no'))),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'install_key' => ['required', 'string'],
            'full_name' => ['required', 'string', 'max:150'],
            'matric_no' => ['required', 'string', 'regex:/^[A-Z0-9]{3,20}$/'],
            'email' => ['required', 'email', 'max:150'],
            'phone_number' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        if (! hash_equals((string) config('ycc.install_key'), $data['install_key'])) {
            return back()->withInput($request->except('password', 'password_confirmation', 'install_key'))
                ->withErrors(['install_key' => 'Install key does not match INSTALL_KEY in .env.']);
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            return back()->withInput($request->except('password', 'password_confirmation', 'install_key'))
                ->withErrors(['install_key' => 'Database migration failed: '.$e->getMessage()]);
        }

        $admin = User::query()->where('email', $data['email'])->orWhere('matric_no', $data['matric_no'])->first() ?? new User;
        $admin->fill([
            'full_name' => $data['full_name'],
            'matric_no' => $data['matric_no'],
            'email' => $data['email'],
            'phone_number' => preg_replace('/[^\d+]/', '', $data['phone_number']),
            'password' => $data['password'],
            'pdpa_consent_at' => now(),
        ]);
        $admin->forceFill([
            'role' => 'superadmin',
            'account_status' => 'approved',
            'reviewed_at' => now(),
            'activity_status' => 'new',
        ])->save();

        if ($request->boolean('seed_demo')) {
            (new DemoSeeder)->run();
        }
        $activity->refreshAll();

        @file_put_contents(storage_path('app/installed.lock'), now()->toDateTimeString());

        // This request's session lives in a file (see AppServiceProvider), so send
        // the admin to the normal login page, which uses the new database session.
        return redirect()->route('login', ['installed' => 1]);
    }

    private function guard(): void
    {
        $installed = file_exists(storage_path('app/installed.lock'));

        try {
            $hasAdmin = Schema::hasTable('users') && User::query()->whereIn('role', ['admin', 'superadmin'])->exists();
        } catch (Throwable) {
            $hasAdmin = false;
        }

        abort_if(blank(config('ycc.install_key')) || $installed || $hasAdmin, 404);
    }
}

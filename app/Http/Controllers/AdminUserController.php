<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SupabaseAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('admin.users', ['users' => User::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:full,limited'],
        ]);

        $auth = app(SupabaseAuth::class);
        $authUserId = null;

        try {
            $authUserId = $auth->createUser($data['email'], $data['password'], $data['name']);
            User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(64),
                'role' => $data['role'],
                'supabase_user_id' => $authUserId,
            ]);
        } catch (Throwable $exception) {
            if ($authUserId) {
                try {
                    $auth->deleteUser($authUserId);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            report($exception);

            return back()->withInput($request->except('password', 'password_confirmation'))
                ->with('error', 'The admin account could not be created. Check the Supabase Auth configuration and try again.');
        }

        return back()->with('success', ($data['role'] === 'full' ? 'Primary admin' : 'Team admin').' created successfully.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! $user->supabase_user_id) {
            return back()->with('error', 'This account must be linked to Supabase Auth before its password can be reset.');
        }

        try {
            app(SupabaseAuth::class)->setUserPassword($user->supabase_user_id, $data['password']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'The password could not be updated in Supabase Auth.');
        }

        return back()->with('success', 'Password updated for ' . $user->name . '.');
    }
}

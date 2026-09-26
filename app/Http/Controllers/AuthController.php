<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SupabaseAuth;
use Illuminate\Http\Request;
use App\Models\ActivityLog;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $email = $request->input('email');
        $password = $request->input('password');

        try {
            $authSession = app(SupabaseAuth::class)->signIn($email, $password);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput($request->only('email'))->with('error', 'Admin sign-in is temporarily unavailable. Please try again shortly.');
        }

        $authId = data_get($authSession, 'user.id');
        $user = $authId
            ? User::where('supabase_user_id', $authId)->whereRaw('LOWER(email) = ?', [strtolower($email)])->first()
            : null;

        if (! $user || ! in_array($user->role, ['full', 'limited'], true) || ! filled(data_get($authSession, 'access_token'))) {
            if ($accessToken = data_get($authSession, 'access_token')) {
                try {
                    app(SupabaseAuth::class)->logout($accessToken);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }

            return back()->withInput($request->only('email'))->with('error', 'Invalid admin credentials.');
        }

        $request->session()->regenerate();
        $request->session()->put('is_admin', true);
        $request->session()->put('admin_role', $user->role);
        $request->session()->put('admin_user_id', $user->id);
        $request->session()->put('admin_name', $user->name);
        $request->session()->put('admin_email', $email);
        $request->session()->put('supabase_access_token', $authSession['access_token']);
        $this->logAuthentication($request, 'Signed in');

        return redirect()->route('admin.dashboard');
    }

    public function showForgotPasswordForm()
    {
        return view('admin.forgot-password');
    }

    public function sendPasswordResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        try {
            app(SupabaseAuth::class)->sendPasswordReset(
                $request->input('email'),
                route('password.reset', ['token' => 'supabase'])
            );
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'We could not send the reset link. Please try again shortly.');
        }

        return back()->with('status', 'If an admin account uses that address, a password reset link has been sent.');
    }

    public function showResetPasswordForm(Request $request, string $token)
    {
        return view('admin.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'access_token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        try {
            $supabase = app(SupabaseAuth::class);
            $authUser = $supabase->getUser($request->input('access_token'));
            $user = User::where('supabase_user_id', data_get($authUser, 'id'))
                ->whereRaw('LOWER(email) = ?', [strtolower($request->input('email'))])
                ->first();

            if (! $user) {
                return back()->withInput($request->only('email'))->with('error', 'This reset link is invalid or has expired.');
            }

            $supabase->updatePassword($request->input('access_token'), $request->input('password'));
            try {
                $supabase->logout($request->input('access_token'));
            } catch (\Throwable $exception) {
                report($exception);
            }
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withInput($request->only('email'))->with('error', 'This reset link is invalid or has expired.');
        }

        return redirect()->route('admin.login')->with('success', 'Password reset successfully. You can now sign in.');
    }

    public function logout(Request $request)
    {
        if ($request->session()->get('is_admin')) {
            $this->logAuthentication($request, 'Signed out');
        }

        $accessToken = $request->session()->get('supabase_access_token');
        if ($accessToken) {
            try {
                app(SupabaseAuth::class)->logout($accessToken);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function logAuthentication(Request $request, string $action): void
    {
        ActivityLog::create([
            'user_id' => $request->session()->get('admin_user_id'),
            'actor_name' => $request->session()->get('admin_name', 'Unknown administrator'),
            'actor_email' => $request->session()->get('admin_email'),
            'actor_role' => $request->session()->get('admin_role', 'limited'),
            'action' => $action,
            'method' => 'SESSION',
            'ip_address' => $request->ip(),
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => $action . ' to the admin panel.',
        ]);
    }
}

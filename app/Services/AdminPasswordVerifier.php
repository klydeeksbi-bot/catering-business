<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AdminPasswordVerifier
{
    public function verify(Request $request, string $password): bool
    {
        $user = User::find($request->session()->get('admin_user_id'));

        if ($user?->supabase_user_id) {
            try {
                $authSession = app(SupabaseAuth::class)->signIn((string) $user->email, $password);
                $valid = data_get($authSession, 'user.id') === $user->supabase_user_id;
                $accessToken = data_get($authSession, 'access_token');
                if ($accessToken) {
                    app(SupabaseAuth::class)->logout($accessToken);
                }

                return $valid;
            } catch (Throwable $exception) {
                report($exception);

                return false;
            }
        }

        return app()->environment('testing') && $user !== null && Hash::check($password, $user->password);
    }
}
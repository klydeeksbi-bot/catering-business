<?php

namespace Tests\Feature;

use App\Services\SupabaseAuth;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupabaseAuthTest extends TestCase
{
    public function test_password_sign_in_uses_the_supabase_auth_api(): void
    {
        config([
            'services.supabase.url' => 'https://example.supabase.co',
            'services.supabase.anon_key' => 'test-anon-key',
        ]);
        Http::fake([
            'example.supabase.co/auth/v1/token*' => Http::response([
                'access_token' => 'session-token',
                'user' => ['id' => 'auth-user-id', 'email' => 'admin@example.test'],
            ]),
        ]);

        $result = app(SupabaseAuth::class)->signIn('admin@example.test', 'secret');

        $this->assertSame('session-token', $result['access_token']);
        Http::assertSent(fn ($request) => $request->url() === 'https://example.supabase.co/auth/v1/token?grant_type=password'
            && $request->hasHeader('apikey', 'test-anon-key')
            && $request['email'] === 'admin@example.test'
            && $request['password'] === 'secret');
    }

    public function test_invalid_credentials_return_no_auth_session(): void
    {
        config([
            'services.supabase.url' => 'https://example.supabase.co',
            'services.supabase.anon_key' => 'test-anon-key',
        ]);
        Http::fake(['*' => Http::response(['msg' => 'Invalid login credentials'], 400)]);

        $this->assertNull(app(SupabaseAuth::class)->signIn('admin@example.test', 'wrong'));
    }

    public function test_only_a_supabase_auth_user_with_an_admin_profile_can_sign_in(): void
    {
        config([
            'services.supabase.url' => 'https://example.supabase.co',
            'services.supabase.anon_key' => 'test-anon-key',
        ]);
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'role' => 'full',
            'supabase_user_id' => 'auth-user-id',
        ]);
        Http::fake([
            'example.supabase.co/auth/v1/token*' => Http::response([
                'access_token' => 'session-token',
                'user' => ['id' => 'auth-user-id', 'email' => 'admin@example.test'],
            ]),
        ]);

        $this->post(route('admin.login.post'), ['email' => $user->email, 'password' => 'secret'])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('admin_role', 'full')
            ->assertSessionHas('supabase_access_token', 'session-token');
    }

    public function test_authenticated_non_admin_cannot_sign_in_to_the_dashboard(): void
    {
        config([
            'services.supabase.url' => 'https://example.supabase.co',
            'services.supabase.anon_key' => 'test-anon-key',
        ]);
        Http::fake([
            'example.supabase.co/auth/v1/token*' => Http::response([
                'access_token' => 'session-token',
                'user' => ['id' => 'unlinked-auth-user', 'email' => 'person@example.test'],
            ]),
            'example.supabase.co/auth/v1/logout' => Http::response([], 204),
        ]);

        $this->from(route('admin.login'))
            ->post(route('admin.login.post'), ['email' => 'person@example.test', 'password' => 'secret'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Invalid admin credentials.')
            ->assertSessionMissing('is_admin');
    }
}
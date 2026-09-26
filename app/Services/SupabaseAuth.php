<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SupabaseAuth
{
    public function isConfigured(): bool
    {
        return filled(config('services.supabase.url')) && filled(config('services.supabase.anon_key'));
    }

    public function signIn(string $email, string $password): ?array
    {
        $response = $this->request('POST', '/auth/v1/token?grant_type=password', [
            'email' => $email,
            'password' => $password,
        ]);

        if (in_array($response->status(), [400, 401], true)) {
            return null;
        }

        $this->ensureSuccessful($response);

        return $response->json();
    }

    public function logout(string $accessToken): void
    {
        $this->request('POST', '/auth/v1/logout', [], $accessToken);
    }

    public function sendPasswordReset(string $email, string $redirectTo): void
    {
        $this->request('POST', '/auth/v1/recover?redirect_to='.urlencode($redirectTo), ['email' => $email]);
    }

    public function updatePassword(string $accessToken, string $password): void
    {
        $response = Http::timeout(15)
            ->withHeaders($this->headers($accessToken))
            ->put($this->url().'/auth/v1/user', ['password' => $password]);

        $this->ensureSuccessful($response);
    }

    public function getUser(string $accessToken): array
    {
        $response = Http::timeout(15)
            ->withHeaders($this->headers($accessToken))
            ->get($this->url().'/auth/v1/user');

        $this->ensureSuccessful($response);

        return $response->json();
    }

    public function createUser(string $email, string $password, string $name): string
    {
        $response = $this->adminRequest('POST', '/auth/v1/admin/users', [
            'email' => $email,
            'password' => $password,
            'email_confirm' => true,
            'user_metadata' => ['name' => $name],
        ]);

        $id = $response->json('id');
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Supabase Auth did not return a user ID.');
        }

        return $id;
    }

    public function setUserPassword(string $userId, string $password): void
    {
        $this->adminRequest('PUT', '/auth/v1/admin/users/'.urlencode($userId), ['password' => $password]);
    }

    public function deleteUser(string $userId): void
    {
        $this->adminRequest('DELETE', '/auth/v1/admin/users/'.urlencode($userId), []);
    }

    public function userIdsForEmails(array $emails): array
    {
        $key = (string) config('services.supabase.service_role_key');
        if ($key === '') {
            throw new RuntimeException('Supabase service role key is not configured.');
        }

        $wanted = array_fill_keys(array_map('strtolower', $emails), true);
        $matches = [];
        $page = 1;
        do {
            $response = Http::timeout(15)
                ->withHeaders([
                    'apikey' => $key,
                    'Authorization' => 'Bearer '.$key,
                    'Accept' => 'application/json',
                ])
                ->get($this->url().'/auth/v1/admin/users', ['page' => $page, 'per_page' => 1000]);
            $this->ensureSuccessful($response);
            $users = $response->json('users', []);

            foreach ($users as $user) {
                $email = strtolower((string) ($user['email'] ?? ''));
                if (isset($wanted[$email]) && isset($user['id'])) {
                    $matches[$email] = $user['id'];
                }
            }

            $page++;
        } while (count($users) === 1000);

        return $matches;
    }

    private function request(string $method, string $path, array $data = [], ?string $accessToken = null): Response
    {
        return Http::timeout(15)
            ->withHeaders($this->headers($accessToken))
            ->send($method, $this->url().$path, ['json' => $data]);
    }

    private function adminRequest(string $method, string $path, array $data): Response
    {
        $key = (string) config('services.supabase.service_role_key');
        if ($key === '') {
            throw new RuntimeException('Supabase service role key is not configured.');
        }

        $response = Http::timeout(15)
            ->withHeaders([
                'apikey' => $key,
                'Authorization' => 'Bearer '.$key,
                'Accept' => 'application/json',
            ])
            ->send($method, $this->url().$path, ['json' => $data]);

        $this->ensureSuccessful($response);

        return $response;
    }

    private function headers(?string $accessToken = null): array
    {
        $key = (string) config('services.supabase.anon_key');
        if ($key === '') {
            throw new RuntimeException('Supabase anon key is not configured.');
        }

        return [
            'apikey' => $key,
            'Authorization' => 'Bearer '.($accessToken ?: $key),
            'Accept' => 'application/json',
        ];
    }

    private function url(): string
    {
        $url = rtrim((string) config('services.supabase.url'), '/');
        if ($url === '') {
            throw new RuntimeException('Supabase URL is not configured.');
        }

        return $url;
    }

    private function ensureSuccessful(Response $response): void
    {
        if (! $response->successful()) {
            throw new RuntimeException('Supabase Auth request failed (HTTP '.$response->status().').');
        }
    }
}
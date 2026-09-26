<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SupabaseStorage
{
    public function upload(UploadedFile $file, string $folder): string
    {
        $path = trim($folder, '/').'/'.Str::uuid().'.'.$file->extension();
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $response = Http::timeout(30)
            ->withHeaders($this->headers() + [
                'Content-Type' => $mime,
                'x-upsert' => 'false',
            ])
            ->withBody($contents, $mime)
            ->post($this->objectUrl($path));

        $this->ensureSuccessful($response);

        return $path;
    }

    public function delete(string $path): void
    {
        if ($path === '') {
            return;
        }

        $response = Http::timeout(15)
            ->withHeaders($this->headers())
            ->delete($this->bucketUrl(), ['prefixes' => [$path]]);

        $this->ensureSuccessful($response);
    }

    public function publicUrl(?string $path): string
    {
        if (! $path) {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }
        if (! filled(config('services.supabase.url'))) {
            return asset('storage/'.ltrim($path, '/'));
        }

        return $this->objectUrl($path, true);
    }

    private function objectUrl(string $path, bool $public = false): string
    {
        $bucket = rawurlencode((string) config('services.supabase.storage_bucket'));
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
        $visibility = $public ? '/public' : '';

        return $this->url().'/storage/v1/object'.$visibility.'/'.$bucket.'/'.$encodedPath;
    }

    private function bucketUrl(): string
    {
        return $this->url().'/storage/v1/object/'.rawurlencode((string) config('services.supabase.storage_bucket'));
    }

    private function headers(): array
    {
        $key = (string) config('services.supabase.service_role_key');
        if ($key === '') {
            throw new RuntimeException('Supabase service role key is not configured.');
        }

        return [
            'apikey' => $key,
            'Authorization' => 'Bearer '.$key,
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
            throw new RuntimeException('Supabase Storage request failed (HTTP '.$response->status().').');
        }
    }
}
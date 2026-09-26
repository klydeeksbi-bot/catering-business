<?php

namespace Tests\Feature;

use App\Services\SupabaseStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupabaseStorageTest extends TestCase
{
    public function test_uploads_use_the_private_server_key_and_public_urls_use_the_configured_bucket(): void
    {
        config([
            'services.supabase.url' => 'https://example.supabase.co',
            'services.supabase.service_role_key' => 'server-only-key',
            'services.supabase.storage_bucket' => 'catering-media',
        ]);
        Http::fake(['example.supabase.co/storage/v1/object/*' => Http::response(['Key' => 'stored'], 200)]);

        $storage = app(SupabaseStorage::class);
        $path = $storage->upload(UploadedFile::fake()->create('menu.webp', 10, 'image/webp'), 'packages');

        $this->assertStringStartsWith('packages/', $path);
        $this->assertStringEndsWith('.webp', $path);
        $this->assertSame(
            'https://example.supabase.co/storage/v1/object/public/catering-media/packages/a%20menu.webp',
            $storage->publicUrl('packages/a menu.webp')
        );
        Http::assertSent(fn ($request) => $request->hasHeader('apikey', 'server-only-key')
            && $request->hasHeader('Authorization', 'Bearer server-only-key')
            && str_contains($request->url(), '/storage/v1/object/catering-media/packages/'));
    }
}
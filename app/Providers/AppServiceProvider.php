<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $tempDir = '/tmp';

        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }

        foreach (['TMPDIR', 'TMP', 'TEMP'] as $key) {
            putenv(sprintf('%s=%s', $key, $tempDir));
            $_ENV[$key] = $tempDir;
            $_SERVER[$key] = $tempDir;
        }
    }
}

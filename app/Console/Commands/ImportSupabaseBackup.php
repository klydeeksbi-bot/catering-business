<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use App\Services\SupabaseAuth;
use App\Services\SupabaseStorage;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ImportSupabaseBackup extends Command
{
    protected $signature = 'supabase:import-backup {backup : Backup filename in storage/app/backups}';

    protected $description = 'Import an existing Laravel JSON backup into an empty Supabase Postgres database';

    private const TABLES = [
        'users',
        'services',
        'packages',
        'clients',
        'reservations',
        'inquiries',
        'activity_logs',
        'settings',
        'notification_templates',
        'gallery_items',
    ];

    public function handle(BackupService $backups, SupabaseAuth $auth, SupabaseStorage $storage): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('Set DB_CONNECTION=pgsql and SUPABASE_DB_URL before importing.');

            return self::FAILURE;
        }

        try {
            $contents = json_decode(file_get_contents($backups->pathFor($this->argument('backup'))), true, 512, JSON_THROW_ON_ERROR);
            $rows = $contents['tables'] ?? null;
            if (! is_array($rows)) {
                throw new RuntimeException('The backup file does not contain a tables collection.');
            }

            $this->assertDestinationIsEmpty();
            $rowsByTable = $this->prepareRows($rows, $auth, $storage);
            $this->importRows($rowsByTable);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $unlinkedUsers = collect($rowsByTable['users'] ?? [])->filter(fn (array $user) => empty($user['supabase_user_id']))->count();
        $this->info('Backup data imported. Existing IDs and entity relationships were preserved.');
        if ($unlinkedUsers > 0) {
            $this->warn("{$unlinkedUsers} user profile(s) have no matching Supabase Auth account; create or link those accounts before admin sign-in.");
        }

        return self::SUCCESS;
    }

    private function assertDestinationIsEmpty(): void
    {
        $seedSlugs = ['silver', 'gold', 'platinum', 'diamond'];

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Supabase table {$table} is missing. Apply the SQL migration first.");
            }

            if ($table === 'packages') {
                $packages = DB::table('packages')->get(['slug']);
                if ($packages->contains(fn ($package) => ! in_array($package->slug, $seedSlugs, true))) {
                    throw new RuntimeException('The destination already contains package data; import is only supported into an empty app schema.');
                }
                continue;
            }

            if (DB::table($table)->exists()) {
                throw new RuntimeException("The destination table {$table} is not empty; refusing to overwrite data.");
            }
        }
    }

    private function prepareRows(array $backupTables, SupabaseAuth $auth, SupabaseStorage $storage): array
    {
        $users = $backupTables['users'] ?? [];
        if (! is_array($users) || ! array_is_list($users)) {
            throw new RuntimeException('Invalid data for users in the backup.');
        }
        $userIds = $auth->userIdsForEmails(array_map(fn ($user) => (string) ($user['email'] ?? ''), $users));
        $prepared = [];
        $uploadedPaths = [];

        foreach (self::TABLES as $table) {
            $tableRows = $backupTables[$table] ?? [];
            if (! is_array($tableRows) || ! array_is_list($tableRows)) {
                throw new RuntimeException("Invalid data for {$table} in the backup.");
            }
            $columns = array_flip(Schema::getColumnListing($table));
            $prepared[$table] = [];

            foreach ($tableRows as $row) {
                if (! is_array($row)) {
                    throw new RuntimeException("Invalid row data for {$table}.");
                }
                $row = array_intersect_key($row, $columns);

                if ($table === 'users') {
                    $row['supabase_user_id'] = $userIds[strtolower((string) ($row['email'] ?? ''))] ?? null;
                }
                if ($table === 'packages' && ! empty($row['image_path'])) {
                    $row['image_path'] = $this->moveFile($row['image_path'], $storage, $uploadedPaths);
                }
                if ($table === 'gallery_items' && ! empty($row['image_path'])) {
                    $row['image_path'] = $this->moveFile($row['image_path'], $storage, $uploadedPaths);
                }
                if ($table === 'reservations') {
                    foreach (['service_contract', 'service_contracts'] as $column) {
                        if (empty($row[$column])) {
                            continue;
                        }
                        $files = $column === 'service_contracts'
                            ? (is_array($row[$column]) ? $row[$column] : json_decode($row[$column], true, 512, JSON_THROW_ON_ERROR))
                            : [$row[$column]];
                        $files = array_map(fn ($path) => $this->moveFile($path, $storage, $uploadedPaths), $files ?: []);
                        $row[$column] = $column === 'service_contracts'
                            ? json_encode($files, JSON_THROW_ON_ERROR)
                            : ($files[0] ?? null);
                    }
                }

                $prepared[$table][] = $row;
            }
        }

        return $prepared;
    }

    private function moveFile(string $path, SupabaseStorage $storage, array &$uploadedPaths): string
    {
        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }
        if (isset($uploadedPaths[$path])) {
            return $uploadedPaths[$path];
        }

        $localPath = storage_path('app/public/'.ltrim($path, '/'));
        if (! is_file($localPath)) {
            throw new RuntimeException("Referenced local media file is missing: {$path}");
        }

        $file = new UploadedFile(
            $localPath,
            basename($localPath),
            mime_content_type($localPath) ?: 'application/octet-stream',
            UPLOAD_ERR_OK,
            true
        );
        $folder = dirname($path);
        $uploadedPaths[$path] = $storage->upload($file, $folder === '.' ? 'imports' : $folder);

        return $uploadedPaths[$path];
    }

    private function importRows(array $rowsByTable): void
    {
        DB::transaction(function () use ($rowsByTable): void {
            DB::table('packages')->whereIn('slug', ['silver', 'gold', 'platinum', 'diamond'])->delete();

            foreach (self::TABLES as $table) {
                foreach (array_chunk($rowsByTable[$table], 250) as $chunk) {
                    if ($chunk !== []) {
                        DB::table($table)->insert($chunk);
                    }
                }
            }

            foreach (self::TABLES as $table) {
                DB::statement("select setval(pg_get_serial_sequence('public.{$table}', 'id'), coalesce(max(id), 1), count(*) > 0) from public.{$table}");
            }
        });
    }
}
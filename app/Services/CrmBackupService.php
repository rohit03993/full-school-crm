<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class CrmBackupService
{
    public const MANIFEST_NAME = 'manifest.json';

    public const DATABASE_NAME = 'database.sql';

    public const APP_KEY_NAME = 'app-key.txt';

    public const ENV_SNAPSHOT_NAME = 'env-snapshot.json';

    public const RESTORE_GUIDE_NAME = 'RESTORE.txt';

    /**
     * Create a full backup archive: database + private files + public files + APP_KEY.
     *
     * @return array{
     *     path: string,
     *     filename: string,
     *     size_bytes: int,
     *     created_at: string,
     *     database_driver: string,
     *     private_files: int,
     *     public_files: int,
     *     tables: int,
     * }
     */
    public function create(?callable $onProgress = null): array
    {
        $this->ensureBackupDirectory();

        $stamp = now()->format('Y-m-d_His');
        $filename = 'school-crm-full-backup-'.$stamp.'.zip';
        $zipPath = $this->backupDirectory().DIRECTORY_SEPARATOR.$filename;
        $workDir = storage_path('app/private/backups/.work-'.Str::lower(Str::random(8)));

        File::ensureDirectoryExists($workDir);

        try {
            $this->progress($onProgress, 'Dumping database…');
            $sqlPath = $workDir.DIRECTORY_SEPARATOR.self::DATABASE_NAME;
            $tableCount = $this->dumpDatabase($sqlPath);

            $this->progress($onProgress, 'Collecting private files (documents, photos, receipts)…');
            $privateDir = $workDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'private';
            $privateFiles = $this->copyStorageTree(
                storage_path('app/private'),
                $privateDir,
                config('crm-backup.exclude_private_prefixes', []),
            );

            $this->progress($onProgress, 'Collecting public files (homework, logos, gallery)…');
            $publicDir = $workDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'public';
            $publicFiles = $this->copyStorageTree(
                storage_path('app/public'),
                $publicDir,
                config('crm-backup.exclude_public_prefixes', []),
            );

            $this->progress($onProgress, 'Writing restore metadata…');
            $manifest = [
                'format' => 'school-crm-full-backup-v1',
                'created_at' => now()->toIso8601String(),
                'app_name' => config('app.name'),
                'app_url' => config('app.url'),
                'app_env' => config('app.env'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'database' => [
                    'driver' => config('database.default'),
                    'connection' => config('database.default'),
                    'name' => (string) config('database.connections.'.config('database.default').'.database'),
                    'tables' => $tableCount,
                ],
                'contents' => [
                    'database' => self::DATABASE_NAME,
                    'private_files' => $privateFiles,
                    'public_files' => $publicFiles,
                    'app_key' => self::APP_KEY_NAME,
                    'env_snapshot' => self::ENV_SNAPSHOT_NAME,
                ],
                'includes' => [
                    'students_leads_visits_calls_cases',
                    'fees_payments_receipts',
                    'attendance_homework_exams',
                    'whatsapp_messages_media',
                    'documents_photos_aadhaar',
                    'site_logos_gallery',
                    'settings_users_roles_sequences',
                    'audit_logs_notifications',
                ],
            ];

            File::put(
                $workDir.DIRECTORY_SEPARATOR.self::MANIFEST_NAME,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
            );

            File::put(
                $workDir.DIRECTORY_SEPARATOR.self::APP_KEY_NAME,
                (string) config('app.key')."\n",
            );

            File::put(
                $workDir.DIRECTORY_SEPARATOR.self::ENV_SNAPSHOT_NAME,
                json_encode($this->envSnapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
            );

            File::put(
                $workDir.DIRECTORY_SEPARATOR.self::RESTORE_GUIDE_NAME,
                $this->restoreGuideText(),
            );

            $this->progress($onProgress, 'Building zip archive…');
            $this->zipDirectory($workDir, $zipPath);

            $this->pruneOldBackups();

            clearstatcache(true, $zipPath);

            return [
                'path' => $zipPath,
                'filename' => $filename,
                'size_bytes' => (int) filesize($zipPath),
                'created_at' => $manifest['created_at'],
                'database_driver' => (string) $manifest['database']['driver'],
                'private_files' => $privateFiles,
                'public_files' => $publicFiles,
                'tables' => $tableCount,
            ];
        } catch (Throwable $exception) {
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }

            throw $exception;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * @return list<array{
     *     filename: string,
     *     path: string,
     *     size_bytes: int,
     *     created_at: ?Carbon,
     * }>
     */
    public function listBackups(): array
    {
        $this->ensureBackupDirectory();

        $files = collect(File::files($this->backupDirectory()))
            ->filter(fn ($file): bool => str_ends_with(strtolower($file->getFilename()), '.zip'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        return $files->map(function ($file): array {
            return [
                'filename' => $file->getFilename(),
                'path' => $file->getPathname(),
                'size_bytes' => $file->getSize(),
                'created_at' => Carbon::createFromTimestamp($file->getMTime()),
            ];
        })->all();
    }

    public function findBackup(string $filename): ?string
    {
        $filename = basename($filename);

        if (! preg_match('/^school-crm-full-backup-[\w\-]+\.zip$/', $filename)) {
            return null;
        }

        $path = $this->backupDirectory().DIRECTORY_SEPARATOR.$filename;

        return is_file($path) ? $path : null;
    }

    public function deleteBackup(string $filename): bool
    {
        $path = $this->findBackup($filename);

        if (! $path) {
            return false;
        }

        return @unlink($path);
    }

    public function backupDirectory(): string
    {
        return (string) config('crm-backup.disk_path', storage_path('app/private/backups'));
    }

    public function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $i = 0;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 2).' '.$units[$i];
    }

    /**
     * Restore from a full backup zip. Destructive — replaces DB and storage trees.
     *
     * @return array{manifest: array<string, mixed>, private_files: int, public_files: int}
     */
    public function restore(string $zipPath, bool $force = false): array
    {
        if (! $force) {
            throw new RuntimeException('Restore refused: pass force=true / --force after confirming a maintenance window.');
        }

        if (! is_file($zipPath)) {
            throw new RuntimeException('Backup file not found.');
        }

        $workDir = storage_path('app/private/backups/.restore-'.Str::lower(Str::random(8)));
        File::ensureDirectoryExists($workDir);

        try {
            $zip = new ZipArchive;

            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('Could not open backup zip.');
            }

            $zip->extractTo($workDir);
            $zip->close();

            $manifestPath = $workDir.DIRECTORY_SEPARATOR.self::MANIFEST_NAME;
            $sqlPath = $workDir.DIRECTORY_SEPARATOR.self::DATABASE_NAME;

            if (! is_file($manifestPath) || ! is_file($sqlPath)) {
                throw new RuntimeException('Invalid backup: missing manifest.json or database.sql.');
            }

            /** @var array<string, mixed> $manifest */
            $manifest = json_decode((string) File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);

            if (($manifest['format'] ?? null) !== 'school-crm-full-backup-v1') {
                throw new RuntimeException('Unsupported backup format.');
            }

            $appKeyPath = $workDir.DIRECTORY_SEPARATOR.self::APP_KEY_NAME;
            $backupKey = is_file($appKeyPath) ? trim((string) File::get($appKeyPath)) : '';

            if ($backupKey !== '' && $backupKey !== (string) config('app.key')) {
                throw new RuntimeException(
                    'APP_KEY mismatch. Put the APP_KEY from app-key.txt into .env, then run restore again. '.
                    'Without the same key, WhatsApp secrets and license signing will break.'
                );
            }

            $this->importDatabase($sqlPath);

            $privateSource = $workDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'private';
            $publicSource = $workDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'public';

            $privateFiles = $this->replaceStorageTree(
                $privateSource,
                storage_path('app/private'),
                config('crm-backup.exclude_private_prefixes', []),
            );

            $publicFiles = $this->replaceStorageTree(
                $publicSource,
                storage_path('app/public'),
                config('crm-backup.exclude_public_prefixes', []),
            );

            return [
                'manifest' => $manifest,
                'private_files' => $privateFiles,
                'public_files' => $publicFiles,
            ];
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    protected function dumpDatabase(string $sqlPath): int
    {
        $driver = config('database.default');
        $connection = config('database.connections.'.$driver);

        if ($driver === 'mysql' || $driver === 'mariadb') {
            if ($this->tryMysqlDump($sqlPath, $connection)) {
                return $this->countTables();
            }
        }

        return $this->dumpDatabaseWithPhp($sqlPath);
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    protected function tryMysqlDump(string $sqlPath, array $connection): bool
    {
        $mysqldump = $this->findMysqlDumpBinary();

        if (! $mysqldump) {
            return false;
        }

        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        $args = [
            $mysqldump,
            '--single-transaction',
            '--routines',
            '--triggers',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            '-h', $host,
            '-P', $port,
            '-u', $username,
        ];

        if ($password !== '') {
            $args[] = '-p'.$password;
        }

        $args[] = $database;

        $result = Process::timeout(3600)->run($args);

        if (! $result->successful()) {
            return false;
        }

        File::put($sqlPath, $result->output());

        return is_file($sqlPath) && filesize($sqlPath) > 0;
    }

    protected function findMysqlDumpBinary(): ?string
    {
        foreach (['mysqldump', 'mysqldump.exe'] as $binary) {
            $result = Process::run([
                PHP_OS_FAMILY === 'Windows' ? 'where' : 'which',
                $binary,
            ]);

            if ($result->successful()) {
                $line = trim(explode("\n", str_replace("\r", '', $result->output()))[0] ?? '');

                if ($line !== '' && is_file($line)) {
                    return $line;
                }
            }
        }

        return null;
    }

    protected function dumpDatabaseWithPhp(string $sqlPath): int
    {
        $driver = config('database.default');
        $tables = $this->listTableNames();
        $handle = fopen($sqlPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Could not write database dump.');
        }

        try {
            fwrite($handle, "-- School CRM full backup\n");
            fwrite($handle, '-- Created: '.now()->toDateTimeString()."\n");
            fwrite($handle, '-- Driver: '.$driver."\n\n");

            if ($driver === 'mysql' || $driver === 'mariadb') {
                fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");
            } elseif ($driver === 'sqlite') {
                fwrite($handle, "PRAGMA foreign_keys = OFF;\n\n");
            }

            foreach ($tables as $table) {
                $this->writeTableDump($handle, $table, $driver);
            }

            if ($driver === 'mysql' || $driver === 'mariadb') {
                fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            } elseif ($driver === 'sqlite') {
                fwrite($handle, "PRAGMA foreign_keys = ON;\n");
            }
        } finally {
            fclose($handle);
        }

        return count($tables);
    }

    /**
     * @param  resource  $handle
     */
    protected function writeTableDump($handle, string $table, string $driver): void
    {
        fwrite($handle, "-- Table: {$table}\n");

        if ($driver === 'sqlite') {
            $create = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
            $createSql = $create->sql ?? null;

            if (is_string($createSql) && $createSql !== '') {
                fwrite($handle, 'DROP TABLE IF EXISTS '.$this->quoteIdentifier($table, $driver).";\n");
                fwrite($handle, $createSql.";\n\n");
            }
        } else {
            $quoted = $this->quoteIdentifier($table, $driver);
            $createRow = DB::selectOne('SHOW CREATE TABLE '.$quoted);
            $createSql = null;

            if ($createRow) {
                $vars = array_values((array) $createRow);
                $createSql = isset($vars[1]) && is_string($vars[1]) ? $vars[1] : null;
            }

            if (is_string($createSql) && $createSql !== '') {
                fwrite($handle, 'DROP TABLE IF EXISTS '.$quoted.";\n");
                fwrite($handle, $createSql.";\n\n");
            }
        }

        $query = DB::table($table);
        $hasId = \Illuminate\Support\Facades\Schema::hasColumn($table, 'id');

        if ($hasId) {
            $query->orderBy('id')->chunk(200, function ($rows) use ($handle, $table, $driver): void {
                foreach ($rows as $row) {
                    $this->writeInsertRow($handle, $table, $driver, $row);
                }
            });
        } else {
            foreach ($query->get() as $row) {
                $this->writeInsertRow($handle, $table, $driver, $row);
            }
        }

        fwrite($handle, "\n");
    }

    /**
     * @param  resource  $handle
     */
    protected function writeInsertRow($handle, string $table, string $driver, object $row): void
    {
        $values = [];

        foreach ((array) $row as $value) {
            $values[] = $this->sqlLiteral($value, $driver);
        }

        fwrite(
            $handle,
            'INSERT INTO '.$this->quoteIdentifier($table, $driver).
            ' VALUES ('.implode(', ', $values).");\n"
        );
    }

    protected function importDatabase(string $sqlPath): void
    {
        $driver = config('database.default');
        $sql = File::get($sqlPath);

        if ($driver === 'mysql' || $driver === 'mariadb') {
            if ($this->tryMysqlImport($sqlPath)) {
                return;
            }
        }

        // PHP fallback: split on semicolons carefully enough for our dumps
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        $statements = $this->splitSqlStatements($sql);

        foreach ($statements as $statement) {
            $statement = trim($statement);

            if ($statement === '' || str_starts_with($statement, '--')) {
                continue;
            }

            // Skip MySQL-only toggles on SQLite
            if ($driver === 'sqlite' && str_contains(strtoupper($statement), 'FOREIGN_KEY_CHECKS')) {
                continue;
            }

            DB::unprepared($statement);
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    protected function tryMysqlImport(string $sqlPath): bool
    {
        $mysql = $this->findMysqlBinary();

        if (! $mysql) {
            return false;
        }

        $connection = config('database.connections.'.config('database.default'));
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        $args = [
            $mysql,
            '-h', $host,
            '-P', $port,
            '-u', $username,
        ];

        if ($password !== '') {
            $args[] = '-p'.$password;
        }

        $args[] = $database;

        $result = Process::timeout(3600)
            ->input(File::get($sqlPath))
            ->run($args);

        return $result->successful();
    }

    protected function findMysqlBinary(): ?string
    {
        foreach (['mysql', 'mysql.exe'] as $binary) {
            $result = Process::run([
                PHP_OS_FAMILY === 'Windows' ? 'where' : 'which',
                $binary,
            ]);

            if ($result->successful()) {
                $line = trim(explode("\n", str_replace("\r", '', $result->output()))[0] ?? '');

                if ($line !== '' && is_file($line)) {
                    return $line;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $stringChar = '';
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inString) {
                $buffer .= $char;

                if ($char === '\\' && $next !== '') {
                    $buffer .= $next;
                    $i++;

                    continue;
                }

                if ($char === $stringChar) {
                    $inString = false;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = true;
                $stringChar = $char;
                $buffer .= $char;

                continue;
            }

            if ($char === '-' && $next === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }

                continue;
            }

            if ($char === ';') {
                $statements[] = $buffer;
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    /**
     * @param  list<string>  $excludePrefixes
     */
    protected function copyStorageTree(string $sourceRoot, string $targetRoot, array $excludePrefixes): int
    {
        File::ensureDirectoryExists($targetRoot);

        if (! is_dir($sourceRoot)) {
            return 0;
        }

        $count = 0;
        $sourceRootReal = realpath($sourceRoot) ?: $sourceRoot;

        $directory = new \RecursiveDirectoryIterator($sourceRootReal, \FilesystemIterator::SKIP_DOTS);
        $filter = new \RecursiveCallbackFilterIterator(
            $directory,
            function (\SplFileInfo $current) use ($sourceRootReal, $excludePrefixes): bool {
                $absolute = $current->getPathname();
                $relative = ltrim(str_replace('\\', '/', Str::after($absolute, $sourceRootReal)), '/');

                if ($relative === '') {
                    return true;
                }

                if (str_contains($relative, '/.work-') || str_starts_with($relative, '.work-')
                    || str_contains($relative, '/.restore-') || str_starts_with($relative, '.restore-')) {
                    return false;
                }

                return ! $this->shouldExcludePath($relative, $excludePrefixes);
            },
        );

        $iterator = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::SELF_FIRST);

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            $absolute = $item->getPathname();
            $relative = ltrim(str_replace('\\', '/', Str::after($absolute, $sourceRootReal)), '/');

            if ($relative === '') {
                continue;
            }

            $destination = $targetRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if ($item->isDir()) {
                File::ensureDirectoryExists($destination);

                continue;
            }

            File::ensureDirectoryExists(dirname($destination));
            File::copy($absolute, $destination);
            $count++;
        }

        return $count;
    }

    /**
     * Replace destination tree contents with source (keeps excluded dirs like backups/).
     *
     * @param  list<string>  $preservePrefixes
     */
    protected function replaceStorageTree(string $sourceRoot, string $destinationRoot, array $preservePrefixes): int
    {
        File::ensureDirectoryExists($destinationRoot);

        if (! is_dir($sourceRoot)) {
            return 0;
        }

        // Remove existing files except preserved prefixes
        if (is_dir($destinationRoot)) {
            foreach (File::directories($destinationRoot) as $dir) {
                $name = basename($dir);

                if (in_array($name, $preservePrefixes, true) || str_starts_with($name, '.work-') || str_starts_with($name, '.restore-')) {
                    continue;
                }

                File::deleteDirectory($dir);
            }

            foreach (File::files($destinationRoot) as $file) {
                @unlink($file->getPathname());
            }
        }

        return $this->copyStorageTree($sourceRoot, $destinationRoot, []);
    }

    /**
     * @param  list<string>  $excludePrefixes
     */
    protected function shouldExcludePath(string $relative, array $excludePrefixes): bool
    {
        $relative = str_replace('\\', '/', $relative);

        foreach ($excludePrefixes as $prefix) {
            $prefix = trim(str_replace('\\', '/', $prefix), '/');

            if ($relative === $prefix || str_starts_with($relative, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    protected function zipDirectory(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create backup zip.');
        }

        $sourceDir = realpath($sourceDir) ?: $sourceDir;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolute = $file->getRealPath() ?: $file->getPathname();
            $relative = ltrim(str_replace('\\', '/', Str::after($absolute, $sourceDir)), '/');
            $zip->addFile($absolute, $relative);
        }

        $zip->close();

        if (! is_file($zipPath)) {
            throw new RuntimeException('Backup zip was not created.');
        }
    }

    protected function pruneOldBackups(): void
    {
        $retain = max(1, (int) config('crm-backup.retain', 14));
        $backups = $this->listBackups();

        foreach (array_slice($backups, $retain) as $old) {
            @unlink($old['path']);
        }
    }

    protected function ensureBackupDirectory(): void
    {
        File::ensureDirectoryExists($this->backupDirectory());
    }

    /**
     * @return list<string>
     */
    protected function listTableNames(): array
    {
        $driver = config('database.default');

        if ($driver === 'sqlite') {
            return collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
                ->pluck('name')
                ->sort()
                ->values()
                ->all();
        }

        return collect(DB::select('SHOW TABLES'))
            ->map(function ($row) {
                $values = array_values((array) $row);

                return (string) ($values[0] ?? '');
            })
            ->filter()
            ->sort()
            ->values()
            ->all();
    }

    protected function countTables(): int
    {
        return count($this->listTableNames());
    }

    protected function quoteIdentifier(string $name, string $driver): string
    {
        if ($driver === 'sqlite') {
            return '"'.str_replace('"', '""', $name).'"';
        }

        return '`'.str_replace('`', '``', $name).'`';
    }

    protected function sqlLiteral(mixed $value, string $driver): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $string = (string) $value;

        if ($driver === 'sqlite') {
            return "'".str_replace("'", "''", $string)."'";
        }

        return DB::getPdo()->quote($string);
    }

    /**
     * @return array<string, string|null>
     */
    protected function envSnapshot(): array
    {
        return [
            'APP_NAME' => config('app.name'),
            'APP_ENV' => config('app.env'),
            'APP_URL' => config('app.url'),
            'APP_KEY' => config('app.key'),
            'APP_TIMEZONE' => config('app.timezone'),
            'DB_CONNECTION' => config('database.default'),
            'DB_DATABASE' => (string) config('database.connections.'.config('database.default').'.database'),
            'FILESYSTEM_DISK' => config('filesystems.default'),
            'note' => 'Restore requires the same APP_KEY. Database credentials on the new server may differ — only APP_KEY must match the backup.',
        ];
    }

    protected function restoreGuideText(): string
    {
        return <<<'TXT'
School CRM — Full backup restore
================================

This archive contains EVERYTHING needed to restore the institute:
- database.sql          → all tables (students, leads, calls, cases, fees, attendance, homework, WhatsApp, settings, users…)
- storage/private/      → photos, Aadhaar, documents, receipts, ID cards, payment proofs, marksheets, WhatsApp media
- storage/public/       → homework files, website logo/gallery, CRM branding
- app-key.txt           → APP_KEY (must match .env or WhatsApp/license secrets break)
- manifest.json         → backup metadata

Restore (server):
1. Put the CRM code on the server (same or compatible version).
2. Set .env APP_KEY to the value in app-key.txt (exact match).
3. php artisan crm:restore path/to/this.zip --force
4. php artisan storage:link
5. php artisan crm:publish-assets
6. php artisan cache:clear
7. Restart queue worker / cron.

Or from admin: Setup → Backups (Super Admin) after uploading is not supported — use artisan restore.

TXT;
    }

    protected function progress(?callable $onProgress, string $message): void
    {
        if ($onProgress) {
            $onProgress($message);
        }
    }
}

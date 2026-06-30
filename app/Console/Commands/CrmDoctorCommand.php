<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class CrmDoctorCommand extends Command
{
    protected $signature = 'crm:doctor {--probe : Write a one-time web probe file and print its URL}';

    protected $description = 'Diagnose common production issues (permissions, assets, autoload, database, HTTP)';

    public function handle(): int
    {
        $ok = true;

        $this->components->info('Environment');
        $this->line('PHP '.PHP_VERSION.' · SAPI '.PHP_SAPI.' · user '.(function_exists('posix_getpwuid') && function_exists('posix_geteuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? '?')
            : get_current_user()));
        $this->line('Project root: '.base_path());
        $this->line('CLI open_basedir: '.(ini_get('open_basedir') ?: '(none — FPM may still restrict via CloudPanel)'));

        foreach ([
            storage_path('logs'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('app'),
            base_path('bootstrap/cache'),
        ] as $path) {
            if (! is_writable($path)) {
                $this->components->error("Not writable: {$path}");
                $ok = false;
            } else {
                $owner = $this->pathOwner($path);
                $this->line("OK writable: {$path}".($owner ? " ({$owner})" : ''));
            }
        }

        if (! file_exists(base_path('vendor/autoload.php'))) {
            $this->components->error('vendor/ missing — run composer install');
            $ok = false;
        }

        if (blank(config('app.key'))) {
            $this->components->error('APP_KEY is empty — run php artisan key:generate');
            $ok = false;
        }

        if (file_exists(base_path('app/Jobs')) && ! is_dir(base_path('app/Jobs'))) {
            $this->components->error('app/Jobs is a FILE — pull latest code.');
            $ok = false;
        }

        if (! file_exists(public_path('vendor/livewire/manifest.json'))) {
            $this->components->warn('Livewire assets missing — run php artisan crm:publish-assets');
            $ok = false;
        }

        if (! file_exists(public_path('build/manifest.json'))) {
            $this->components->warn('Vite build missing — npm ci && npm run build (public site CSS)');
        }

        if (! file_exists(public_path('storage'))) {
            $this->components->warn('public/storage symlink missing — run php artisan storage:link');
        }

        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            $this->line('OK database connection');
        } catch (Throwable $exception) {
            $this->components->error('Database failed: '.$exception->getMessage());
            $ok = false;
        }

        if (! class_exists(\App\Jobs\RunWhatsAppCampaignJob::class)) {
            $this->components->error('Cannot autoload App\\Jobs\\RunWhatsAppCampaignJob');
            $ok = false;
        }

        $this->newLine();
        $this->components->info('HTTP kernel (CLI simulation)');
        foreach (['/up', '/admin', '/'] as $uri) {
            $status = $this->simulateHttp($uri, $error);
            if ($status === null) {
                $this->components->error("{$uri} — exception: {$error}");
                $ok = false;
            } elseif ($status >= 500) {
                $this->components->error("{$uri} — HTTP {$status}");
                $ok = false;
            } else {
                $this->line("OK {$uri} — HTTP {$status}");
            }
        }

        $this->newLine();
        $this->components->info('Recent log (storage/logs/laravel.log)');
        $this->showLogTail();

        if ($this->option('probe') || ! $ok) {
            $this->newLine();
            $this->writeWebProbe();
        }

        if (! $ok) {
            $this->newLine();
            $this->line('If CLI simulation is OK but browser/curl still returns 500:');
            $this->line('  1. Run: php artisan crm:doctor --probe  then open the probe URL in the browser');
            $this->line('  2. Fix ownership: chown -R <site-user>:<site-user> storage bootstrap/cache');
            $this->line('  3. CloudPanel → Site → PHP → open_basedir must include the project root (not only /public)');
            $this->line('  4. Reload PHP-FPM after .env or PHP changes');

            return self::FAILURE;
        }

        $this->components->success('Checks passed.');

        return self::SUCCESS;
    }

    protected function pathOwner(string $path): ?string
    {
        if (! function_exists('posix_getpwuid') || ! file_exists($path)) {
            return null;
        }

        $info = posix_getpwuid(fileowner($path));

        return $info['name'] ?? null;
    }

    protected function simulateHttp(string $uri, ?string &$error = null): ?int
    {
        $error = null;

        try {
            /** @var Kernel $kernel */
            $kernel = app(Kernel::class);
            $request = Request::create($uri, 'GET');
            $response = $kernel->handle($request);
            $status = $response->getStatusCode();
            $kernel->terminate($request, $response);

            return $status;
        } catch (Throwable $exception) {
            $error = $exception->getMessage().' @ '.$exception->getFile().':'.$exception->getLine();

            return null;
        }
    }

    protected function showLogTail(): void
    {
        $log = storage_path('logs/laravel.log');

        if (! is_readable($log)) {
            $this->line('(no readable log file)');

            return;
        }

        $lines = @file($log, FILE_IGNORE_NEW_LINES);

        if (! is_array($lines) || $lines === []) {
            $this->line('(empty)');

            return;
        }

        foreach (array_slice($lines, -25) as $line) {
            $this->line($line);
        }
    }

    protected function writeWebProbe(): void
    {
        $token = Str::lower(Str::random(12));
        $filename = "crm-probe-{$token}.php";
        $path = public_path($filename);

        $contents = <<<'PHP'
<?php
header('Content-Type: text/plain; charset=utf-8');
$root = dirname(__DIR__);
$out = [];
$out[] = 'probe_ok';
$out[] = 'php=' . PHP_VERSION;
$out[] = 'sapi=' . php_sapi_name();
$out[] = 'open_basedir=' . (ini_get('open_basedir') ?: 'none');
$out[] = 'vendor=' . (is_file($root . '/vendor/autoload.php') ? 'yes' : 'NO');
$out[] = 'storage_logs_writable=' . (is_writable($root . '/storage/logs') ? 'yes' : 'NO');
try {
    require $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    /** @var \Illuminate\Contracts\Http\Kernel $kernel */
    $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
    $request = \Illuminate\Http\Request::create('/up', 'GET');
    $response = $kernel->handle($request);
    $out[] = 'laravel_up=' . $response->getStatusCode();
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    $out[] = 'laravel_error=' . $e->getMessage();
    $out[] = 'at=' . $e->getFile() . ':' . $e->getLine();
}
file_put_contents($root . '/storage/logs/fpm-probe.log', implode("\n", $out) . "\n---\n", FILE_APPEND);
echo implode("\n", $out);

PHP;

        file_put_contents($path, $contents);

        $this->components->warn('Web probe written (delete after use):');
        $this->line(url($filename));
        $this->line('Or: curl '.url($filename));
    }
}

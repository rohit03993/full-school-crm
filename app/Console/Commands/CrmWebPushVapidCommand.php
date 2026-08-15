<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class CrmWebPushVapidCommand extends Command
{
    protected $signature = 'crm:webpush-vapid';

    protected $description = 'Generate VAPID keys for PWA Web Push and print .env lines';

    public function handle(): int
    {
        if (! class_exists(VAPID::class)) {
            $this->error('Package minishlink/web-push is not installed. Run: composer require minishlink/web-push');

            return self::FAILURE;
        }

        $keys = VAPID::createVapidKeys();

        $this->info('Add these to your .env (then config:clear):');
        $this->newLine();
        $this->line('WEBPUSH_ENABLED=true');
        $this->line('VAPID_SUBJECT=mailto:your-email@institute.example');
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->comment('Keep the private key secret. After deploy, open the installed app once and allow notifications.');

        return self::SUCCESS;
    }
}

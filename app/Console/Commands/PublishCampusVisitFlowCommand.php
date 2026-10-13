<?php

namespace App\Console\Commands;

use App\Services\WhatsAppFlowService;
use Illuminate\Console\Command;

class PublishCampusVisitFlowCommand extends Command
{
    protected $signature = 'whatsapp:publish-campus-visit-flow';

    protected $description = 'Create the campus visit form on WhatsApp and publish it';

    public function handle(WhatsAppFlowService $flows): int
    {
        $result = $flows->publishCampusVisit();

        if (($result['status'] ?? '') !== 'success') {
            $this->error((string) ($result['error'] ?? 'The campus visit form was not published.'));

            return self::FAILURE;
        }

        $this->info('Campus visit form is published. Flow id '.$result['flow_id']);

        return self::SUCCESS;
    }
}

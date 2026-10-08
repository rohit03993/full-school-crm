<?php

namespace App\Console\Commands;

use App\Services\HomeworkCombinedTemplateUpdateService;
use Illuminate\Console\Command;

class CrmUpdateHomeworkCombinedTemplateCommand extends Command
{
    protected $signature = 'crm:update-homework-combined-template';

    protected $description = 'Send the one-subject-per-line wording to the existing homework_combined WhatsApp template';

    public function handle(HomeworkCombinedTemplateUpdateService $updates): int
    {
        $result = $updates->sendUpdatedWording();

        if ($result['status'] === 'success') {
            $this->components->info($result['message']);

            return self::SUCCESS;
        }

        $this->components->error($result['message']);

        return self::FAILURE;
    }
}

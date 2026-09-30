<?php

namespace App\Console\Commands;

use App\Services\HomeworkNotDoneTemplateUpdateService;
use Illuminate\Console\Command;

class CrmUpdateHomeworkNotDoneTemplateCommand extends Command
{
    protected $signature = 'crm:update-homework-not-done-template';

    protected $description = 'Send the spaced homework_not_done wording to WhatsApp under the same template name';

    public function handle(HomeworkNotDoneTemplateUpdateService $updates): int
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

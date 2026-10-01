<?php

namespace App\Filament\Concerns;

use App\Services\ActivityMarksWhatsAppService;

trait ConfirmsDuplicateExamSend
{
    public bool $showDuplicateExamWarning = false;

    public function askDuplicateExamSend(): void
    {
        $this->showDuplicateExamWarning = true;
    }

    public function cancelDuplicateExamSend(): void
    {
        $this->showDuplicateExamWarning = false;
    }

    public function confirmDuplicateExamSend(): void
    {
        $this->showDuplicateExamWarning = false;
        $this->queueWhatsAppCampaign(app(ActivityMarksWhatsAppService::class));
    }
}

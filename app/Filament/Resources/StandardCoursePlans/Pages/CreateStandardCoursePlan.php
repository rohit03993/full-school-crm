<?php

namespace App\Filament\Resources\StandardCoursePlans\Pages;

use App\Enums\StandardCoursePlanStatus;
use App\Filament\Resources\StandardCoursePlans\Concerns\FillsClass11PhysicsCoursePlan;
use App\Filament\Resources\StandardCoursePlans\StandardCoursePlanResource;
use App\Services\StandardCoursePlanService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateStandardCoursePlan extends CreateRecord
{
    use FillsClass11PhysicsCoursePlan;

    protected static string $resource = StandardCoursePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->fillClass11PhysicsAction(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        app(StandardCoursePlanService::class)->assertCanSave($data);

        $data['status'] = StandardCoursePlanStatus::Draft->value;
        $data['created_by_user_id'] = Auth::id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}

<?php

namespace App\Filament\Resources\FaceVerificationRequests\Pages;

use App\Filament\Resources\FaceVerificationRequests\FaceVerificationRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListFaceVerificationRequests extends ListRecords
{
    protected static string $resource = FaceVerificationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

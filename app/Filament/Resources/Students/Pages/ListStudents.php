<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Pages\StudentSearchPage;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;

class ListStudents extends ListRecords
{
    use ShowsCrmPageHint;

    protected static string $resource = StudentResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'students.list';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.students.missing-mobile-alert')
                    ->viewData(fn (): array => [
                        'count' => $this->missingMobileCount(),
                        'filterUrl' => StudentResource::getUrl('index', [
                            'filters' => [
                                'missing_mobile' => [
                                    'value' => true,
                                ],
                            ],
                        ]),
                    ]),
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Action::make('searchStudent')
                ->label('Search Student')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->url(StudentSearchPage::getUrl())
                ->color('gray'),
        ];

        if ($this->missingMobileCount() > 0) {
            $actions[] = Action::make('missingMobile')
                ->label('Missing mobile ('.$this->missingMobileCount().')')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger')
                ->url(StudentResource::getUrl('index', [
                    'filters' => [
                        'missing_mobile' => [
                            'value' => true,
                        ],
                    ],
                ]));
        }

        return $actions;
    }

    protected function missingMobileCount(): int
    {
        return Student::query()->whereNull('mobile')->count();
    }
}

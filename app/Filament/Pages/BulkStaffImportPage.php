<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Exports\StaffImportTemplateExport;
use App\Services\StaffBulkImportService;
use App\Services\StudentImportFileReader;
use App\Support\CrmAccess;
use App\Support\CrmNavigation;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

class BulkStaffImportPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Import staff';

    protected static ?string $title = 'Import Staff';

    protected static ?int $navigationSort = 11;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ADMIN;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var list<string> */
    public array $lastErrors = [];

    public static function canAccess(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::StaffManage);
    }

    public function getSubheading(): ?string
    {
        return 'Upload Staff ID, Name, and Mobile. Staff ID is the Face/RFID PIN and must not match any student roll.';
    }

    public function mount(): void
    {
        $this->form->fill([]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Spreadsheet')
                ->description('Required columns: Staff ID, Name, Mobile. Optional: Designation, Email.')
                ->schema([
                    FileUpload::make('upload')
                        ->label('Excel / CSV file')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                            'text/csv',
                            'text/plain',
                            '.xlsx',
                            '.xls',
                            '.csv',
                        ])
                        ->disk('local')
                        ->directory('temp-staff-imports')
                        ->required()
                        ->storeFiles(false),
                    Placeholder::make('errors')
                        ->label('Row errors')
                        ->content(fn (): HtmlString => new HtmlString(
                            $this->lastErrors === []
                                ? '<span class="text-sm text-gray-500">No errors yet.</span>'
                                : '<ul class="list-disc space-y-1 pl-5 text-sm text-danger-600">'.
                                    collect($this->lastErrors)->take(20)->map(fn (string $e): string => '<li>'.e($e).'</li>')->implode('').
                                    '</ul>'
                        ))
                        ->visible(fn (): bool => $this->lastErrors !== [])
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('staffImportForm')
            ->livewireSubmitHandler('importStaff')
            ->footer([
                Actions::make([
                    Action::make('downloadTemplate')
                        ->label('Download template')
                        ->color('gray')
                        ->outlined()
                        ->action(fn (): BinaryFileResponse => Excel::download(
                            new StaffImportTemplateExport,
                            'staff-import-template.xlsx',
                        )),
                    Action::make('import')
                        ->label('Import staff')
                        ->submit('importStaff'),
                ])->alignment(Alignment::Start),
            ]);
    }

    public function importStaff(
        StudentImportFileReader $reader,
        StaffBulkImportService $importer,
    ): void {
        $file = $this->data['upload'] ?? null;

        if (! $file instanceof TemporaryUploadedFile) {
            Notification::make()->title('Choose a file')->warning()->send();

            return;
        }

        $parsed = $reader->storeAndParse($file);
        $columns = $importer->guessColumnIndexes($parsed['headers']);
        $result = $importer->importRows(Auth::user(), $parsed['rows'], $columns);
        $reader->deleteStoredFile($parsed['path'] ?? null);

        $this->lastErrors = $result['errors'];
        $this->form->fill([]);

        $body = $result['imported'].' created, '.$result['updated'].' updated';

        if ($result['errors'] !== []) {
            $body .= '. '.count($result['errors']).' row(s) failed — see list below.';
        }

        Notification::make()
            ->title('Staff import finished')
            ->body($body)
            ->success()
            ->send();
    }
}

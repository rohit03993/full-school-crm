<?php

namespace App\Filament\Resources\HomeworkAssignments;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresAnyCrmPermission;
use App\Filament\Resources\HomeworkAssignments\Pages\CreateHomeworkAssignment;
use App\Filament\Resources\HomeworkAssignments\Pages\ListHomeworkAssignments;
use App\Filament\Resources\HomeworkAssignments\Pages\ViewHomeworkAssignment;
use App\Filament\Support\CrmTable;
use App\Models\Batch;
use App\Models\HomeworkAssignment;
use App\Models\WhatsAppTemplate;
use App\Services\HomeworkAssignmentService;
use App\Support\CrmAccess;
use App\Support\CrmNavigation;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class HomeworkAssignmentResource extends Resource
{
    use RequiresAnyCrmPermission;

    /**
     * @return list<CrmPermission>
     */
    protected static function anyCrmPermissions(): array
    {
        return [CrmPermission::HomeworkManage];
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::Homework;
    }

    protected static ?string $model = HomeworkAssignment::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Homework';

    protected static ?string $modelLabel = 'Homework';

    protected static ?string $pluralModelLabel = 'Homework';

    protected static ?int $navigationSort = 45;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function canCreate(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::HomeworkManage);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Assign homework')
                ->description('One batch per assignment. Students view homework in the student portal after logging in with their mobile number.')
                ->schema([
                    Select::make('batch_id')
                        ->label('Batch')
                        ->options(fn (): array => Batch::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->required(),
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->required()
                        ->rows(4)
                        ->columnSpanFull(),
                    FileUpload::make('attachment')
                        ->label('PDF or image (optional)')
                        ->disk('public')
                        ->directory('homework')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ])
                        ->maxSize(10240)
                        ->columnSpanFull(),
                    Toggle::make('send_whatsapp')
                        ->label('Send WhatsApp with portal link')
                        ->helperText('Sends name, roll number, homework title, and link to open homework in the student portal.')
                        ->default(true)
                        ->live(),
                    Select::make('whatsapp_template_name')
                        ->label('WhatsApp template')
                        ->options(fn (): array => WhatsAppTemplate::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'name')
                            ->all())
                        ->searchable()
                        ->visible(fn (callable $get): bool => (bool) $get('send_whatsapp'))
                        ->helperText('Live API campaign name in Pal Digital (4 params: name, roll, title, link).'),
                ])
                ->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Homework details')
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('batch.name')->label('Batch'),
                        TextEntry::make('content_type')->badge(),
                        TextEntry::make('published_at')->dateTime('d M Y, h:i A'),
                        TextEntry::make('description')->columnSpanFull(),
                        TextEntry::make('portalUrl')
                            ->label('Student portal link')
                            ->state(fn (HomeworkAssignment $record): string => $record->portalUrl())
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('viewPercentage')
                            ->label('Viewed')
                            ->state(fn (HomeworkAssignment $record): string => $record->viewedStudentsCount()
                                .' / '.$record->totalStudentsCount()
                                .' ('.$record->viewPercentage().'%)'),
                        ViewComponent::make('filament.resources.homework-assignments.attachment')
                            ->viewData(fn (HomeworkAssignment $record): array => [
                                'record' => $record,
                            ])
                            ->columnSpanFull()
                            ->visible(fn (HomeworkAssignment $record): bool => $record->hasFile()),
                    ])
                    ->columns(2),
                ViewComponent::make('filament.resources.homework-assignments.view-report')
                    ->viewData(fn (HomeworkAssignment $record): array => [
                        'report' => app(HomeworkAssignmentService::class)->paginatedViewReport($record),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->limit(40),
                TextColumn::make('batch.name')->label('Batch')->searchable()->sortable(),
                TextColumn::make('content_type')->badge(),
                TextColumn::make('published_at')->label('Published')->dateTime('d M Y')->sortable(),
                TextColumn::make('viewedStudentsCount')
                    ->label('Viewed')
                    ->state(fn (HomeworkAssignment $record): string => $record->viewedStudentsCount().' / '.$record->totalStudentsCount()),
                TextColumn::make('viewPercentage')
                    ->label('%')
                    ->suffix('%')
                    ->state(fn (HomeworkAssignment $record): int => $record->viewPercentage()),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('batch_id')
                    ->label('Batch')
                    ->options(fn (): array => Batch::query()->orderBy('name')->pluck('name', 'id')->all()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomeworkAssignments::route('/'),
            'create' => CreateHomeworkAssignment::route('/create'),
            'view' => ViewHomeworkAssignment::route('/{record}'),
        ];
    }
}

<?php

namespace App\Filament\Resources\MetaWhatsAppTemplates;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Resources\MetaWhatsAppTemplates\Pages\CreateMetaWhatsAppTemplate;
use App\Filament\Resources\MetaWhatsAppTemplates\Pages\EditMetaWhatsAppTemplate;
use App\Filament\Resources\MetaWhatsAppTemplates\Pages\ListMetaWhatsAppTemplates;
use App\Filament\Support\CrmTable;
use App\Models\MetaWhatsAppTemplate;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppTemplateParamResolver;
use App\Support\CrmNavigation;
use App\Support\MetaWhatsAppTemplateBuilder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class MetaWhatsAppTemplateResource extends Resource
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::MetaWhatsappSettings;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    protected static ?string $model = MetaWhatsAppTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Templates';

    protected static ?string $modelLabel = 'WhatsApp Template';

    protected static ?string $pluralModelLabel = 'WhatsApp Templates';

    protected static ?int $navigationSort = 15;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_META_WHATSAPP;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Submit to Meta')
                ->description('Creates a template on your WhatsApp Business account. Meta usually approves within minutes to 24 hours — click Sync on the list page to refresh status.')
                ->schema(static::createFormSchema())
                ->visible(fn (?MetaWhatsAppTemplate $record): bool => $record === null),
            Section::make('Template details')
                ->schema(static::editFormSchema())
                ->visible(fn (?MetaWhatsAppTemplate $record): bool => $record !== null),
        ]);
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    protected static function createFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('Template name')
                ->required()
                ->maxLength(64)
                ->helperText('Lowercase letters, numbers, underscores — e.g. parent_checkin')
                ->live(onBlur: true)
                ->afterStateUpdated(fn (callable $set, ?string $state) => $set('name', MetaWhatsAppTemplateBuilder::normalizeName((string) $state))),
            Select::make('language')
                ->options([
                    'en' => 'English (en)',
                    'en_US' => 'English US (en_US)',
                    'hi' => 'Hindi (hi)',
                ])
                ->default('en')
                ->required(),
            Select::make('category')
                ->options([
                    'UTILITY' => 'Utility',
                    'MARKETING' => 'Marketing',
                    'AUTHENTICATION' => 'Authentication (OTP)',
                ])
                ->default('UTILITY')
                ->required(),
            TextInput::make('header_text')
                ->label('Header (optional)')
                ->maxLength(60)
                ->helperText('Plain text only — no variables.'),
            Textarea::make('body_text')
                ->label('Message body')
                ->required()
                ->rows(5)
                ->helperText('Use {{1}} for student name, {{2}} for roll number, etc. Meta requires sample values for each variable.')
                ->columnSpanFull(),
            TextInput::make('body_examples_csv')
                ->label('Sample values')
                ->helperText('Comma-separated, in order of {{1}}, {{2}}, … — e.g. Rohit Sharma, 12-A-042')
                ->placeholder('Rohit Sharma, Class 12-A')
                ->required(fn (Get $get): bool => MetaWhatsAppTemplateBuilder::positionalPlaceholderOrder((string) $get('body_text')) !== [])
                ->columnSpanFull(),
            TextInput::make('footer_text')
                ->label('Footer (optional)')
                ->maxLength(60),
            Toggle::make('allow_category_change')
                ->label('Allow Meta to recategorize')
                ->default(true)
                ->helperText('Recommended — Meta may adjust UTILITY vs MARKETING during review.'),
        ];
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    protected static function editFormSchema(): array
    {
        return [
            Placeholder::make('status_display')
                ->label('Status')
                ->content(fn (MetaWhatsAppTemplate $record): HtmlString => new HtmlString(
                    '<span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset '
                    .match (strtoupper($record->status)) {
                        'APPROVED' => 'bg-success-50 text-success-700 ring-success-600/20',
                        'PENDING' => 'bg-warning-50 text-warning-700 ring-warning-600/20',
                        'REJECTED' => 'bg-danger-50 text-danger-700 ring-danger-600/20',
                        default => 'bg-gray-50 text-gray-700 ring-gray-500/20',
                    }
                    .'">'.e($record->status).'</span>'
                )),
            TextInput::make('name')
                ->disabled(),
            TextInput::make('language')
                ->disabled(),
            Textarea::make('body')
                ->label('Message body')
                ->disabled()
                ->rows(4)
                ->columnSpanFull(),
            Repeater::make('param_mapping_rows')
                ->label('Parameter mapping')
                ->helperText('Maps {{1}}, {{2}}, … to student data when sending campaigns and profile messages.')
                ->schema([
                    Select::make('source')
                        ->label('Data source')
                        ->options(WhatsAppTemplateParamResolver::sourceOptions())
                        ->searchable()
                        ->required(),
                ])
                ->defaultItems(0)
                ->maxItems(10)
                ->visible(fn (MetaWhatsAppTemplate $record): bool => (int) $record->param_count > 0)
                ->columnSpanFull(),
            Toggle::make('is_active')
                ->label('Active for sending')
                ->helperText('Only approved templates can be sent. Turn off to hide from campaign pickers.')
                ->visible(fn (MetaWhatsAppTemplate $record): bool => strtoupper($record->status) === 'APPROVED'),
        ];
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('language')
                    ->label('Lang')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtoupper($state)) {
                        'APPROVED' => 'success',
                        'PENDING' => 'warning',
                        'REJECTED' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('param_count')
                    ->label('Params')
                    ->sortable(),
                TextColumn::make('body')
                    ->limit(50)
                    ->toggleable(),
                TextColumn::make('synced_at')
                    ->label('Last sync')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'APPROVED' => 'Approved',
                        'PENDING' => 'Pending',
                        'REJECTED' => 'Rejected',
                    ]),
            ])
            ->defaultSort('name');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeMappings(array $data): array
    {
        $rows = $data['param_mapping_rows'] ?? [];
        $mappings = collect($rows)
            ->pluck('source')
            ->filter()
            ->values()
            ->all();

        $data['param_mappings'] = $mappings;
        unset($data['param_mapping_rows']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function expandMappings(array $data): array
    {
        $data['param_mapping_rows'] = collect($data['param_mappings'] ?? [])
            ->map(fn (?string $source): array => ['source' => $source])
            ->values()
            ->all();

        return $data;
    }

    public static function mirrorMappingsToWhatsAppTemplate(MetaWhatsAppTemplate $record): void
    {
        if (strtoupper($record->status) !== 'APPROVED') {
            return;
        }

        WhatsAppTemplate::query()->updateOrCreate(
            ['name' => $record->name],
            [
                'description' => 'Synced from Meta ('.$record->language.')',
                'param_count' => (int) $record->param_count,
                'body' => $record->body,
                'param_mappings' => $record->param_mappings,
                'provider_meta' => array_merge(
                    $record->provider_meta ?? [],
                    ['meta_language' => $record->language, 'source' => 'meta'],
                ),
                'is_active' => (bool) $record->is_active,
                'synced_at' => now(),
            ],
        );
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMetaWhatsAppTemplates::route('/'),
            'create' => CreateMetaWhatsAppTemplate::route('/create'),
            'edit' => EditMetaWhatsAppTemplate::route('/{record}/edit'),
        ];
    }
}

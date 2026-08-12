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
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeeReminderWhatsAppTemplate;
use App\Support\HomeworkNotDoneWhatsAppTemplate;
use App\Support\HomeworkShareWhatsAppTemplate;
use App\Support\LoginOtpWhatsAppTemplate;
use App\Support\StaffPunchWhatsAppTemplate;
use App\Support\MetaWhatsAppTemplateBuilder;
use App\Support\MetaWhatsAppTemplateVariableHelper;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = 'WhatsApp Template';

    protected static ?string $pluralModelLabel = 'WhatsApp Templates';

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::whatsAppTemplates();
    }

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
            Placeholder::make('fee_reminder_copy_guide')
                ->hiddenLabel()
                ->content(new HtmlString(
                    '<div class="space-y-2">'
                    .'<div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-white/10 dark:bg-white/5">'
                    .'<p class="font-bold text-gray-950 dark:text-white">Any template (generic)</p>'
                    .'<p class="mt-1 text-xs text-gray-600 dark:text-gray-300">'
                    .'Use <code class="text-xs">{{1}}</code>, <code class="text-xs">{{2}}</code>, … in the body. Sample fields appear as Variable 1, Variable 2, … — edit the samples for Meta approval. Map CRM fields after approve on the Edit screen.'
                    .'</p></div>'
                    .'<div class="rounded-xl border border-amber-200/70 bg-amber-50/50 px-4 py-3 text-sm dark:border-amber-500/20 dark:bg-amber-500/5">'
                    .'<p class="font-bold text-gray-950 dark:text-white">Known CRM presets (optional)</p>'
                    .'<p class="mt-1 text-xs text-gray-600 dark:text-gray-300">'
                    .'Name <code class="text-xs">'.e(FeeReminderWhatsAppTemplate::NAME).'</code>, <code class="text-xs">'.e(HomeworkNotDoneWhatsAppTemplate::NAME).'</code>, <code class="text-xs">'.e(HomeworkShareWhatsAppTemplate::NAME).'</code> / <code class="text-xs">homework_update</code>, <code class="text-xs">'.e(StaffPunchWhatsAppTemplate::IN_NAME).'</code> / <code class="text-xs">'.e(StaffPunchWhatsAppTemplate::OUT_NAME).'</code>, or <code class="text-xs">'.e(LoginOtpWhatsAppTemplate::NAME).'</code>, leave body blank and blur the name — body + samples auto-fill. <strong>login_otp</strong> is submitted as Authentication (Copy code), not Utility.'
                    .'</p></div></div>'
                ))
                ->columnSpanFull(),
            TextInput::make('name')
                ->label('Template name')
                ->required()
                ->maxLength(64)
                ->helperText('Custom names are fine. Presets: '.FeeReminderWhatsAppTemplate::NAME.', '.HomeworkNotDoneWhatsAppTemplate::NAME.', '.HomeworkShareWhatsAppTemplate::NAME.', '.StaffPunchWhatsAppTemplate::IN_NAME.', '.StaffPunchWhatsAppTemplate::OUT_NAME.', '.LoginOtpWhatsAppTemplate::NAME)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                    $normalized = MetaWhatsAppTemplateBuilder::normalizeName((string) $state);
                    $set('name', $normalized);

                    if (FeeReminderWhatsAppTemplate::looksLikeName($normalized)) {
                        $set('category', FeeReminderWhatsAppTemplate::CATEGORY);
                        $set('body_text', FeeReminderWhatsAppTemplate::BODY);
                        $set('body_variable_samples', FeeReminderWhatsAppTemplate::sampleRows());

                        return;
                    }

                    if (HomeworkNotDoneWhatsAppTemplate::looksLikeName($normalized)) {
                        $set('category', HomeworkNotDoneWhatsAppTemplate::CATEGORY);
                        $set('body_text', HomeworkNotDoneWhatsAppTemplate::BODY);
                        $set('body_variable_samples', HomeworkNotDoneWhatsAppTemplate::sampleRows());

                        return;
                    }

                    if (HomeworkShareWhatsAppTemplate::looksLikeName($normalized)) {
                        $set('category', HomeworkShareWhatsAppTemplate::CATEGORY);
                        $set('body_text', HomeworkShareWhatsAppTemplate::BODY);
                        $set('body_variable_samples', HomeworkShareWhatsAppTemplate::sampleRows());

                        return;
                    }

                    if (StaffPunchWhatsAppTemplate::looksLikeInName($normalized)) {
                        $set('category', StaffPunchWhatsAppTemplate::CATEGORY);
                        $set('body_text', StaffPunchWhatsAppTemplate::IN_BODY);
                        $set('body_variable_samples', StaffPunchWhatsAppTemplate::sampleRows());

                        return;
                    }

                    if (StaffPunchWhatsAppTemplate::looksLikeOutName($normalized)) {
                        $set('category', StaffPunchWhatsAppTemplate::CATEGORY);
                        $set('body_text', StaffPunchWhatsAppTemplate::OUT_BODY);
                        $set('body_variable_samples', StaffPunchWhatsAppTemplate::sampleRows());

                        return;
                    }

                    if (LoginOtpWhatsAppTemplate::looksLikeName($normalized)) {
                        $set('category', LoginOtpWhatsAppTemplate::CATEGORY);
                        $set('body_text', LoginOtpWhatsAppTemplate::BODY);
                        $set('body_variable_samples', LoginOtpWhatsAppTemplate::sampleRows());
                        $set('allow_category_change', false);

                        return;
                    }

                    if (filled(trim((string) $get('body_text')))) {
                        $set(
                            'body_variable_samples',
                            MetaWhatsAppTemplateVariableHelper::syncRowsFromBody(
                                (string) $get('body_text'),
                                $get('body_variable_samples') ?? [],
                                $normalized,
                            ),
                        );
                    }
                }),
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
                ->required()
                ->live()
                ->helperText('Authentication is required for login OTP. CRM submits Meta’s Copy code OTP template — not a custom Utility body.'),
            TextInput::make('header_text')
                ->label('Header (optional)')
                ->maxLength(60)
                ->helperText('Plain text only — no variables.')
                ->visible(fn (Get $get): bool => ! MetaWhatsAppTemplateBuilder::isAuthenticationOtp((string) $get('name'), (string) $get('category'))),
            Textarea::make('body_text')
                ->label('Message body')
                ->required(fn (Get $get): bool => ! MetaWhatsAppTemplateBuilder::isAuthenticationOtp((string) $get('name'), (string) $get('category')))
                ->rows(8)
                ->helperText(fn (Get $get): string => MetaWhatsAppTemplateBuilder::isAuthenticationOtp((string) $get('name'), (string) $get('category'))
                    ? 'Preview only. Create submits an Authentication Copy-code OTP template to Meta (fixed OTP text + Copy code button).'
                    : 'Any Meta template: use {{1}}, {{2}}, … Samples below are for approval only. Optional presets auto-fill when you use a known CRM template name.')
                ->live(debounce: 400)
                ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                    $set(
                        'body_variable_samples',
                        MetaWhatsAppTemplateVariableHelper::syncRowsFromBody(
                            (string) $state,
                            $get('body_variable_samples') ?? [],
                            (string) $get('name'),
                        ),
                    );
                })
                ->columnSpanFull(),
            Section::make('Template variables')
                ->description('Meta needs one sample per {{n}}. Labels are hints only — edit samples freely. After approval, map params to CRM fields on Edit.')
                ->schema([
                    Repeater::make('body_variable_samples')
                        ->label('')
                        ->schema([
                            TextInput::make('index')
                                ->hidden()
                                ->dehydrated(),
                            TextInput::make('label')
                                ->hidden()
                                ->dehydrated(),
                            TextInput::make('example')
                                ->label(fn (Get $get): string => '{{'.$get('index').'}} — '.($get('label') ?: 'Variable'))
                                ->required()
                                ->maxLength(256)
                                ->live(onBlur: true),
                        ])
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columnSpanFull(),
                    Placeholder::make('body_preview')
                        ->label('Preview with sample values')
                        ->content(function (Get $get): HtmlString {
                            $preview = MetaWhatsAppTemplateVariableHelper::previewBody(
                                (string) $get('body_text'),
                                $get('body_variable_samples') ?? [],
                            );

                            return new HtmlString(
                                '<div class="whitespace-pre-wrap rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800 dark:border-white/10 dark:bg-white/5 dark:text-gray-100">'
                                .e($preview)
                                .'</div>'
                            );
                        })
                        ->columnSpanFull(),
                ])
                ->visible(fn (Get $get): bool => MetaWhatsAppTemplateVariableHelper::variableCount((string) $get('body_text')) > 0)
                ->columnSpanFull(),
            TextInput::make('footer_text')
                ->label('Footer (optional)')
                ->maxLength(60)
                ->visible(fn (Get $get): bool => ! MetaWhatsAppTemplateBuilder::isAuthenticationOtp((string) $get('name'), (string) $get('category'))),
            Toggle::make('allow_category_change')
                ->label('Allow Meta to recategorize')
                ->default(true)
                ->visible(fn (Get $get): bool => ! MetaWhatsAppTemplateBuilder::isAuthenticationOtp((string) $get('name'), (string) $get('category')))
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
    public static function normalizeCreateFormData(array $data): array
    {
        $samples = $data['body_variable_samples'] ?? [];

        if (is_array($samples) && $samples !== []) {
            $data['body_examples'] = MetaWhatsAppTemplateVariableHelper::rowsToExamplesList($samples);
            // Keep CSV for older callers / logs; prefer body_examples when submitting.
            $data['body_examples_csv'] = MetaWhatsAppTemplateVariableHelper::rowsToExamplesCsv($samples);
        }

        unset($data['body_variable_samples']);

        return $data;
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

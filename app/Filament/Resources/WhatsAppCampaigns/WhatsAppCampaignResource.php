<?php

namespace App\Filament\Resources\WhatsAppCampaigns;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Enums\WhatsAppAudienceType;
use App\Enums\WhatsAppCampaignStatus;
use App\Filament\Resources\WhatsAppCampaigns\Pages\CreateWhatsAppCampaign;
use App\Filament\Resources\WhatsAppCampaigns\Pages\ListWhatsAppCampaigns;
use App\Filament\Resources\WhatsAppCampaigns\Pages\ViewWhatsAppCampaign;
use App\Filament\Resources\WhatsAppCampaigns\RelationManagers\RecipientsRelationManager;
use App\Filament\Support\CrmTable;
use App\Models\Batch;
use App\Models\Course;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppCampaignService;
use App\Support\WhatsAppCampaignFormHelper;
use App\Support\WhatsAppCampaignViewHelper;
use App\Support\CrmNavigation;
use App\Support\InstituteProfile;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use UnitEnum;

class WhatsAppCampaignResource extends Resource
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::WhatsappCampaigns;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    protected static ?string $model = WhatsAppCampaign::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'WhatsApp Campaigns';

    protected static ?string $modelLabel = 'WhatsApp Campaign';

    protected static ?string $pluralModelLabel = 'WhatsApp Campaigns';

    protected static ?int $navigationSort = 30;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_MESSAGING;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('1. Template')
                ->description('Choose the WhatsApp message to send. Student name, roll number, and batch are filled automatically per recipient.')
                ->schema([
                    TextInput::make('name')
                        ->label('Campaign name')
                        ->required()
                        ->maxLength(255)
                        ->default(fn (): string => WhatsAppCampaignFormHelper::generateDefaultName())
                        ->helperText('Auto-generated as YYYY-MM-DD-001. Edit if needed.'),
                    Select::make('whatsapp_template_id')
                        ->label('WhatsApp template')
                        ->options(fn (): array => WhatsAppTemplate::query()
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (WhatsAppTemplate $template): array => [
                                $template->id => $template->name.' ('.$template->param_count.' param'
                                    .($template->param_count === 1 ? '' : 's').')',
                            ])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->native(false)
                        ->live()
                        ->dehydrateStateUsing(fn ($state): ?int => filled($state) ? (int) $state : null)
                        ->afterStateUpdated(function ($state, callable $set): void {
                            $set('template_manual_params', []);
                            $set('campaign_variables', WhatsAppCampaignFormHelper::defaultCampaignVariables(
                                filled($state) ? (int) $state : null,
                            ));
                        })
                        ->placeholder('Choose a template…'),
                    Placeholder::make('template_preview_card')
                        ->label('')
                        ->content(fn (Get $get): HtmlString => WhatsAppCampaignFormHelper::renderTemplatePreviewCard(
                            filled($get('whatsapp_template_id')) ? (int) $get('whatsapp_template_id') : null,
                        ))
                        ->visible(fn (Get $get): bool => filled($get('whatsapp_template_id')))
                        ->columnSpanFull(),
                ])
                ->columns(1),
            Section::make('2. Audience')
                ->description('Pick who receives this campaign. Only students with a mobile number are included.')
                ->schema([
                    Select::make('audience_type')
                        ->label('Send to')
                        ->options([
                            WhatsAppAudienceType::Batch->value => 'One batch',
                            WhatsAppAudienceType::Course->value => 'Whole class / course',
                        ])
                        ->default(WhatsAppAudienceType::Batch->value)
                        ->required()
                        ->native(false)
                        ->live(),
                    Select::make('course_id')
                        ->label('Class / course')
                        ->options(fn (): array => InstituteProfile::activeCourseOptions())
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->live()
                        ->dehydrateStateUsing(fn ($state): ?int => filled($state) ? (int) $state : null)
                        ->placeholder('Select class…'),
                    Select::make('batch_id')
                        ->label('Batch')
                        ->options(function (Get $get): array {
                            $courseId = $get('course_id');

                            if (blank($courseId)) {
                                return [];
                            }

                            return Batch::query()
                                ->where('course_id', $courseId)
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Batch $batch): array => [$batch->id => $batch->selectLabel()])
                                ->all();
                        })
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->dehydrateStateUsing(fn ($state): ?int => filled($state) ? (int) $state : null)
                        ->placeholder('Select batch…')
                        ->visible(fn (Get $get): bool => $get('audience_type') === WhatsAppAudienceType::Batch->value)
                        ->required(fn (Get $get): bool => $get('audience_type') === WhatsAppAudienceType::Batch->value),
                    Placeholder::make('estimated_recipients')
                        ->label('')
                        ->content(function (Get $get): HtmlString|string {
                            if (blank($get('course_id'))) {
                                return 'Select a class to see how many students will receive this message.';
                            }

                            if ($get('audience_type') === WhatsAppAudienceType::Batch->value && blank($get('batch_id'))) {
                                return 'Select a batch to see how many students will receive this message.';
                            }

                            $count = app(WhatsAppCampaignService::class)->estimateAudienceCount([
                                'audience_type' => $get('audience_type'),
                                'course_id' => $get('course_id'),
                                'batch_id' => $get('batch_id'),
                                'academic_session_id' => null,
                            ]);

                            return WhatsAppCampaignFormHelper::renderRecipientEstimate($count, $count > 50);
                        })
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn (Get $get): bool => filled($get('whatsapp_template_id'))),
            Section::make('3. Campaign details')
                ->description('Fill the fields below once. They apply to every message in this campaign.')
                ->schema(fn (Get $get): array => WhatsAppCampaignFormHelper::messageDetailFields(
                    filled($get('whatsapp_template_id')) ? (int) $get('whatsapp_template_id') : null,
                ))
                ->columns(2)
                ->key(fn (Get $get): string => 'message-details-'.($get('whatsapp_template_id') ?? 'none'))
                ->visible(fn (Get $get): bool => filled($get('whatsapp_template_id'))),
            Section::make('4. Send')
                ->description('Review the summary below, then create the campaign. Large sends may take a few minutes to finish.')
                ->schema([
                    Toggle::make('send_immediately')
                        ->label('Send immediately after create')
                        ->helperText('When off, the campaign is saved as draft — start it later from the campaign view.')
                        ->default(true)
                        ->dehydrated(false)
                        ->inline(false),
                ])
                ->visible(fn (Get $get): bool => filled($get('whatsapp_template_id'))),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campaign summary')
                ->schema([
                    TextEntry::make('delivery_stats')
                        ->label('')
                        ->state(fn (WhatsAppCampaign $record): HtmlString => WhatsAppCampaignViewHelper::renderStatsDashboard($record))
                        ->columnSpanFull(),
                ]),
            Section::make('Send progress')
                ->description('Updates automatically while messages are sending.')
                ->schema([
                    TextEntry::make('send_progress')
                        ->label('')
                        ->state(fn (WhatsAppCampaign $record): HtmlString => WhatsAppCampaignViewHelper::renderSendProgress($record))
                        ->columnSpanFull(),
                ])
                ->visible(fn (WhatsAppCampaign $record): bool => WhatsAppCampaignViewHelper::isInProgress($record)),
            Section::make('Campaign details')
                ->schema([
                    TextEntry::make('name')->label('Campaign name'),
                    TextEntry::make('template.name')->label('Template'),
                    TextEntry::make('status')
                        ->badge()
                        ->color(fn (WhatsAppCampaignStatus|string $state): string => match ($state instanceof WhatsAppCampaignStatus ? $state->value : $state) {
                            'completed' => 'success',
                            'paused' => 'danger',
                            'running', 'queued' => 'warning',
                            default => 'gray',
                        }),
                    TextEntry::make('audience_type')
                        ->label('Audience')
                        ->badge()
                        ->color('info'),
                    TextEntry::make('course.name')->label('Course'),
                    TextEntry::make('batch.name')->label('Batch'),
                    TextEntry::make('shot_at')->label('Started')->dateTime('d M Y, h:i A'),
                    TextEntry::make('finished_at')->label('Finished')->dateTime('d M Y, h:i A'),
                ])
                ->columns(2)
                ->collapsed(fn (WhatsAppCampaign $record): bool => (int) $record->total_recipients > 15),
            Section::make('Message preview')
                ->description('Shows the actual text sent when available; otherwise the Pal Digital template sample.')
                ->schema([
                    TextEntry::make('resolved_message_preview')
                        ->label('')
                        ->state(function (WhatsAppCampaign $record): ?string {
                            $sent = $record->recipients()
                                ->whereNotNull('message_sent')
                                ->where('message_sent', '!=', '')
                                ->orderBy('id')
                                ->value('message_sent');

                            return filled($sent) ? (string) $sent : $record->template?->body;
                        })
                        ->prose()
                        ->columnSpanFull(),
                ])
                ->collapsed()
                ->visible(fn (WhatsAppCampaign $record): bool => filled($record->template?->body)
                    || $record->recipients()->whereNotNull('message_sent')->exists()),
            Section::make('Parameters used')
                ->schema([
                    TextEntry::make('campaign_params_display')
                        ->label('')
                        ->state(fn (WhatsAppCampaign $record): HtmlString => WhatsAppCampaignViewHelper::renderCampaignVariables($record))
                        ->columnSpanFull(),
                ])
                ->collapsed()
                ->visible(fn (WhatsAppCampaign $record): bool => WhatsAppCampaignViewHelper::hasCampaignVariables($record)),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RecipientsRelationManager::class,
        ];
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('template.name')->label('Template')->limit(30),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (WhatsAppCampaignStatus|string $state): string => match ($state instanceof WhatsAppCampaignStatus ? $state->value : $state) {
                        'completed' => 'success',
                        'paused' => 'danger',
                        'running', 'queued' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('total_recipients')->label('Recipients')->numeric()->sortable(),
                TextColumn::make('sent_count')
                    ->label('Sent')
                    ->numeric()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('failed_count')
                    ->label('Failed')
                    ->numeric()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('course.name')->label('Course')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('batch.name')->label('Batch')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('shot_at')->label('Sent on')->dateTime('d M Y, h:i A')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppCampaigns::route('/'),
            'create' => CreateWhatsAppCampaign::route('/create'),
            'view' => ViewWhatsAppCampaign::route('/{record}'),
        ];
    }
}

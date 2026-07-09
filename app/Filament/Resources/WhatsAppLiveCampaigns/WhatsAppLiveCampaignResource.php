<?php

namespace App\Filament\Resources\WhatsAppLiveCampaigns;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\WhatsAppLiveCampaignStatus;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Resources\WhatsAppLiveCampaigns\Pages\CreateWhatsAppLiveCampaign;
use App\Filament\Resources\WhatsAppLiveCampaigns\Pages\EditWhatsAppLiveCampaign;
use App\Filament\Resources\WhatsAppLiveCampaigns\Pages\ListWhatsAppLiveCampaigns;
use App\Filament\Support\CrmTable;
use App\Models\MetaWhatsAppTemplate;
use App\Models\WhatsAppLiveCampaign;
use App\Services\WhatsAppIntegrationApiService;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class WhatsAppLiveCampaignResource extends Resource
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

    protected static ?string $model = WhatsAppLiveCampaign::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = 'Live API campaign';

    protected static ?string $pluralModelLabel = 'Live API campaigns';

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::whatsAppQuickCampaigns();
    }

    protected static ?int $navigationSort = 18;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_META_WHATSAPP;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campaign')
                ->description('The campaign name is sent as campaignName in the external API (AiSensy-compatible). Must match exactly when triggering from other systems.')
                ->schema([
                    TextInput::make('name')
                        ->label('Campaign name')
                        ->required()
                        ->maxLength(120)
                        ->unique(ignoreRecord: true)
                        ->helperText('e.g. parent_checkin, homework_notify — use this in automations and API calls.'),
                    Select::make('meta_whatsapp_template_id')
                        ->label('Linked Meta template')
                        ->options(fn (): array => MetaWhatsAppTemplate::query()
                            ->where('status', 'APPROVED')
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (MetaWhatsAppTemplate $template): array => [
                                $template->id => $template->name.' ('.$template->language.', '.$template->param_count.' param'
                                    .((int) $template->param_count === 1 ? '' : 's').')',
                            ])
                            ->all())
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->disabled(fn (?WhatsAppLiveCampaign $record): bool => $record?->isLive() ?? false)
                        ->helperText('Only APPROVED templates. Pause the campaign to change the template.'),
                    Textarea::make('description')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('External API')
                ->description('AiSensy-compatible trigger for attendance systems, Taskbook, etc.')
                ->schema([
                    Placeholder::make('api_docs')
                        ->label('')
                        ->content(function (WhatsAppIntegrationApiService $api): HtmlString {
                            $endpoint = e($api->apiEndpointUrl());
                            $keyStatus = $api->hasStoredKey()
                                ? 'Key saved: <code class="rounded bg-gray-100 px-1 font-mono text-xs dark:bg-gray-800">'.e($api->maskedKey()).'</code>'
                                : 'No API key yet — generate one from the list page.';

                            return new HtmlString(
                                '<div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">'
                                .'<p><strong>POST</strong> <code class="rounded bg-gray-100 px-1 font-mono text-xs dark:bg-gray-800">'.$endpoint.'</code></p>'
                                .'<p>'.$keyStatus.'</p>'
                                .'<pre class="overflow-x-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">'
                                .e(json_encode([
                                    'apiKey' => 'crm.<uuid>.<secret>',
                                    'campaignName' => 'your_campaign_name',
                                    'destination' => '919876543210',
                                    'userName' => 'Student Name',
                                    'templateParams' => ['Rohit Sharma', '9:15 AM'],
                                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                                .'</pre></div>'
                            );
                        })
                        ->columnSpanFull(),
                ])
                ->collapsed()
                ->visible(fn (?WhatsAppLiveCampaign $record): bool => $record !== null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (WhatsAppLiveCampaign $record): ?string => $record->metaTemplate?->name),
                TextColumn::make('metaTemplate.language')
                    ->label('Lang')
                    ->toggleable(),
                TextColumn::make('metaTemplate.param_count')
                    ->label('Params')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (WhatsAppLiveCampaignStatus $state): string => $state->label())
                    ->color(fn (WhatsAppLiveCampaignStatus $state): string => match ($state) {
                        WhatsAppLiveCampaignStatus::Live => 'success',
                        WhatsAppLiveCampaignStatus::Draft => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('went_live_at')
                    ->label('Live since')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(WhatsAppLiveCampaignStatus::cases())
                        ->mapWithKeys(fn (WhatsAppLiveCampaignStatus $status): array => [
                            $status->value => $status->label(),
                        ])
                        ->all()),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppLiveCampaigns::route('/'),
            'create' => CreateWhatsAppLiveCampaign::route('/create'),
            'edit' => EditWhatsAppLiveCampaign::route('/{record}/edit'),
        ];
    }
}

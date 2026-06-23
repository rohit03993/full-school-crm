<?php

namespace App\Filament\Resources\WhatsAppTemplates;

use App\Filament\Resources\WhatsAppTemplates\Pages\CreateWhatsAppTemplate;
use App\Filament\Resources\WhatsAppTemplates\Pages\EditWhatsAppTemplate;
use App\Filament\Resources\WhatsAppTemplates\Pages\ListWhatsAppTemplates;
use App\Filament\Support\CrmTable;
use App\Models\WhatsAppTemplate;
use App\Support\CrmNavigation;
use App\Services\WhatsAppTemplateParamResolver;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class WhatsAppTemplateResource extends Resource
{
    protected static ?string $model = WhatsAppTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'WhatsApp Templates';

    protected static ?string $modelLabel = 'WhatsApp Template';

    protected static ?string $pluralModelLabel = 'WhatsApp Templates';

    protected static ?int $navigationSort = 5;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pal Digital template')
                ->description('Name must match a live API campaign in Pal Digital (waservice), or the Meta template name linked to that campaign.')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    TextInput::make('description')
                        ->maxLength(255),
                    Textarea::make('body')
                        ->label('Template preview text')
                        ->helperText('Use {{1}}, {{2}}, … for parameters. Shown in message history after send.')
                        ->rows(4)
                        ->columnSpanFull(),
                    Repeater::make('param_mapping_rows')
                        ->label('Parameter mapping')
                        ->helperText('Each row maps to {{1}}, {{2}}, … in order.')
                        ->schema([
                            Select::make('source')
                                ->label('Data source')
                                ->options(WhatsAppTemplateParamResolver::sourceOptions())
                                ->searchable()
                                ->required(),
                        ])
                        ->defaultItems(0)
                        ->maxItems(10)
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('param_count')->label('Params')->sortable(),
                TextColumn::make('description')->limit(40),
                TextColumn::make('synced_at')->label('Synced')->dateTime('d M Y')->sortable()->toggleable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('updated_at')->dateTime('d M Y')->sortable(),
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
        $data['param_count'] = count($mappings);
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

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppTemplates::route('/'),
            'create' => CreateWhatsAppTemplate::route('/create'),
            'edit' => EditWhatsAppTemplate::route('/{record}/edit'),
        ];
    }
}

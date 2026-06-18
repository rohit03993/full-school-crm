<?php

namespace App\Filament\Resources\ActivityTypes;

use App\Enums\RoleName;
use App\Filament\Resources\ActivityTypes\Pages\CreateActivityType;
use App\Filament\Resources\ActivityTypes\Pages\EditActivityType;
use App\Filament\Resources\ActivityTypes\Pages\ListActivityTypes;
use App\Models\ActivityType;
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
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ActivityTypeResource extends Resource
{
    protected static ?string $model = ActivityType::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Activity Types';

    protected static ?string $modelLabel = 'Activity Type';

    protected static ?string $pluralModelLabel = 'Activity Types';

    protected static ?int $navigationSort = 2;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Activity Type')
                    ->description('Define what kinds of activities your institute tracks — exams, mock tests, workshops, etc.')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true),
                        TextInput::make('plural_name')
                            ->label('Plural name')
                            ->placeholder('Auto-generated from name if left blank')
                            ->maxLength(100),
                        TextInput::make('slug')
                            ->helperText('Leave blank to auto-generate from name.')
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),
                        TextInput::make('icon')
                            ->placeholder('heroicon-o-academic-cap')
                            ->maxLength(100),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->default(true),
                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),
                        Repeater::make('field_schema')
                            ->label('Custom fields')
                            ->helperText('Optional extra fields staff fill when creating a session of this type.')
                            ->schema([
                                TextInput::make('label')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('key')
                                    ->required()
                                    ->maxLength(50)
                                    ->helperText('Machine key, e.g. subject or max_marks'),
                                Select::make('type')
                                    ->options([
                                        'text' => 'Text',
                                        'number' => 'Number',
                                        'textarea' => 'Long text',
                                    ])
                                    ->default('text')
                                    ->required()
                                    ->native(false),
                            ])
                            ->columns(3)
                            ->columnSpanFull()
                            ->defaultItems(0),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('plural_name')->label('Plural'),
                TextColumn::make('sessions_count')->counts('sessions')->label('Sessions'),
                TextColumn::make('sort_order')->sortable(),
                IconColumn::make('is_enabled')->boolean()->label('Enabled'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityTypes::route('/'),
            'create' => CreateActivityType::route('/create'),
            'edit' => EditActivityType::route('/{record}/edit'),
        ];
    }
}

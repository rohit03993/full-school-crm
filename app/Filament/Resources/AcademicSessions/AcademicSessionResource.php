<?php

namespace App\Filament\Resources\AcademicSessions;

use App\Enums\RoleName;
use App\Filament\Resources\AcademicSessions\Pages\CreateAcademicSession;
use App\Filament\Resources\AcademicSessions\Pages\EditAcademicSession;
use App\Filament\Resources\AcademicSessions\Pages\ListAcademicSessions;
use App\Filament\Support\CrmTable;
use App\Models\AcademicSession;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use Filament\Forms\Components\DatePicker;
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

class AcademicSessionResource extends Resource
{
    protected static ?string $model = AcademicSession::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Academic Sessions';

    protected static ?string $modelLabel = 'Academic Session';

    protected static ?string $pluralModelLabel = 'Academic Sessions';

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public static function getNavigationTooltip(): ?string
    {
        return CrmHint::navigationTooltip('sessions.list');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Session Details')
                    ->description('Academic year or session — e.g. 2025–26 for schools and colleges.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Session Name')
                            ->placeholder('e.g. 2025–26')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('code')
                            ->label('Code')
                            ->placeholder('e.g. 2025-26')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->helperText('Short unique code used in batches and reports.'),
                        DatePicker::make('starts_on')
                            ->label('Starts On')
                            ->required()
                            ->native(false),
                        DatePicker::make('ends_on')
                            ->label('Ends On')
                            ->required()
                            ->afterOrEqual('starts_on')
                            ->native(false),
                        Toggle::make('is_current')
                            ->label('Current Session')
                            ->helperText('Only one session can be current. Used as default when creating batches.'),
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
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->fontFamily('mono'),
                TextColumn::make('starts_on')
                    ->label('From')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label('To')
                    ->date('d M Y')
                    ->sortable(),
                IconColumn::make('is_current')
                    ->label('Current')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('batches_count')
                    ->counts('batches')
                    ->label('Batches'),
            ])
            ->defaultSort('starts_on', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcademicSessions::route('/'),
            'create' => CreateAcademicSession::route('/create'),
            'edit' => EditAcademicSession::route('/{record}/edit'),
        ];
    }
}

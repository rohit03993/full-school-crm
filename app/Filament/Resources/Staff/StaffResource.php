<?php

namespace App\Filament\Resources\Staff;

use App\Enums\RoleName;
use App\Filament\Resources\Staff\Pages\CreateStaff;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'Staff Member';

    protected static ?string $pluralModelLabel = 'Staff';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    protected static string | UnitEnum | null $navigationGroup = 'Administration';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['roles', 'staffProfile'])
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [
                RoleName::Staff->value,
                RoleName::SuperAdmin->value,
            ]));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->maxLength(255),
                    TextInput::make('mobile')
                        ->tel()
                        ->maxLength(10)
                        ->rule('nullable|regex:/^[6-9]\d{9}$/'),
                    Select::make('role')
                        ->options([
                            RoleName::Staff->value => RoleName::Staff->label(),
                            RoleName::SuperAdmin->value => RoleName::SuperAdmin->label(),
                        ])
                        ->default(RoleName::Staff->value)
                        ->required()
                        ->native(false)
                        ->dehydrated(true),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),
            Section::make('Staff Profile')
                ->columns(2)
                ->relationship('staffProfile')
                ->schema([
                    TextInput::make('designation')
                        ->maxLength(100),
                    TextInput::make('employee_code')
                        ->label('Employee Code')
                        ->maxLength(50)
                        ->unique(ignoreRecord: true),
                    TextInput::make('mobile')
                        ->label('Work Mobile')
                        ->tel()
                        ->maxLength(10)
                        ->rule('nullable|regex:/^[6-9]\d{9}$/'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => RoleName::tryFrom($state)?->label() ?? $state),
                TextColumn::make('staffProfile.designation')
                    ->label('Designation')
                    ->placeholder('—'),
                TextColumn::make('staffProfile.employee_code')
                    ->label('Employee Code')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->relationship('roles', 'name')
                    ->options([
                        RoleName::Staff->value => RoleName::Staff->label(),
                        RoleName::SuperAdmin->value => RoleName::SuperAdmin->label(),
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaff::route('/'),
            'create' => CreateStaff::route('/create'),
            'edit' => EditStaff::route('/{record}/edit'),
        ];
    }
}

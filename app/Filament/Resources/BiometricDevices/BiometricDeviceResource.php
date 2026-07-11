<?php

namespace App\Filament\Resources\BiometricDevices;

use App\Enums\RoleName;
use App\Filament\Resources\BiometricDevices\Pages\CreateBiometricDevice;
use App\Filament\Resources\BiometricDevices\Pages\EditBiometricDevice;
use App\Filament\Resources\BiometricDevices\Pages\ListBiometricDevices;
use App\Filament\Support\CrmTable;
use App\Models\BiometricDevice;
use App\Support\CrmNavigation;
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

class BiometricDeviceResource extends Resource
{
    protected static ?string $model = BiometricDevice::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 59;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    public static function getNavigationLabel(): string
    {
        return 'Biometric devices';
    }

    public static function getModelLabel(): string
    {
        return 'Biometric device';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Biometric devices';
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Device')
                ->description('Register each ZKTeco machine by serial number (allowlist). Only active devices can push punches to /iclock.')
                ->schema([
                    TextInput::make('name')
                        ->label('Machine name')
                        ->required()
                        ->maxLength(120)
                        ->placeholder('e.g. Reception K40'),
                    TextInput::make('serial_number')
                        ->label('Serial number (SN)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(64)
                        ->helperText('From the device menu / sticker. Must match ADMS SN exactly.'),
                    TextInput::make('location')
                        ->label('Location / branch')
                        ->maxLength(120)
                        ->placeholder('e.g. Main gate'),
                    Toggle::make('is_active')
                        ->label('Active (allow punches)')
                        ->default(true),
                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('serial_number')->label('SN')->searchable()->copyable(),
                TextColumn::make('location')->toggleable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('last_seen_at')->dateTime('d M Y H:i')->placeholder('—'),
                TextColumn::make('last_punch_at')->dateTime('d M Y H:i')->placeholder('—'),
                TextColumn::make('today_punch_count')->label('Today')->alignCenter(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBiometricDevices::route('/'),
            'create' => CreateBiometricDevice::route('/create'),
            'edit' => EditBiometricDevice::route('/{record}/edit'),
        ];
    }
}

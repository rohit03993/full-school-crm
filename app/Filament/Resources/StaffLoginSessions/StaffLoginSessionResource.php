<?php

namespace App\Filament\Resources\StaffLoginSessions;

use App\Enums\CrmPermission;
use App\Enums\StaffLoginMethod;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Resources\StaffLoginSessions\Pages\ListStaffLoginSessions;
use App\Filament\Support\CrmTable;
use App\Models\StaffLoginSession;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StaffLoginSessionResource extends Resource
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::StaffManage;
    }

    protected static ?string $model = StaffLoginSession::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = 'Staff login';

    protected static ?string $pluralModelLabel = 'Staff login log';

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::staffLoginLog();
    }

    protected static ?int $navigationSort = 15;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ADMIN;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->defaultSort('logged_in_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Staff')
                    ->searchable()
                    ->description(fn (StaffLoginSession $record): string => $record->user?->mobile ?? ''),
                TextColumn::make('logged_in_at')
                    ->label('In')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('logged_out_at')
                    ->label('Out')
                    ->dateTime('d M Y H:i')
                    ->placeholder('In now')
                    ->sortable(),
                TextColumn::make('duration')
                    ->label('Duration')
                    ->state(fn (StaffLoginSession $record): string => $record->durationLabel()),
                TextColumn::make('method')
                    ->label('How')
                    ->badge()
                    ->formatStateUsing(fn (StaffLoginMethod $state): string => $state->label())
                    ->color(fn (StaffLoginMethod $state): string => $state === StaffLoginMethod::Otp ? 'success' : 'gray'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (StaffLoginSession $record): string => $record->isOpen() ? 'In now' : 'Logged out')
                    ->color(fn (StaffLoginSession $record): string => $record->isOpen() ? 'warning' : 'gray'),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Staff')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('method')
                    ->options([
                        StaffLoginMethod::Otp->value => StaffLoginMethod::Otp->label(),
                        StaffLoginMethod::Password->value => StaffLoginMethod::Password->label(),
                    ]),
                TernaryFilter::make('open')
                    ->label('Still in CRM')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('logged_out_at'),
                        false: fn (Builder $query) => $query->whereNotNull('logged_out_at'),
                    ),
                Filter::make('logged_in_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->native(false),
                        \Filament\Forms\Components\DatePicker::make('until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('logged_in_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('logged_in_at', '<=', $date));
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStaffLoginSessions::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }
}

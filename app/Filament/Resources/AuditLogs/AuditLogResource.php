<?php

namespace App\Filament\Resources\AuditLogs;

use App\Enums\RoleName;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Support\CrmTable;
use App\Models\AuditLog;
use App\Support\CrmNavigation;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?string $modelLabel = 'Audit entry';

    protected static ?string $pluralModelLabel = 'Audit log';

    protected static ?int $navigationSort = 20;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_ADMIN;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('user_name')
                    ->label('User')
                    ->searchable()
                    ->description(fn (AuditLog $record): string => $record->user_role ?? ''),
                TextColumn::make('action')
                    ->label('Action')
                    ->searchable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('auditable_type')
                    ->label('Record')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? class_basename($state)
                        : '—')
                    ->description(fn (AuditLog $record): string => $record->auditable_id
                        ? '#'.$record->auditable_id
                        : ''),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Action')
                    ->options(fn (): array => AuditLog::query()
                        ->select('action')
                        ->distinct()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->all())
                    ->searchable(),
                Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->native(false),
                        \Filament\Forms\Components\DatePicker::make('until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordUrl(
                fn (AuditLog $record): string => static::getUrl('view', ['record' => $record]),
            );
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Audit entry')
                    ->schema([
                        TextEntry::make('created_at')->dateTime('d M Y H:i:s'),
                        TextEntry::make('user_name'),
                        TextEntry::make('user_role')->label('Role'),
                        TextEntry::make('action')->badge(),
                        TextEntry::make('auditable_type')
                            ->label('Record type')
                            ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),
                        TextEntry::make('auditable_id')->label('Record ID'),
                        TextEntry::make('ip_address')->label('IP'),
                        TextEntry::make('reason')->placeholder('—'),
                        TextEntry::make('old_values')
                            ->label('Previous values')
                            ->formatStateUsing(fn (?array $state): string => $state
                                ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                : '—')
                            ->columnSpanFull(),
                        TextEntry::make('new_values')
                            ->label('New values')
                            ->formatStateUsing(fn (?array $state): string => $state
                                ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                                : '—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}

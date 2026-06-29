<?php

namespace App\Filament\Resources\WhatsAppCampaigns\RelationManagers;

use App\Enums\WhatsAppRecipientStatus;
use App\Filament\Support\CrmTable;
use App\Support\WhatsAppCampaignViewHelper;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'Delivery log';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedUsers;

    protected static bool $shouldSkipAuthorization = true;

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $total = (int) $ownerRecord->total_recipients;

        if ($total < 1) {
            return null;
        }

        if ((int) $ownerRecord->failed_count > 0) {
            return (string) $ownerRecord->failed_count;
        }

        return (string) $ownerRecord->total_recipients;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        if ((int) $ownerRecord->failed_count > 0) {
            return 'danger';
        }

        if (WhatsAppCampaignViewHelper::isInProgress($ownerRecord)) {
            return 'warning';
        }

        return 'success';
    }

    public function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->heading('Recipients')
            ->description('Search, filter, and paginate — built for large campaigns (100+ students).')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('student')
                ->orderByRaw("CASE status WHEN 'failed' THEN 0 WHEN 'processing' THEN 1 WHEN 'pending' THEN 2 ELSE 3 END")
                ->orderBy('id'))
            ->columns([
                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label('Mobile')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Mobile copied'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (WhatsAppRecipientStatus|string $state): string => $state instanceof WhatsAppRecipientStatus
                        ? $state->label()
                        : ucfirst((string) $state))
                    ->color(fn (WhatsAppRecipientStatus|string $state): string => match ($state instanceof WhatsAppRecipientStatus ? $state->value : $state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        'pending', 'processing' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('template_params')
                    ->label('Params sent')
                    ->formatStateUsing(function (mixed $state): string {
                        if (! is_array($state) || $state === []) {
                            return '—';
                        }

                        return count($state).' param(s): '.collect($state)
                            ->map(fn (mixed $value, int $index): string => '{{'.($index + 1).'}}='.(string) $value)
                            ->implode(', ');
                    })
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('error_message')
                    ->label('Error / note')
                    ->wrap()
                    ->limit(100)
                    ->tooltip(fn (?string $state): ?string => filled($state) && strlen($state) > 100 ? $state : null)
                    ->placeholder('—')
                    ->color('danger')
                    ->visible(fn (): bool => (int) $this->getOwnerRecord()->failed_count > 0
                        || WhatsAppCampaignViewHelper::isInProgress($this->getOwnerRecord())),
                TextColumn::make('updated_at')
                    ->label('Last update')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('id')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(WhatsAppRecipientStatus::cases())
                        ->mapWithKeys(fn (WhatsAppRecipientStatus $status): array => [
                            $status->value => $status->label(),
                        ])
                        ->all()),
            ])
            ->striped()
            ->emptyStateHeading('No recipients')
            ->emptyStateDescription('Recipients are added when the campaign is created.');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}

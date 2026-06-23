<?php

namespace App\Filament\Forms;

use App\Enums\CallDirection;
use App\Enums\CallQuickTag;
use App\Enums\CallStatus;
use App\Enums\VisitStatus;
use App\Enums\WhoAnswered;
use App\Services\CallLogService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class LogCallFormSchema
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function fields(): array
    {
        return [
            Select::make('call_direction')
                ->label('Direction')
                ->options(collect(CallDirection::cases())->mapWithKeys(
                    fn (CallDirection $direction): array => [$direction->value => $direction->label()],
                )->all())
                ->default(CallDirection::Outgoing->value)
                ->native(false),
            Toggle::make('call_connected')
                ->label('Call connected?')
                ->default(true)
                ->live(),
            Select::make('call_status')
                ->label('Reason')
                ->options(CallStatus::notConnectedOptions())
                ->visible(fn (Get $get): bool => ! $get('call_connected'))
                ->required(fn (Get $get): bool => ! $get('call_connected'))
                ->native(false),
            Select::make('who_answered')
                ->label('Who answered?')
                ->options(WhoAnswered::options())
                ->visible(fn (Get $get): bool => (bool) $get('call_connected'))
                ->required(fn (Get $get): bool => (bool) $get('call_connected'))
                ->native(false),
            Select::make('visit_status')
                ->label('Lead status after call')
                ->options(collect(VisitStatus::cases())->mapWithKeys(
                    fn (VisitStatus $status): array => [$status->value => $status->label()],
                )->all())
                ->visible(fn (Get $get): bool => (bool) $get('call_connected'))
                ->required(fn (Get $get): bool => (bool) $get('call_connected'))
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                    if (! $get('call_connected') || blank($state)) {
                        return;
                    }

                    $visitStatus = VisitStatus::tryFrom($state);

                    if ($visitStatus && in_array($visitStatus, self::followUpVisitStatuses(), true)) {
                        $set(
                            'next_followup_at',
                            app(CallLogService::class)->suggestFollowUp($visitStatus, true)->format('Y-m-d H:i:s'),
                        );

                        return;
                    }

                    if (in_array($visitStatus, [VisitStatus::NotInterested, VisitStatus::Joined], true)) {
                        $set('next_followup_at', null);
                    }
                })
                ->native(false),
            TextInput::make('duration_minutes')
                ->label('Duration (minutes)')
                ->numeric()
                ->minValue(0)
                ->maxValue(600),
            Textarea::make('call_notes')
                ->label('Call notes')
                ->rows(3)
                ->required(fn (Get $get): bool => (bool) $get('call_connected'))
                ->minLength(10)
                ->helperText('At least 10 characters when the call connected.')
                ->maxLength(2000),
            CheckboxList::make('tags')
                ->label('Quick tags')
                ->options(CallQuickTag::options())
                ->columns(2)
                ->visible(fn (Get $get): bool => (bool) $get('call_connected')),
            DateTimePicker::make('next_followup_at')
                ->label('Next follow-up')
                ->native(false)
                ->seconds(false)
                ->minDate(now())
                ->required(fn (Get $get): bool => self::requiresFollowUpDate($get))
                ->visible(fn (Get $get): bool => (bool) $get('call_connected'))
                ->helperText(fn (Get $get): string => self::requiresFollowUpDate($get)
                    ? 'Required for this lead status. Suggested time uses 9 AM – 8 PM.'
                    : 'Optional for this lead status.'),
        ];
    }

    public static function requiresFollowUpDate(Get $get): bool
    {
        if (! $get('call_connected')) {
            return false;
        }

        $visitStatus = VisitStatus::tryFrom((string) $get('visit_status'));

        if (! $visitStatus) {
            return false;
        }

        if (in_array($visitStatus, [VisitStatus::NotInterested, VisitStatus::Joined], true)) {
            return false;
        }

        return in_array($visitStatus, self::followUpVisitStatuses(), true);
    }

    /**
     * @return list<VisitStatus>
     */
    public static function followUpVisitStatuses(): array
    {
        return CallLogService::FOLLOWUP_VISIT_STATUSES;
    }
}

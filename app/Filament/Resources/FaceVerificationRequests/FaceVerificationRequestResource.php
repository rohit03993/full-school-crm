<?php

namespace App\Filament\Resources\FaceVerificationRequests;

use App\Enums\RoleName;
use App\Filament\Resources\FaceVerificationRequests\Pages\ListFaceVerificationRequests;
use App\Filament\Support\CrmTable;
use App\Models\FaceVerificationRequest;
use App\Support\CrmNavigation;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class FaceVerificationRequestResource extends Resource
{
    protected static ?string $model = FaceVerificationRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedFaceSmile;

    protected static ?int $navigationSort = 60;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    public static function getNavigationLabel(): string
    {
        return 'Face verifications';
    }

    public static function getModelLabel(): string
    {
        return 'Face verification';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Face verifications';
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

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
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
                TextColumn::make('enrollment_number')
                    ->label('Roll')
                    ->searchable()
                    ->fontFamily('mono')
                    ->sortable(),
                TextColumn::make('student.name')
                    ->label('Student')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('source')
                    ->label('Source')
                    ->state(fn (FaceVerificationRequest $record): string => match ((string) data_get($record->meta, 'source')) {
                        'camera_kiosk' => 'Camera',
                        'adms' => 'RFID gate',
                        default => '—',
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Camera' => 'info',
                        'RFID gate' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('biometricDevice.name')
                    ->label('Gate')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtoupper($state)) {
                        FaceVerificationRequest::STATUS_PASS => 'success',
                        FaceVerificationRequest::STATUS_PENDING => 'warning',
                        FaceVerificationRequest::STATUS_FAIL => 'danger',
                        FaceVerificationRequest::STATUS_TIMEOUT => 'gray',
                        FaceVerificationRequest::STATUS_ERROR => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('score')
                    ->placeholder('—')
                    ->alignRight(),
                TextColumn::make('punched_at')
                    ->label('Punch time')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
                TextColumn::make('responded_at')
                    ->label('Responded')
                    ->dateTime('d M Y H:i:s')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('face_request_id')
                    ->label('Face request')
                    ->limit(12)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        FaceVerificationRequest::STATUS_PENDING => 'PENDING',
                        FaceVerificationRequest::STATUS_PASS => 'PASS',
                        FaceVerificationRequest::STATUS_FAIL => 'FAIL',
                        FaceVerificationRequest::STATUS_TIMEOUT => 'TIMEOUT',
                        FaceVerificationRequest::STATUS_ERROR => 'ERROR',
                    ]),
                SelectFilter::make('source')
                    ->label('Source')
                    ->options([
                        'camera_kiosk' => 'Camera',
                        'adms' => 'RFID gate',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        if (! filled($value)) {
                            return $query;
                        }

                        return $query->where('meta->source', $value);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaceVerificationRequests::route('/'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with(['student', 'biometricDevice']);
    }
}

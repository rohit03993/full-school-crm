<?php

namespace App\Filament\Resources\Staff;

use App\Enums\CrmPermission;
use App\Enums\RoleName;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Enums\StaffJobRole;
use App\Filament\Pages\BulkStaffImportPage;
use App\Filament\Resources\Staff\Pages\CreateStaff;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Support\CrmTable;
use App\Models\User;
use App\Services\FaceVerify\FaceVerifyGateService;
use App\Support\BiometricPinCollision;
use App\Support\CrmAccess;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
use Throwable;
use UnitEnum;

class StaffResource extends Resource
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::StaffManage;
    }

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = 'Staff Member';

    protected static ?string $pluralModelLabel = 'Staff';

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::staff();
    }

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_ADMIN;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['roles', 'staffProfile'])
            ->where('is_platform_operator', false)
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', array_merge(
                        [RoleName::SuperAdmin->value, RoleName::Staff->value],
                        StaffJobRole::values(),
                    )))
                    ->orWhereHas('staffProfile', fn (Builder $profile) => $profile
                        ->whereNotNull('employee_code')
                        ->where('employee_code', '!=', ''));
            });
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
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
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->maxLength(255),
                    TextInput::make('mobile')
                        ->tel()
                        ->required()
                        ->maxLength(10)
                        ->rule('regex:/^[6-9]\d{9}$/')
                        ->unique(ignoreRecord: true)
                        ->helperText('Staff sign in at /admin with this mobile and password.'),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Turn off to deactivate. The account and history stay; they cannot log in. Staff are not deleted from this screen.'),
                ]),
            Section::make('Access & roles')
                ->description('Tick every function this person should have. Permissions from all selected roles are combined — e.g. Accountant + Fee adjuster can collect and change fee plans.')
                ->schema([
                    Toggle::make('is_super_admin')
                        ->label('Super Admin (full access)')
                        ->helperText('Owners only — settings, staff, reports, WhatsApp config, and all day-to-day work.')
                        ->default(false)
                        ->live(),
                    CheckboxList::make('job_roles')
                        ->label('Job roles')
                        ->options(StaffJobRole::options())
                        ->descriptions(collect(StaffJobRole::cases())
                            ->mapWithKeys(fn (StaffJobRole $role): array => [$role->value => $role->description()])
                            ->all())
                        ->columns(1)
                        ->disabled(fn (callable $get): bool => (bool) $get('is_super_admin'))
                        ->helperText('Select one or more roles. Combine Accountant + Fee adjuster when someone should both collect fees and change discounts/structure.'),
                ]),
            Section::make('Staff Profile')
                ->columns(2)
                ->relationship('staffProfile')
                ->schema([
                    TextInput::make('designation')
                        ->maxLength(100),
                    TextInput::make('employee_code')
                        ->label('Staff ID')
                        ->helperText('Device PIN / Face ID — must not match any student roll number.')
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper(trim($state)) : null)
                        ->rule(function (): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail): void {
                                if (! filled($value)) {
                                    return;
                                }

                                if (BiometricPinCollision::staffCodeCollidesWithStudentRoll((string) $value)) {
                                    $fail('This Staff ID matches a student roll number. Choose a different ID.');
                                }
                            };
                        }),
                    TextInput::make('mobile')
                        ->label('Work Mobile')
                        ->tel()
                        ->maxLength(10)
                        ->rules(['nullable', 'regex:/^[6-9]\d{9}$/']),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('mobile')
                    ->label('Mobile')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->formatStateUsing(function (string $state): string {
                        if ($state === RoleName::SuperAdmin->value) {
                            return RoleName::SuperAdmin->label();
                        }

                        if ($state === RoleName::Staff->value) {
                            return 'Legacy staff (full ops)';
                        }

                        return StaffJobRole::tryFrom($state)?->label() ?? $state;
                    })
                    ->listWithLineBreaks()
                    ->limitList(4),
                TextColumn::make('staffProfile.designation')
                    ->label('Designation')
                    ->placeholder('—'),
                TextColumn::make('staffProfile.employee_code')
                    ->label('Staff ID')
                    ->searchable()
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
                SelectFilter::make('job_role')
                    ->label('Job role')
                    ->options(StaffJobRole::options())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->whereHas('roles', fn (Builder $roleQuery): Builder => $roleQuery->where('name', $value));
                    }),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                Action::make('syncFaceVerify')
                    ->label('Sync to Face API')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (): bool => (bool) config('face_verify.enabled', false)
                        && (Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('Sync staff to Face API')
                    ->modalDescription('Upserts this staff member so the shared kiosk can enroll by Staff ID.')
                    ->action(function (User $record): void {
                        try {
                            app(FaceVerifyGateService::class)->syncStaff($record);

                            Notification::make()
                                ->title('Synced to Face API')
                                ->body('Staff ID '.$record->staffProfile?->employee_code.' is ready for kiosk enrollment.')
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('Face API sync failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->headerActions([
                Action::make('importStaff')
                    ->label('Import staff')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->url(BulkStaffImportPage::getUrl())
                    ->visible(fn (): bool => CrmAccess::can(Auth::user(), CrmPermission::StaffManage)),
                Action::make('syncAllFaceVerify')
                    ->label('Sync all to Face API')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->visible(fn (): bool => (bool) config('face_verify.enabled', false)
                        && (Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false))
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $users = User::query()
                            ->where('is_active', true)
                            ->whereHas('staffProfile', fn (Builder $q) => $q->whereNotNull('employee_code')->where('employee_code', '!=', ''))
                            ->with('staffProfile')
                            ->get();

                        try {
                            $result = app(FaceVerifyGateService::class)->syncStaffMembers($users);

                            Notification::make()
                                ->title('Staff synced to Face API')
                                ->body(($result['synced'] ?? 0).' staff member(s) synced.')
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('Face API sync failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
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

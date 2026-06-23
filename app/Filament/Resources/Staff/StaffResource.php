<?php

namespace App\Filament\Resources\Staff;

use App\Enums\CrmPermission;
use App\Enums\RoleName;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Enums\StaffJobRole;
use App\Filament\Resources\Staff\Pages\CreateStaff;
use App\Filament\Resources\Staff\Pages\EditStaff;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Support\CrmTable;
use App\Models\User;
use App\Support\CrmNavigation;
use Filament\Forms\Components\CheckboxList;
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
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::StaffManage;
    }

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Staff';

    protected static ?string $modelLabel = 'Staff Member';

    protected static ?string $pluralModelLabel = 'Staff';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_ADMIN;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['roles', 'staffProfile'])
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', array_merge(
                [RoleName::SuperAdmin->value],
                StaffJobRole::values(),
            )));
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
                        ->default(true),
                ]),
            Section::make('Access & roles')
                ->description('Tick every function this person should have. Permissions from all selected roles are combined — e.g. Counsellor + Accountant gives both calling and fee collection.')
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
                        ->helperText('Select one or more — or all four for a full operations login without Super Admin.'),
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

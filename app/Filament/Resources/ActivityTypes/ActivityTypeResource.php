<?php

namespace App\Filament\Resources\ActivityTypes;

use App\Enums\CrmPermission;
use App\Enums\RoleName;
use App\Filament\Concerns\RequiresAnyCrmPermission;
use App\Filament\Resources\ActivityTypes\Pages\CreateActivityType;
use App\Support\CrmAccess;
use App\Filament\Resources\ActivityTypes\Pages\EditActivityType;
use App\Filament\Resources\ActivityTypes\Pages\ListActivityTypes;
use App\Filament\Support\CrmTable;
use App\Models\ActivityType;
use App\Support\ActivityTypePresets;
use App\Support\CrmNavigation;
use App\Support\EduExamLabels;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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

class ActivityTypeResource extends Resource
{
    use RequiresAnyCrmPermission;

    /**
     * @return list<CrmPermission>
     */
    protected static function anyCrmPermissions(): array
    {
        return [
            CrmPermission::MarksImport,
            CrmPermission::AcademicsManage,
        ];
    }

    protected static ?string $model = ActivityType::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Exam Types';

    protected static ?string $modelLabel = 'Exam Type';

    protected static ?string $pluralModelLabel = 'Exam Types';

    public static function getNavigationLabel(): string
    {
        return EduExamLabels::examTypes();
    }

    public static function getModelLabel(): string
    {
        return EduExamLabels::examType();
    }

    public static function getPluralModelLabel(): string
    {
        return EduExamLabels::examTypes();
    }

    protected static ?int $navigationSort = 45;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function canCreate(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::AcademicsManage);
    }

    public static function canEdit($record): bool
    {
        return static::canCreate();
    }

    public static function canDelete($record): bool
    {
        return static::canCreate();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(EduExamLabels::examType())
                    ->description('Workshop & Event: leave Marks ✗ — staff mark attendance under Academics → Workshops & Events.')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->live(onBlur: true),
                        TextInput::make('plural_name')
                            ->label('Plural name')
                            ->placeholder('Auto-generated from name if left blank')
                            ->maxLength(100),
                        TextInput::make('slug')
                            ->helperText('Leave blank to auto-generate from name.')
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),
                        TextInput::make('icon')
                            ->placeholder('heroicon-o-academic-cap')
                            ->maxLength(100),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->default(true),
                        Toggle::make('tracks_marks')
                            ->label('Records marks & scores')
                            ->helperText('Required for Import Marks and score entry. Adds Subject and Max Marks fields.')
                            ->default(true)
                            ->live(),
                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),
                        Repeater::make('field_schema')
                            ->label('Custom fields')
                            ->helperText('Optional extra fields staff fill when creating a session of this type.')
                            ->schema([
                                TextInput::make('label')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('key')
                                    ->required()
                                    ->maxLength(50)
                                    ->helperText('Machine key, e.g. subject or max_marks'),
                                Select::make('type')
                                    ->options([
                                        'text' => 'Text',
                                        'number' => 'Number',
                                        'textarea' => 'Long text',
                                    ])
                                    ->default('text')
                                    ->required()
                                    ->native(false),
                            ])
                            ->columns(3)
                            ->columnSpanFull()
                            ->defaultItems(0),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return CrmTable::configure($table)
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('plural_name')->label('Plural'),
                TextColumn::make('sessions_count')->counts('sessions')->label('Sessions'),
                TextColumn::make('sort_order')->sortable(),
                IconColumn::make('marks_enabled')
                    ->label('Marks')
                    ->boolean()
                    ->getStateUsing(fn (ActivityType $record): bool => $record->supportsScoring())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                IconColumn::make('is_enabled')->boolean()->label('Enabled'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityTypes::route('/'),
            'create' => CreateActivityType::route('/create'),
            'edit' => EditActivityType::route('/{record}/edit'),
        ];
    }
}

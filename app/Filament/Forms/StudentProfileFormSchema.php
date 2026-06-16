<?php

namespace App\Filament\Forms;

use App\Enums\Gender;
use App\Enums\StudentCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class StudentProfileFormSchema
{
    /**
     * @return array<int, Section>
     */
    public static function forEdit(): array
    {
        return [
            Section::make('Personal Details')
                ->description('Complete the profile when you have more information from the student.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('father_name')
                        ->label("Father's Name")
                        ->maxLength(255),
                    DatePicker::make('date_of_birth')
                        ->label('Date of Birth')
                        ->maxDate(now()->subDay())
                        ->native(false),
                    Select::make('gender')
                        ->options(self::genderOptions())
                        ->native(false),
                    TextInput::make('mobile')
                        ->tel()
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Mobile cannot be changed — it is the unique student ID.'),
                    TextInput::make('alternate_mobile')
                        ->label('Alternate Mobile')
                        ->tel()
                        ->maxLength(10)
                        ->rule('nullable|regex:/^[6-9]\d{9}$/'),
                    TextInput::make('email')
                        ->email()
                        ->maxLength(255)
                        ->columnSpanFull(),
                ]),
            Section::make('Address')
                ->columns(2)
                ->schema([
                    Textarea::make('address')
                        ->rows(2)
                        ->columnSpanFull(),
                    TextInput::make('city')
                        ->maxLength(100),
                    TextInput::make('state')
                        ->maxLength(100),
                    TextInput::make('pincode')
                        ->maxLength(6)
                        ->rule('nullable|digits:6'),
                ]),
            Section::make('Category')
                ->schema([
                    Select::make('category')
                        ->options(self::categoryOptions())
                        ->native(false),
                ]),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function genderOptions(): array
    {
        return collect(Gender::cases())
            ->mapWithKeys(fn (Gender $gender) => [$gender->value => $gender->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected static function categoryOptions(): array
    {
        return collect(StudentCategory::cases())
            ->mapWithKeys(fn (StudentCategory $category) => [$category->value => $category->label()])
            ->all();
    }
}

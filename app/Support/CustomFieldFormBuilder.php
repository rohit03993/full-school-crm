<?php

namespace App\Support;

use App\Models\CustomFieldDefinition;
use App\Services\CustomFieldService;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class CustomFieldFormBuilder
{
    /**
     * @return list<Section>
     */
    public static function sections(string $entity, string $statePath = 'custom_data'): array
    {
        $definitions = app(CustomFieldService::class)->activeDefinitions($entity);

        if ($definitions === []) {
            return [];
        }

        $fields = [];

        foreach ($definitions as $definition) {
            $fields[] = self::component($definition, $statePath);
        }

        return [
            Section::make('Additional information')
                ->description('Custom fields configured in Settings → Custom Fields.')
                ->columns(2)
                ->schema($fields),
        ];
    }

    /**
     * @return list<Component>
     */
    public static function flatComponents(string $entity, string $statePath = 'custom_data'): array
    {
        $definitions = app(CustomFieldService::class)->activeDefinitions($entity);

        return array_map(
            fn (CustomFieldDefinition $definition): Component => self::component($definition, $statePath),
            $definitions,
        );
    }

    public static function component(CustomFieldDefinition $definition, string $statePath): Component
    {
        $name = $statePath.'.'.$definition->field_key;

        return match ($definition->field_type) {
            'textarea' => Textarea::make($name)
                ->label($definition->label)
                ->required($definition->is_required)
                ->rows(3)
                ->columnSpanFull(),
            'date' => DatePicker::make($name)
                ->label($definition->label)
                ->required($definition->is_required)
                ->native(false),
            'number' => TextInput::make($name)
                ->label($definition->label)
                ->required($definition->is_required)
                ->numeric(),
            'select' => Select::make($name)
                ->label($definition->label)
                ->required($definition->is_required)
                ->options(collect($definition->options ?? [])->mapWithKeys(fn (string $option): array => [$option => $option])->all())
                ->native(false),
            default => TextInput::make($name)
                ->label($definition->label)
                ->required($definition->is_required)
                ->maxLength(255),
        };
    }
}

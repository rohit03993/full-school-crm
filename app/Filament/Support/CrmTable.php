<?php

namespace App\Filament\Support;

use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

final class CrmTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->deferFilters()
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns([
                'default' => 1,
                'md' => 2,
                'xl' => 3,
            ])
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->striped();
    }
}

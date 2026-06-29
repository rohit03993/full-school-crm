<?php

namespace App\Filament\Support;

use App\Support\CrmPagination;
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
            ->paginationPageOptions(CrmPagination::perPageOptions())
            ->defaultPaginationPageOption(CrmPagination::PER_PAGE)
            ->striped();
    }
}

<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\DashboardFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Turns the dashboard page filters into the shared filter object every
 * dashboard query uses, so one widget can never disagree with another.
 */
trait UsesDashboardFilters
{
    use InteractsWithPageFilters;

    protected function dashboardFilters(): DashboardFilters
    {
        return DashboardFilters::fromArray($this->pageFilters ?? []);
    }
}

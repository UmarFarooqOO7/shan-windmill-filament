<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    // Define the layout of the widgets on the dashboard
    protected function getHeaderWidgets(): array
    {
        return [];
    }

    // Define the layout of the widgets on the dashboard
    protected function getFooterWidgets(): array
    {
        return [];
    }

    public function getColumns(): int | string | array
    {
        return 12;
    }
}

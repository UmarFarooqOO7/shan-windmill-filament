<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Calendar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar'; // Changed to calendar icon

    protected static string $view = 'filament.pages.calendar';

    protected static ?string $navigationLabel = 'Calendar'; // Explicit label

    // protected static ?string $navigationGroup = 'Events'; // Grouping

    protected static ?int $navigationSort = 3; // Sort order

    protected static ?string $title = 'Calendar'; // Page title

    // You can uncomment and use getSlug() if you want a custom URL slug
    // public static function getSlug(): string
    // {
    //     return 'my-calendar';
    // }
}

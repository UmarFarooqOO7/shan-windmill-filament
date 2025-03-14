<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Facades\Filament;

class WelcomeWidget extends Widget
{
    protected static string $view = 'filament.widgets.welcome-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -3;

    public function getMessage(): string
    {
        $user = Filament::auth()->user();
        $name = $user ? $user->name : 'Guest';

        return "👋 Welcome back, {$name}!";
    }
}

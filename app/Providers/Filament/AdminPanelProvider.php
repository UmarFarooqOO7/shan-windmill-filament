<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Calendar; // Assuming this is your Calendar Page class
use App\Models\Message;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Facades\Auth;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->Profile(isSimple: false)
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->spa()
            ->databaseNotifications()
            ->databaseNotificationsPolling('5s')
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('14rem')
            ->maxContentWidth(MaxWidth::Full)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                // Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->plugin(\TomatoPHP\FilamentUsers\FilamentUsersPlugin::make())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugin(
                FilamentFullCalendarPlugin::make()
                    ->selectable(true)
                    ->editable(true)
            )
            ->navigationItems([
                NavigationItem::make()
                    ->label('Chat') // ✅ This works, because closure has access to `auth()`
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->url('/admin/chat')
                    ->badge(function () {
                        $authId = auth()->user()->id; // ✅ Safe
                        if (! $authId) return null;

                        return Message::where(function ($query) use ($authId) {
                            $query
                                ->where(function ($q) use ($authId) {
                                    $q->whereHas('chat', function ($c) use ($authId) {
                                        $c->whereNotNull('team_id')
                                            ->whereHas('team.members', fn($q2) => $q2->where('users.id', $authId));
                                    })
                                        ->where('user_id', '!=', $authId)
                                        ->whereDoesntHave(
                                            'readers',
                                            fn($q) =>
                                            $q->where('user_id', $authId)
                                        );
                                })
                                ->orWhere(function ($q) use ($authId) {
                                    $q->whereHas('chat', function ($c) use ($authId) {
                                        $c->whereNull('team_id')
                                            ->whereHas(
                                                'users',
                                                fn($u) =>
                                                $u->where('users.id', $authId)
                                            );
                                    })
                                        ->where('user_id', '!=', $authId)
                                        ->where('is_read', false);
                                });
                        })
                            ->count() ?: null;
                    })
                    ->sort(90),
            ]);
    }
}

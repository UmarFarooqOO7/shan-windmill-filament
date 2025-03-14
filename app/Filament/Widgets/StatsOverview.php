<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Team;
use App\Models\Lead;
use App\Models\LeadAmount;
use App\Traits\HasTeamScope;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    use HasTeamScope;

    protected function getHeading(): ?string
    {
        return 'Overview';
    }

    protected function getDescription(): ?string
    {
        return 'An overview of last 7 days';
    }

    protected function getStats(): array
    {
        $user = Filament::auth()->user();
        $isAdmin = $user?->is_admin ?? false;

        // Get data for leads in user's teams
        $leadQuery = Lead::query();
        $leadQuery = $this->applyTeamScope($leadQuery);

        // Initialize stats array
        $stats = [];

        // Admin-only statistics
        if ($isAdmin) {
            $teamsLastWeek = collect(range(6, 0))->map(function ($days) {
                return Team::whereDate('created_at', Carbon::now()->subDays($days))->count();
            })->toArray();

            $usersLastWeek = collect(range(6, 0))->map(function ($days) {
                return User::whereDate('created_at', Carbon::now()->subDays($days))->count();
            })->toArray();

            $stats[] = Stat::make('Total Users', User::count())
                ->description(User::whereDate('created_at', Carbon::today())->count() . ' today')
                ->descriptionIcon('heroicon-m-user')
                ->chart($usersLastWeek)
                ->color('info');

            $stats[] = Stat::make('Total Teams', Team::count())
                ->description(Team::whereDate('created_at', Carbon::today())->count() . ' today')
                ->descriptionIcon('heroicon-m-user-group')
                ->chart($teamsLastWeek)
                ->color('primary');
        }

        // Get leads data for last 7 days for chart
        $leadsLastWeek = collect(range(6, 0))->map(function ($days) use ($leadQuery) {
            return $leadQuery->clone()
                ->whereDate('created_at', Carbon::now()->subDays($days))
                ->count();
        })->toArray();

        // Get cleared amounts for last 7 days
        $clearedAmountsLastWeek = collect(range(6, 0))->map(function ($days) use ($leadQuery) {
            $date = Carbon::now()->subDays($days)->format('Y-m-d');
            return DB::table('lead_amounts')
                ->whereIn('lead_id', $leadQuery->pluck('id'))
                ->whereDate('payment_date', $date)
                ->sum('amount_cleared');
        })->toArray();

        // Get owed amounts for last 7 days
        $owedAmountsLastWeek = collect(range(6, 0))->map(function ($days) use ($leadQuery) {
            $date = Carbon::now()->subDays($days);
            return $leadQuery->clone()
                ->whereDate('created_at', $date)
                ->sum('amount_owed');
        })->toArray();

        // Calculate remaining amounts for last 7 days
        $remainingAmountsLastWeek = collect(range(6, 0))->map(function ($days) use ($leadQuery) {
            $date = Carbon::now()->subDays($days);
            $owed = $leadQuery->clone()
                ->whereDate('created_at', '<=', $date)
                ->sum('amount_owed');
            $cleared = LeadAmount::whereIn('lead_id', $leadQuery->pluck('id'))
                ->whereDate('payment_date', '<=', $date)
                ->sum('amount_cleared');
            return $owed - $cleared;
        })->toArray();

        // Calculate totals
        $totalOwed = $leadQuery->sum('amount_owed');
        $totalCleared = LeadAmount::whereIn('lead_id', $leadQuery->pluck('id'))->sum('amount_cleared');
        $totalRemaining = $totalOwed - $totalCleared;

        // Get today's amounts
        $todayOwed = $leadQuery->clone()
            ->whereDate('created_at', Carbon::today())
            ->sum('amount_owed');

        $todayCleared = LeadAmount::whereIn('lead_id', $leadQuery->pluck('id'))
            ->whereDate('payment_date', Carbon::today())
            ->sum('amount_cleared');

        $todayRemaining = $todayOwed - $todayCleared;

        // Add common stats for all users
        $stats = array_merge($stats, [
            Stat::make('Total Leads', $leadQuery->count())
                ->description($leadQuery->whereDate('created_at', Carbon::today())->count() . ' today')
                ->descriptionIcon('heroicon-m-briefcase')
                ->chart($leadsLastWeek)
                ->color('warning'),

            Stat::make('Total Amount Cleared', '$' . number_format($totalCleared, 2))
                ->description('$' . number_format($todayCleared, 2) . ' today')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->chart($clearedAmountsLastWeek)
                ->color('success'),

            Stat::make('Total Amount Owed', '$' . number_format($totalOwed, 2))
                ->description('$' . number_format($todayOwed, 2) . ' today')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->chart($owedAmountsLastWeek)
                ->color('danger'),

            Stat::make('Remaining Amount', '$' . number_format($totalRemaining, 2))
                ->description('$' . number_format($todayRemaining, 2) . ' remaining today')
                ->descriptionIcon('heroicon-m-calculator')
                ->chart($remainingAmountsLastWeek)
                ->color('warning'),
        ]);

        return $stats;
    }
}

<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Team;
use App\Models\Lead;
use App\Models\LeadAmount;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
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
        // Get data for the last 7 days
        $usersLastWeek = collect(range(6, 0))->map(function ($days) {
            return User::whereDate('created_at', Carbon::now()->subDays($days))->count();
        })->toArray();

        $teamsLastWeek = collect(range(6, 0))->map(function ($days) {
            return Team::whereDate('created_at', Carbon::now()->subDays($days))->count();
        })->toArray();

        $leadsLastWeek = collect(range(6, 0))->map(function ($days) {
            return Lead::whereDate('created_at', Carbon::now()->subDays($days))->count();
        })->toArray();

        // Get cleared amounts for each day in the last 7 days using payment_date or created_at as fallback
        $clearedAmountsLastWeek = collect(range(6, 0))->map(function ($days) {
            $date = Carbon::now()->subDays($days)->format('Y-m-d');
            return DB::table('lead_amounts')
                ->where(function ($query) use ($date) {
                    $query->whereDate('payment_date', $date)
                        ->orWhere(function ($q) use ($date) {
                            $q->whereNull('payment_date')
                               ->whereDate('created_at', $date);
                        });
                })
                ->sum('amount_cleared');
        })->toArray();

        $owedAmountsLastWeek = collect(range(6, 0))->map(function ($days) {
            return Lead::whereDate('created_at', Carbon::now()->subDays($days))
                ->sum('amount_owed');
        })->toArray();

        // Calculate remaining amounts using the lead_amounts payment_date
        $remainingAmountsLastWeek = collect(range(6, 0))->map(function ($days) {
            $date = Carbon::now()->subDays($days);

            return DB::table('leads')
                ->leftJoin('lead_amounts', 'leads.id', '=', 'lead_amounts.lead_id')
                ->where(function ($query) use ($date) {
                    $query->whereNull('lead_amounts.payment_date')
                        ->orWhereDate('lead_amounts.payment_date', '<=', $date);
                })
                ->selectRaw('SUM(leads.amount_owed) - COALESCE(SUM(lead_amounts.amount_cleared), 0) as remaining_amount')
                ->value('remaining_amount') ?? 0;
        })->toArray();

        // Calculate total remaining amount
        $totalRemaining = DB::table('leads')
            ->leftJoin('lead_amounts', 'leads.id', '=', 'lead_amounts.lead_id')
            ->selectRaw('SUM(leads.amount_owed) - COALESCE(SUM(lead_amounts.amount_cleared), 0) as remaining_amount')
            ->value('remaining_amount') ?? 0;

        // Get today's cleared amount using payment_date or created_at as fallback
        $todayClearedAmount = DB::table('lead_amounts')
            ->where(function ($query) {
                $query->whereDate('payment_date', Carbon::today())
                    ->orWhere(function ($q) {
                        $q->whereNull('payment_date')
                           ->whereDate('created_at', Carbon::today());
                    });
            })
            ->sum('amount_cleared');

        return [
            Stat::make('Total Users', User::count())
                ->description(User::whereDate('created_at', Carbon::today())->count() . ' today')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($usersLastWeek)
                ->color('success'),

            Stat::make('Total Teams', Team::count())
                ->description(Team::whereDate('created_at', Carbon::today())->count() . ' today')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($teamsLastWeek)
                ->color('info'),

            Stat::make('Total Leads', Lead::count())
                ->description(Lead::whereDate('created_at', Carbon::today())->count() . ' today')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($leadsLastWeek)
                ->color('warning'),

            Stat::make('Total Amount Cleared', '$' . number_format(LeadAmount::sum('amount_cleared'), 2))
                ->description('$' . number_format($todayClearedAmount, 2) . ' today')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->chart($clearedAmountsLastWeek)
                ->color('success'),

            Stat::make('Total Amount Owed', '$' . number_format(Lead::sum('amount_owed'), 2))
                ->description('$' . number_format(Lead::whereDate('created_at', Carbon::today())->sum('amount_owed'), 2) . ' today')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->chart($owedAmountsLastWeek)
                ->color('danger'),

            Stat::make('Remaining Amount', '$' . number_format($totalRemaining, 2))
                ->description('$' . number_format($todayClearedAmount, 2) . ' cleared today')
                ->descriptionIcon('heroicon-m-calculator')
                ->chart($remainingAmountsLastWeek)
                ->color('warning'),
        ];
    }
}

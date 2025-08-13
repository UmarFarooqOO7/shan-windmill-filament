<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Lead,Status};
use App\Models\LeadAmount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Traits\HasTeamScope;

class DashboardController extends Controller
{
    use HasTeamScope;

    public function index()
    {
        // Build query & apply team scope directly from trait
        $leadQuery = Lead::query();
        $leadQuery = $this->applyTeamScope($leadQuery);

        // Leads last 7 days
        $leadsLastWeek = collect(range(6, 0))->map(fn($days) =>
            $leadQuery->clone()
                ->whereDate('created_at', Carbon::now()->subDays($days))
                ->count()
        )->toArray();

        // Cleared amounts last 7 days
        $clearedAmountsLastWeek = collect(range(6, 0))->map(function ($days) use ($leadQuery) {
            $date = Carbon::now()->subDays($days)->format('Y-m-d');
            return DB::table('lead_amounts')
                ->whereIn('lead_id', $leadQuery->pluck('id'))
                ->whereDate('payment_date', $date)
                ->sum('amount_cleared');
        })->toArray();

        // Owed amounts last 7 days
        $owedAmountsLastWeek = collect(range(6, 0))->map(function ($days) use ($leadQuery) {
            $date = Carbon::now()->subDays($days);
            return $leadQuery->clone()
                ->whereDate('created_at', $date)
                ->sum('amount_owed');
        })->toArray();

        // Remaining amounts last 7 days
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

        // Totals
        $totalOwed = $leadQuery->sum('amount_owed');
        $totalCleared = LeadAmount::whereIn('lead_id', $leadQuery->pluck('id'))->sum('amount_cleared');
        $totalRemaining = $totalOwed - $totalCleared;

        // Today's amounts
        $todayOwed = $leadQuery->clone()
            ->whereDate('created_at', Carbon::today())
            ->sum('amount_owed');

        $todayCleared = LeadAmount::whereIn('lead_id', $leadQuery->pluck('id'))
            ->whereDate('payment_date', Carbon::today())
            ->sum('amount_cleared');

        return $this->success([
            'totals' => [
                'total_cleared'   => $totalCleared,
                'total_owed'      => $totalOwed,
                'total_remaining' => $totalRemaining,
            ],
            'charts' => [
                'leads_last_week'      => $leadsLastWeek,
                'cleared_last_week'    => $clearedAmountsLastWeek,
                'owed_last_week'       => $owedAmountsLastWeek,
                'remaining_last_week'  => $remainingAmountsLastWeek,
            ],
            'today' => [
                'today_leads'   => $leadQuery->whereDate('created_at', Carbon::today())->count(),
                'today_cleared' => $todayCleared,
                'today_owed'    => $todayOwed,
            ]
        ]);
    }

    public function statusStats()
{
    $setoutStatuses = Status::where('type', 'setout')
        ->select(['statuses.*', DB::raw('COUNT(leads.id) as lead_count')])
        ->leftJoin('leads', 'statuses.id', '=', 'leads.setout_id')
        ->groupBy('statuses.id')
        ->orderByDesc('lead_count')
        ->get();

    $writStatuses = Status::where('type', 'writ')
        ->select(['statuses.*', DB::raw('COUNT(leads.id) as lead_count')])
        ->leftJoin('leads', 'statuses.id', '=', 'leads.writ_id')
        ->groupBy('statuses.id')
        ->orderByDesc('lead_count')
        ->get();

    $leadStatuses = Status::where('type', 'lead')
     ->select(['statuses.*', DB::raw('COUNT(leads.id) as lead_count')])
    ->leftJoin('leads', 'statuses.id', '=', 'leads.status_id')
    ->groupBy('statuses.id')
    ->orderByDesc('lead_count')
    ->get();

    return $this->success([
        'setout' => $setoutStatuses,
        'writ'   => $writStatuses,
        'lead'   => $leadStatuses,
    ]);
}



}

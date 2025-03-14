<?php

namespace App\Traits;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

trait HasTeamScope
{
    protected function getUserTeamIds(): array
    {
        $user = Filament::auth()->user();

        if (!$user || $user->is_admin) {
            return [];
        }

        $userTeamIds = $user->teams->pluck('id')->toArray();

        // Get all child teams of user's teams
        $childTeamIds = [];
        foreach ($user->teams as $team) {
            $childTeamIds = array_merge($childTeamIds, $team->subTeams->pluck('id')->toArray());
        }

        // Combine user's direct teams and their child teams
        return array_unique(array_merge($userTeamIds, $childTeamIds));
    }

    protected function applyTeamScope(Builder $query): Builder
    {
        $teamIds = $this->getUserTeamIds();

        if (!empty($teamIds)) {
            return $query->whereHas('teams', function ($query) use ($teamIds) {
                $query->whereIn('teams.id', $teamIds);
            });
        }

        return $query;
    }
}

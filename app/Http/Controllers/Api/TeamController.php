<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $teams = Team::query()
            ->whereHas('members', function ($query) {
                $query->where('users.id', auth()->id());
            })
            ->when($request->search, function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->search . '%');
            })
            ->get();

        return $this->success($teams, 'Teams fetched successfully');

    }

    public function show(Team $team)
    {
        $team->load([
            'members:id,name,email',       // Customize as needed
            'parent:id,name',
            'subTeams:id,name,team_id'
        ]);

        return $this->success($team, 'Team details fetched successfully.');
    }

}

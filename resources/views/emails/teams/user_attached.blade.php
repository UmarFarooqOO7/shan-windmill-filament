<x-mail::message>
# Added to Team

Hi {{ $user->name }},

You have been added to the team: **{{ $team->name }}**.

You can now collaborate with other members of this team.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>

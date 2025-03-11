@extends('filament::page')

@section('content')
    <div class="space-y-4">
        <x-filament::card>
            <h2 class="text-xl font-bold">Employee Details</h2>
            <p><strong>Name:</strong> {{ $record->name }}</p>
            <p><strong>Email:</strong> {{ $record->email }}</p>
        </x-filament::card>

        <x-filament::card>
            <h2 class="text-xl font-bold">Time Entries</h2>
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clock In</th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clock Out</th>
                        <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Time</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach ($record->timeEntries as $timeEntry)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $timeEntry->clock_in }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $timeEntry->clock_out }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($timeEntry->clock_out)
                                    {{ \Carbon\Carbon::parse($timeEntry->clock_out)->longAbsoluteDiffForHumans(\Carbon\Carbon::parse($timeEntry->clock_in)) }}
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::card>
    </div>
@endsection

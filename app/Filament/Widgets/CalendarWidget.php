<?php

namespace App\Filament\Widgets;

use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget
{
    public static function canView(): bool
    {
        return false;
    }

    /**
     * FullCalendar will call this function whenever it needs new event data.
     * This is triggered when the user clicks prev/next or switches views on the calendar.
     */
    public function fetchEvents(array $fetchInfo): array
    {
        // You can use $fetchInfo to filter events by date.
        // This method should return an array of event-like objects. See: https://github.com/saade/filament-fullcalendar/blob/3.x/#returning-events
        // You can also return an array of EventData objects. See: https://github.com/saade/filament-fullcalendar/blob/3.x/#the-eventdata-class
        return [
            [
                'id' => 1,
                'title' => 'Sample Event 1',
                'start' => now()->startOfWeek()->format('Y-m-d'),
                'end' => now()->startOfWeek()->addDays(1)->format('Y-m-d'),
                'url' => '#', // Optional: URL to navigate to when event is clicked
                // 'shouldOpenUrlInNewTab' => true, // Optional: Whether to open the URL in a new tab
            ],
            [
                'id' => 2,
                'title' => 'Another Event',
                'start' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'end' => now()->addDays(2)->addHours(2)->format('Y-m-d H:i:s'),
                'backgroundColor' => 'red', // Optional: Background color
                'borderColor' => 'red', // Optional: Border color
            ],
            [
                'id' => 3,
                'title' => 'Multi-day Event',
                'start' => now()->addDays(5)->format('Y-m-d'),
                'end' => now()->addDays(7)->format('Y-m-d'),
            ]
        ];
    }

    public function config(): array
    {
        return [
            'firstDay' => 1, // Monday
        ];
    }
}

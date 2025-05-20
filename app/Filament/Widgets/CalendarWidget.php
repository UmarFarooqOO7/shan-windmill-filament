<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Actions;

class CalendarWidget extends FullCalendarWidget
{
    public Model|string|null $model = Event::class;

    // default view for the calendar on dashboard page
    // we want to show it on dedicated page
    public static function canView(): bool
    {
        return false;
    }

    protected function headerActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function modalActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function getFormSchema(): array
    {
        return [
            TextInput::make('title'),

            Grid::make()
                ->schema([
                    DateTimePicker::make('start_at'),

                    DateTimePicker::make('end_at'),
                ]),
        ];
    }

    /**
     * FullCalendar will call this function whenever it needs new event data.
     * This is triggered when the user clicks prev/next or switches views on the calendar.
     */
    public function fetchEvents(array $fetchInfo): array
    {
        // Get the start and end dates from $fetchInfo to filter events
        // These are usually Carbon objects or can be parsed into them.
        $start = Carbon::parse($fetchInfo['start']);
        $end = Carbon::parse($fetchInfo['end']);

        return Event::query()
            ->where('start_at', '>=', $start)
            ->where('end_at', '<=', $end)
            // Or, for events that might span across the range:
            // ->where(function ($query) use ($start, $end) {
            //     $query->where('start_at', '<=', $end)
            //           ->where('end_at', '>=', $start);
            // })
            ->get()
            ->map(function (Event $event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'start' => $event->start_at->toDateTimeString(), // Ensure correct format
                    'end' => $event->end_at?->toDateTimeString(), // Ensure correct format, handle null for all-day
                    'allDay' => $event->all_day,
                    // You can add other properties like 'url', 'backgroundColor', 'borderColor' here
                    // 'url' => route('filament.admin.resources.events.edit', $event), // Example URL
                    // 'backgroundColor' => $event->color,
                    // 'borderColor' => $event->color,
                ];
            })
            ->all();
    }

    public function config(): array
    {
        return [
            'firstDay' => 1, // Monday
        ];
    }
}

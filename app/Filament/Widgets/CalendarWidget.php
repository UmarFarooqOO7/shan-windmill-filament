<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\User; // Import User model for Select options
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea; // Import Textarea
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle; // Import Toggle
use Filament\Forms\Components\Select; // Import Select
use Filament\Forms\Form;
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
            Actions\CreateAction::make()
                ->mountUsing(
                    function (Form $form, array $arguments) {
                        $form->fill([
                            'start_at' => $arguments['start'] ?? null,
                            'end_at' => $arguments['end'] ?? null
                        ]);
                    }
                )
        ];
    }

    protected function modalActions(): array
    {
        return [
            Actions\EditAction::make()
                ->mountUsing(
                    function (Event $record, Form $form, array $arguments) {
                        $form->fill([
                            'title' => $record->title,
                            'start_at' => $arguments['event']['start'] ?? $record->start_at,
                            'end_at' => $arguments['event']['end'] ?? $record->end_at,
                            'description' => $record->description,
                            'all_day' => $record->all_day,
                        ]);
                    }
                ),
            Actions\DeleteAction::make(),
        ];
    }

    public function getFormSchema(): array
    {
        return [
            TextInput::make('title')
                ->label('Title')
                ->required(),

            Grid::make(2) // Using a 2-column grid for start and end times
                ->schema([
                    DateTimePicker::make('start_at')
                        ->label('Start Date & Time')
                        ->required(),

                    DateTimePicker::make('end_at')
                        ->label('End Date & Time')
                        ->nullable(),
                ]),

            Textarea::make('description')
                ->label('Description')
                ->nullable()
                ->columnSpanFull(),

            Toggle::make('all_day')
                ->label('All-day event')
                ->default(false)
                ->columnSpanFull(), // Make toggle take full width if desired, or remove for default
        ];
    }

    /**
     * Called when an event is dropped onto the calendar.
     * The method signature must match the parent class.
     */
    public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta, ?array $oldResource = null, ?array $newResource = null): bool
    {
        if ($this->record = $this->resolveRecord($event['id'])) {
            $this->record->fill([
                'start_at' => $event['start'],
                'end_at' => $event['end'] ?? $this->record->end_at, // Use existing end_at if new one is null
                'all_day' => $event['allDay'] ?? $this->record->all_day,
            ]);
            $this->record->save();
        }
        $this->dispatch('refreshCalendar');
        return false; // Returning false prevents the default modal from opening
    }

    /**
     * Called when an event is resized.
     * The method signature must match the parent class.
     */
    public function onEventResize(array $event, array $oldEvent, array $relatedEvents, array $startDelta, array $endDelta): bool
    {
        if ($this->record = $this->resolveRecord($event['id'])) {
            $this->record->fill([
                'start_at' => $event['start'],
                'end_at' => $event['end'] ?? $this->record->end_at, // Use existing end_at if new one is null
            ]);
            $this->record->save();
        }
        $this->dispatch('refreshCalendar');
        return false; // Returning false prevents the default modal from opening
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
            'initialView' => 'dayGridMonth', // show week by week
            'headerToolbar' => [
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
                'center' => 'title',
                'left' => 'prev,next today',
            ],
        ];
    }

    public function eventDidMount(): string
    {
        return <<<JS
        function({ event, timeText, isStart, isEnd, isMirror, isPast, isFuture, isToday, el, view }){
            el.setAttribute("x-tooltip", "tooltip");
            el.setAttribute("x-data", "{ tooltip: '"+event.title+"' }");
        }
    JS;
    }
}

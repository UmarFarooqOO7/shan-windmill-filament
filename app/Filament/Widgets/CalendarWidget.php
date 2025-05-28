<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea; // Import Textarea
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle; // Import Toggle
use Filament\Forms\Form;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Actions; // This is for Saade's specific calendar actions
use App\Filament\Resources\LeadResource;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr; // Added import
use Filament\Actions\Action; // Added for general Filament actions
use Illuminate\Support\Facades\Auth; // Added for checking user authentication

class CalendarWidget extends FullCalendarWidget
{
    public Model|string|null $model = Event::class;

    // default view for the calendar on dashboard page
    // we want to show it on dedicated page
    public static function canView(): bool
    {
        return false;
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

    protected function headerActions(): array
    {
        $user = Auth::user();
        $googleCalendarActions = [];

        $defaultCalendarActions = [
            Actions\CreateAction::make() // This is Saade\FilamentFullCalendar\Actions\CreateAction
                ->mountUsing(
                    function (Form $form, array $arguments) {
                        $form->fill([
                            'start_at' => $arguments['start'] ?? null,
                            'end_at' => $arguments['end'] ?? null
                        ]);
                    }
                )
        ];

        if ($user) {
            if (!$user->google_access_token) {
                $googleCalendarActions[] = Action::make('connectGoogleCalendar')
                    ->label('Connect Google Calendar')
                    ->action(fn() => redirect(url('/google-auth')))
                    ->icon('heroicon-o-link');
            } else {
                // Only show Disconnect button if connected
                $googleCalendarActions[] = Action::make('disconnectGoogleCalendar')
                    ->label('Disconnect Google Calendar')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->action(fn() => redirect(url('/google-disconnect')));
            }
        }
        return array_merge($googleCalendarActions, $defaultCalendarActions);
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

        // show a notification that the event was moved
        Notification::make()
            ->success()
            ->title('Event Moved')
            ->body(sprintf(
                'The event has been successfully moved from %s to %s.',
                Carbon::parse($oldEvent['start'])->format('M d, Y g:ia'),
                Carbon::parse($event['start'])->format('M d, Y g:ia')
            ))
            ->send();

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

        // show a notification that the event was resized
        Notification::make()
            ->success()
            ->title('Event Resized')
            ->body(sprintf(
                'The event has been successfully resized from %s to %s.',
                Carbon::parse($oldEvent['start'])->format('M d, Y g:ia'),
                Carbon::parse($event['end'])->format('M d, Y g:ia')
            ))
            ->send();

        $this->dispatch('refreshCalendar');

        return false; // Returning false prevents the default modal from opening
    }


    /**
     * FullCalendar will call this function whenever it needs new event data.
     * This is triggered when the user clicks prev/next or switches views on the calendar.
     */
    public function fetchEvents(array $fetchInfo): array
    {
        $start = Carbon::parse($fetchInfo['start']);
        $end = Carbon::parse($fetchInfo['end']);

        return Event::query()
            ->where('start_at', '<=', $end) // Event starts before or at view_end
            ->where(function ($query) use ($start) {
                $query->where('end_at', '>=', $start) // Event ends after or at view_start
                    ->orWhere(function ($q) use ($start) { // Or, event has no end_at (e.g. all_day on start_at) AND its start_at is within view
                        $q->whereNull('end_at')
                            ->where('start_at', '>=', $start);
                    });
            })
            ->get()
            ->map(function (Event $event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'start' => $event->start_at->toDateTimeString(),
                    'end' => $event->end_at?->toDateTimeString(),
                    'allDay' => $event->all_day,
                    'backgroundColor' => $event->is_lead_setout ? '#10B981' : '#3B82F6', // Green for lead, blue for normal
                    'borderColor' => $event->is_lead_setout ? '#059669' : '#2563EB', // Darker green border for lead, darker blue for normal
                    'editable' => !$event->is_lead_setout, // Prevent dragging/resizing for lead setout events
                    'extendedProps' => [
                        'is_lead_setout' => $event->is_lead_setout,
                        'lead_id' => $event->lead_id,
                    ],
                    // Only add URL properties for lead setout events
                    ...$event->is_lead_setout ? [
                        'url' => LeadResource::getUrl(name: 'edit-page', parameters: ['record' => $event->lead_id]),
                        'shouldOpenUrlInNewTab' => true,
                    ] : [],
                ];
            })
            ->all();
    }

    public function config(): array
    {
        return [
            'firstDay' => 1, // Monday
            'initialView' => 'dayGridMonth',
            'headerToolbar' => [
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
                'center' => 'title',
                'left' => 'prev,next today',
            ],
            'editable' => true, // Global editable, individual events can override
            'selectable' => true, // Allows date clicking/selecting for creating events
        ];
    }

    public function eventDidMount(): string
    {
        return <<<'JS'
        function({ event, timeText, isStart, isEnd, isMirror, isPast, isFuture, isToday, el, view }){
            el.setAttribute("x-tooltip", "tooltip");
            el.setAttribute("x-data", "{ tooltip: '" + event.title.replace(/'/g, "\\'") + "' }");
        }
JS;
    }

    /**
     * Triggered when the user clicks an event.
     * @param array $event An Event Object that holds information about the event (date, title, etc).
     * @return void
     */
    public function onEventClick(array $event): void
    {
        if ($this->getModel()) {
            $this->record = $this->resolveRecord($event['id']);
        }

        $this->mountAction('view', [
            'type' => 'click',
            'event' => $event,
        ]);
    }
}

<!DOCTYPE html>
<html>
<head>
    <title>Event Updated</title>
</head>
<body>
    <h1>Event Updated: {{ $event->title }}</h1>
    <p>The event "{{ $event->title }}" has been updated.</p>
    <p><strong>New Details:</strong></p>
    <p><strong>Title:</strong> {{ $event->title }}</p>
    <p><strong>Description:</strong> {{ $event->description }}</p>
    <p><strong>Start Time:</strong> {{ $event->start_at }}</p>
    <p><strong>End Time:</strong> {{ $event->end_at }}</p>
    @if($event->google_calendar_event_id)
    <p>This event has been updated in Google Calendar.</p>
    @endif
</body>
</html>

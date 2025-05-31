<!DOCTYPE html>
<html>
<head>
    <title>New Event Created</title>
</head>
<body>
    <h1>New Event Created: {{ $event->title }}</h1>
    <p>A new event has been scheduled:</p>
    <p><strong>Title:</strong> {{ $event->title }}</p>
    <p><strong>Description:</strong> {{ $event->description }}</p>
    <p><strong>Start Time:</strong> {{ $event->start_at }}</p>
    <p><strong>End Time:</strong> {{ $event->end_at }}</p>
    @if($event->google_calendar_event_id)
    <p>This event has been synced to Google Calendar.</p>
    @endif
</body>
</html>

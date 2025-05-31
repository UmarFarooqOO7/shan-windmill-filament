<!DOCTYPE html>
<html>
<head>
    <title>Google Calendar Sync Error</title>
</head>
<body>
    <h1>Google Calendar Sync Error</h1>
    <p>An error occurred while trying to sync with Google Calendar.</p>
    <p><strong>Error Message:</strong> {{ $errorMessage }}</p>
    @if($userId)
    <p><strong>User ID:</strong> {{ $userId }}</p>
    @endif
    @if($eventId)
    <p><strong>Event ID:</strong> {{ $eventId }}</p>
    @endif
    <p>Please check the application logs for more details.</p>
</body>
</html>

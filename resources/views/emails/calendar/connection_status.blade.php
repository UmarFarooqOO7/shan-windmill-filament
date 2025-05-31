<!DOCTYPE html>
<html>
<head>
    <title>Google Calendar Connection Status</title>
</head>
<body>
    <h1>Google Calendar Connection Status</h1>
    @if($isConnected)
        <p>Hello {{ $user->name }},</p>
        <p>Your Google Calendar has been successfully connected to the application.</p>
        <p>Events will now be synced with your primary Google Calendar.</p>
    @else
        <p>Hello {{ $user->name }},</p>
        <p>Your Google Calendar has been disconnected from the application.</p>
        <p>Events will no longer be synced with your Google Calendar.</p>
    @endif
</body>
</html>

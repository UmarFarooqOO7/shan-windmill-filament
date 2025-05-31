<!DOCTYPE html>
<html>
<head>
    <title>Lead Status Updated</title>
</head>
<body>
    <h1>Lead Status Updated: {{ $lead->plaintiff }}</h1>
    <p>The status for Lead ID #{{ $lead->id }} ({{ $lead->plaintiff }}) has been changed.</p>
    <p><strong>Previous Status:</strong> {{ $previousStatus ? $previousStatus->name : 'N/A' }}</p>
    <p><strong>New Status:</strong> {{ $newStatus ? $newStatus->name : 'N/A' }}</p>
    <p><strong>Changed By:</strong> {{ $changedByUser->name }}</p>
    <p>Please login to the system to view more details.</p>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <title>Lead Status Change Approved</title>
</head>
<body>
    <h1>Lead Status Change Approved: {{ $lead->plaintiff }}</h1>
    <p>The requested status change for Lead ID #{{ $lead->id }} ({{ $lead->plaintiff }}) has been approved.</p>
    <p><strong>New Status:</strong> {{ $approvedStatus->name }}</p>
    <p><strong>Approved By:</strong> {{ $approvedByUser->name }}</p>
    <p>The lead status has been updated in the system.</p>
</body>
</html>

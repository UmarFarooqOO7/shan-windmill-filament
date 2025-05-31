<!DOCTYPE html>
<html>
<head>
    <title>Lead Status Change Rejected</title>
</head>
<body>
    <h1>Lead Status Change Rejected: {{ $lead->plaintiff }}</h1>
    <p>The requested status change for Lead ID #{{ $lead->id }} ({{ $lead->plaintiff }}) to "{{ $rejectedStatus->name }}" has been rejected.</p>
    <p><strong>Rejected By:</strong> {{ $rejectedByUser->name }}</p>
    <p><strong>Reason for Rejection:</strong> {{ $rejectionReason }}</p>
    <p>The lead status has not been changed.</p>
</body>
</html>

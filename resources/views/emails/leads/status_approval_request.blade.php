<!DOCTYPE html>
<html>
<head>
    <title>Lead Status Change Approval Required</title>
</head>
<body>
    <h1>Approval Required for Lead: {{ $lead->plaintiff }}</h1>
    <p>A status change for Lead ID #{{ $lead->id }} ({{ $lead->plaintiff }}) requires your approval.</p>
    <p><strong>Requested By:</strong> {{ $requestedByUser->name }}</p>
    <p><strong>Current Status:</strong> {{ $currentStatus ? $currentStatus->name : 'N/A' }}</p>
    <p><strong>Requested Status:</strong> {{ $requestedStatus->name }}</p>
    @if($reason)
    <p><strong>Reason:</strong> {{ $reason }}</p>
    @endif
    <p>Please login to the system to approve or reject this request.</p>
</body>
</html>

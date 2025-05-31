<!DOCTYPE html>
<html>
<head>
    <title>New Lead Created</title>
</head>
<body>
    <h1>New Lead Created: {{ $lead->plaintiff }}</h1>
    <p>A new lead has been created in the system.</p>
    <p><strong>Lead ID:</strong> {{ $lead->id }}</p>
    <p><strong>Plaintiff:</strong> {{ $lead->plaintiff }}</p>
    <p>Please login to the system to view more details.</p>
</body>
</html>

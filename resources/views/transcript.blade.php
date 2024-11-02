<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YouTube Transcript</title>
</head>

<body>
    <h1>Transcript</h1>
    @if(isset($transcript))
    <pre>{{ json_encode($transcript, JSON_PRETTY_PRINT) }}</pre>
    @else
    <p>No transcript found.</p>
    @endif
    <a href="/youtube">Go Back</a>
</body>

</html>
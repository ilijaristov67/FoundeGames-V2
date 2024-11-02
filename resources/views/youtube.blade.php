<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YouTube Transcript</title>
</head>

<body>
    <h1>YouTube Transcript Fetcher</h1>
    <form action="{{ route('getTranscript') }}" method="POST">
        @csrf
        <input type="text" name="video_url" placeholder="Enter YouTube Video URL" required>
        <button type="submit">Get Transcript</button>
    </form>

    @if ($errors->any())
    <div>
        <ul>
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif


</body>

</html>
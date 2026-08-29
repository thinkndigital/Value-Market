<!DOCTYPE html>
<html lang="en">
@php
    use App\Services\MediaService;
@endphp

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ app(MediaService::class)->getMediaImageUrl($setting['favicon']) }}">
    <title>Privacy Policy</title>
</head>

<body>
    <h2>Privacy Policy</h2>

    {!! $delivery_boy_privacy_policy['delivery_boy_privacy_policy'] ?? '' !!}

</body>

</html>

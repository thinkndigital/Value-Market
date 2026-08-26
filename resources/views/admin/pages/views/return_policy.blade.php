<!DOCTYPE html>
<html lang="en">
@php
    use App\Services\MediaService;
@endphp

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ app(MediaService::class)->getMediaImageUrl($setting['favicon']) }}">
    <title>Return Policy</title>
</head>

<body>
    <h2>Return Policy</h2>
    {{ $return_policy['return_policy'] }}
</body>

</html>

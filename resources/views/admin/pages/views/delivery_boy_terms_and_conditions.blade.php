<!DOCTYPE html>
<html lang="en">
@php
    use App\Services\MediaService;
@endphp

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ app(MediaService::class)->getMediaImageUrl($setting['favicon']) }}">
    <title>Terms &amp; Conditions</title>
</head>

<body>
    <h2>Terms &amp; Conditions</h2>

    {!! $delivery_boy_terms_and_conditions['delivery_boy_terms_and_conditions'] ?? '' !!}

</body>

</html>
